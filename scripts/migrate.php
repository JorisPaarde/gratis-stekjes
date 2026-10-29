#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Idempotent migrate-script: up | down | status
 * PHP 8.2+. MySQL op staging/prod; SQLite in CI via STEKJES_CONFIG / db_driver=sqlite.
 */

$root = dirname(__DIR__);
require_once $root . '/lib/bootstrap.php';

$cmd = $argv[1] ?? 'status';
$dir = $root . '/migrations';
$pdo = stekjes_db();
$driver = stekjes_db_driver();

ensure_migrations_table($pdo, $driver);

$versions = list_migration_versions($dir);

switch ($cmd) {
    case 'up':
        migrate_up($pdo, $dir, $versions, $driver);
        break;
    case 'down':
        $steps = isset($argv[2]) ? max(1, (int) $argv[2]) : 1;
        migrate_down($pdo, $dir, $versions, $driver, $steps);
        break;
    case 'status':
        migrate_status($pdo, $versions);
        break;
    default:
        fwrite(STDERR, "Gebruik: php scripts/migrate.php [up|down|status] [down-steps]\n");
        exit(1);
}

function ensure_migrations_table(PDO $pdo, string $driver): void
{
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT NOT NULL PRIMARY KEY,
            applied_at TEXT NOT NULL
        )');
        // SQLite testschema (spiegel van 0001) — alleen als users-tabel ontbreekt
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        if (!$exists) {
            stekjes_sqlite_bootstrap($pdo);
        }
        return;
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(32) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function stekjes_sqlite_bootstrap(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL COLLATE NOCASE UNIQUE,
  password_hash TEXT NOT NULL,
  voornaam TEXT NOT NULL DEFAULT '',
  telefoon TEXT NOT NULL DEFAULT '',
  locatie_label TEXT NOT NULL DEFAULT '',
  lat REAL,
  lon REAL,
  email_confirmed_at TEXT,
  confirm_token TEXT,
  reset_token TEXT,
  reset_expires TEXT,
  is_admin INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS aanbod (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  type TEXT NOT NULL CHECK(type IN ('uitdelen','ophalen')),
  tekst TEXT NOT NULL,
  visible INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS conversations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aanbod_id INTEGER,
  user_a_id INTEGER NOT NULL,
  user_b_id INTEGER NOT NULL,
  contact_a TEXT CHECK(contact_a IS NULL OR contact_a IN ('ja','nee')),
  contact_b TEXT CHECK(contact_b IS NULL OR contact_b IN ('ja','nee')),
  created_at TEXT NOT NULL,
  UNIQUE(user_a_id, user_b_id, aanbod_id),
  FOREIGN KEY(user_a_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(user_b_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(aanbod_id) REFERENCES aanbod(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  conversation_id INTEGER NOT NULL,
  sender_id INTEGER NOT NULL,
  body TEXT NOT NULL,
  created_at TEXT NOT NULL,
  read_at TEXT,
  FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS blocks (
  blocker_id INTEGER NOT NULL,
  blocked_id INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  PRIMARY KEY (blocker_id, blocked_id)
);
CREATE TABLE IF NOT EXISTS reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  reporter_id INTEGER NOT NULL,
  reported_user_id INTEGER NOT NULL,
  conversation_id INTEGER,
  reason TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  handled INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS helpers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  naam TEXT NOT NULL,
  email TEXT NOT NULL COLLATE NOCASE,
  wat TEXT NOT NULL,
  confirm_token TEXT,
  confirmed_at TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS partner_markers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  naam TEXT NOT NULL,
  lat REAL NOT NULL,
  lon REAL NOT NULL,
  url TEXT NOT NULL DEFAULT '',
  bron TEXT NOT NULL DEFAULT '',
  visible INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS rate_limits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip_hash TEXT NOT NULL,
  action TEXT NOT NULL,
  created_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS geocode_cache (
  query_key TEXT PRIMARY KEY,
  lat REAL NOT NULL,
  lon REAL NOT NULL,
  label TEXT NOT NULL,
  cached_at TEXT NOT NULL
);
SQL);
}

/** @return list<string> */
function list_migration_versions(string $dir): array
{
    $files = glob($dir . '/*.up.sql') ?: [];
    $versions = [];
    foreach ($files as $f) {
        if (preg_match('/(\d{4}_[a-z0-9_]+)\.up\.sql$/', basename($f), $m)) {
            $versions[] = $m[1];
        }
    }
    sort($versions);
    return $versions;
}

/** @return list<string> */
function applied_versions(PDO $pdo): array
{
    $rows = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
    return array_map('strval', $rows ?: []);
}

function migrate_status(PDO $pdo, array $versions): void
{
    $applied = applied_versions($pdo);
    foreach ($versions as $v) {
        $mark = in_array($v, $applied, true) ? 'applied' : 'pending';
        echo "$v\t$mark\n";
    }
}

function migrate_up(PDO $pdo, string $dir, array $versions, string $driver): void
{
    $applied = applied_versions($pdo);
    foreach ($versions as $v) {
        if (in_array($v, $applied, true)) {
            echo "skip $v\n";
            continue;
        }
        if ($driver === 'sqlite') {
            // Schema al via bootstrap; markeer migratie
            $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                ->execute([$v, gmdate('c')]);
            echo "applied $v (sqlite bootstrap)\n";
            continue;
        }
        $file = "$dir/$v.up.sql";
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Kan $file niet lezen");
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP())')
                ->execute([$v]);
            $pdo->commit();
            echo "applied $v\n";
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

function migrate_down(PDO $pdo, string $dir, array $versions, string $driver, int $steps): void
{
    $applied = applied_versions($pdo);
    $toRevert = array_reverse(array_values(array_filter($applied, static fn ($v) => in_array($v, $versions, true))));
    $toRevert = array_slice($toRevert, 0, $steps);
    foreach ($toRevert as $v) {
        $file = "$dir/$v.down.sql";
        if ($driver === 'sqlite') {
            // Voor CI: drop all app tables behalve schema_migrations row
            stekjes_sqlite_drop_all($pdo);
            $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?')->execute([$v]);
            echo "reverted $v (sqlite)\n";
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Kan $file niet lezen");
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?')->execute([$v]);
            // down.sql dropte schema_migrations — herstel lege tabel
            ensure_migrations_table($pdo, $driver);
            $pdo->commit();
            echo "reverted $v\n";
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

function stekjes_sqlite_drop_all(PDO $pdo): void
{
    $tables = [
        'geocode_cache', 'rate_limits', 'partner_markers', 'helpers', 'reports',
        'blocks', 'messages', 'conversations', 'aanbod', 'users',
    ];
    $pdo->exec('PRAGMA foreign_keys = OFF');
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS $t");
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
}
