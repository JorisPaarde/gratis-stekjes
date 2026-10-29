#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Idempotent migrate-script: up | down | status
 * PHP 8.2+. MySQL op staging/prod; SQLite in CI.
 * Fouten: korte reden op stderr, nooit DSN/wachtwoord/host in de output.
 *
 * MySQL: SQL-bestanden worden statement-voor-statement uitgevoerd (geen multi-query).
 * DDL commit implicit — migraties na 0001 zijn waar nodig idempotent.
 */

$root = dirname(__DIR__);

try {
    stekjes_migrate_main($root, $argv);
} catch (Throwable $e) {
    stekjes_migrate_fail($e);
}

function stekjes_migrate_fail(Throwable $e): void
{
    $msg = stekjes_migrate_safe_message($e);
    fwrite(STDERR, 'migrate mislukt: ' . $e::class . ' — ' . $msg . "\n");
    exit(1);
}

function stekjes_migrate_safe_message(Throwable $e): string
{
    $msg = $e->getMessage();
    $msg = preg_replace('/mysql:host=[^;\s]+/i', 'mysql:host=***', $msg) ?? $msg;
    $msg = preg_replace('/host=[^;\s]+/i', 'host=***', $msg) ?? $msg;
    $msg = preg_replace('/dbname=[^;\s]+/i', 'dbname=***', $msg) ?? $msg;
    $msg = preg_replace('/user[^\s]*[=:]\s*\S+/i', 'user=***', $msg) ?? $msg;
    $msg = preg_replace('/pass(word)?[^\s]*[=:]\s*\S+/i', 'pass=***', $msg) ?? $msg;
    $msg = preg_replace('/\/[Hh]ome\/[^\s\/]+/', '/home/***', $msg) ?? $msg;
    return trim($msg) !== '' ? trim($msg) : 'onbekende fout';
}

/** @param list<string> $argv */
function stekjes_migrate_main(string $root, array $argv): void
{
    $configEnv = getenv('STEKJES_CONFIG');
    if (is_string($configEnv) && $configEnv !== '') {
        if (!is_file($configEnv)) {
            throw new RuntimeException('STEKJES_CONFIG wijst niet naar een bestand (pad niet gevonden).');
        }
        if (!is_readable($configEnv)) {
            throw new RuntimeException('STEKJES_CONFIG-bestand is niet leesbaar.');
        }
    }

    require_once $root . '/lib/bootstrap.php';

    $driver = stekjes_db_driver();
    if ($driver === 'mysql' && !extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PHP-extensie pdo_mysql ontbreekt in de CLI.');
    }
    if ($driver === 'sqlite' && !extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PHP-extensie pdo_sqlite ontbreekt in de CLI.');
    }

    $cfg = stekjes_config();
    if ($driver === 'mysql') {
        $host = trim((string) ($cfg['db_host'] ?? ''));
        $name = trim((string) ($cfg['db_name'] ?? ''));
        $user = trim((string) ($cfg['db_user'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('db_host is leeg in config (verwacht o.a. localhost op gedeelde hosting).');
        }
        if ($name === '' || $user === '') {
            throw new RuntimeException('db_name of db_user is leeg in config.');
        }
    }

    $cmd = $argv[1] ?? 'status';
    $dir = $root . '/migrations';
    $pdo = stekjes_db();

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
}

function ensure_migrations_table(PDO $pdo, string $driver): void
{
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT NOT NULL PRIMARY KEY,
            applied_at TEXT NOT NULL
        )');
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
    // Inclusief 0002-kolommen zodat verse CI-DB overeenkomt met actuele schema.
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
  expires_at TEXT NOT NULL,
  expiry_reminder_sent_at TEXT,
  renew_token TEXT,
  renew_token_expires TEXT,
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

/**
 * Split SQL file into statements (geen multi-query nodig).
 * @return list<string>
 */
function stekjes_split_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*\n/', $sql) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || str_starts_with($part, '--')) {
            continue;
        }
        $out[] = rtrim($part, "; \t\n\r");
    }
    return $out;
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
            if ($v !== '0001_init') {
                stekjes_sqlite_apply_migration($pdo, $v);
            }
            $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                ->execute([$v, gmdate('c')]);
            echo "applied $v (sqlite)\n";
            continue;
        }

        // MySQL: idempotente helpers voor bekende migraties (DDL + eventuele half-gelukte deploy)
        if ($v === '0002_aanbod_expiry') {
            stekjes_mysql_apply_0002($pdo);
            $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP())')
                ->execute([$v]);
            echo "applied $v\n";
            continue;
        }

        $file = "$dir/$v.up.sql";
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Kan migratiebestand niet lezen: $v.up.sql");
        }
        foreach (stekjes_split_sql_statements($sql) as $i => $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Migratie $v statement #" . ($i + 1) . ' mislukt: ' . stekjes_migrate_safe_message($e),
                    0,
                    $e
                );
            }
        }
        $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP())')
            ->execute([$v]);
        echo "applied $v\n";
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
            stekjes_sqlite_drop_all($pdo);
            $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?')->execute([$v]);
            echo "reverted $v (sqlite)\n";
            continue;
        }
        if ($v === '0002_aanbod_expiry') {
            stekjes_mysql_revert_0002($pdo);
            $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?')->execute([$v]);
            echo "reverted $v\n";
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Kan down-migratie niet lezen: $v.down.sql");
        }
        foreach (stekjes_split_sql_statements($sql) as $stmt) {
            $pdo->exec($stmt);
        }
        $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?')->execute([$v]);
        ensure_migrations_table($pdo, $driver);
        echo "reverted $v\n";
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

function stekjes_sqlite_apply_migration(PDO $pdo, string $version): void
{
    if ($version === '0002_aanbod_expiry') {
        $cols = $pdo->query('PRAGMA table_info(aanbod)')->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        if (!in_array('expires_at', $names, true)) {
            $pdo->exec('ALTER TABLE aanbod ADD COLUMN expires_at TEXT');
            $pdo->exec("UPDATE aanbod SET expires_at = datetime(created_at, '+60 days') WHERE expires_at IS NULL OR expires_at = ''");
        }
        if (!in_array('expiry_reminder_sent_at', $names, true)) {
            $pdo->exec('ALTER TABLE aanbod ADD COLUMN expiry_reminder_sent_at TEXT');
        }
        if (!in_array('renew_token', $names, true)) {
            $pdo->exec('ALTER TABLE aanbod ADD COLUMN renew_token TEXT');
        }
        if (!in_array('renew_token_expires', $names, true)) {
            $pdo->exec('ALTER TABLE aanbod ADD COLUMN renew_token_expires TEXT');
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_aanbod_renew_token ON aanbod (renew_token)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_aanbod_expires ON aanbod (expires_at)');
    }
}

function stekjes_mysql_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function stekjes_mysql_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Idempotente MySQL-toepassing van 0002 (veilig na half-gelukte deploy:
 * ADD COLUMN commit’t implicit; tweede run mag niet crashen op duplicate column).
 */
function stekjes_mysql_apply_0002(PDO $pdo): void
{
    if (!stekjes_mysql_column_exists($pdo, 'aanbod', 'expires_at')) {
        $pdo->exec('ALTER TABLE aanbod ADD COLUMN expires_at DATETIME NULL');
    }
    if (!stekjes_mysql_column_exists($pdo, 'aanbod', 'expiry_reminder_sent_at')) {
        $pdo->exec('ALTER TABLE aanbod ADD COLUMN expiry_reminder_sent_at DATETIME NULL');
    }
    if (!stekjes_mysql_column_exists($pdo, 'aanbod', 'renew_token')) {
        $pdo->exec('ALTER TABLE aanbod ADD COLUMN renew_token VARCHAR(128) NULL');
    }
    if (!stekjes_mysql_column_exists($pdo, 'aanbod', 'renew_token_expires')) {
        $pdo->exec('ALTER TABLE aanbod ADD COLUMN renew_token_expires DATETIME NULL');
    }

    $pdo->exec('UPDATE aanbod SET expires_at = DATE_ADD(created_at, INTERVAL 60 DAY) WHERE expires_at IS NULL');

    // NOT NULL pas als alle rijen gevuld zijn
    $nulls = (int) $pdo->query('SELECT COUNT(*) FROM aanbod WHERE expires_at IS NULL')->fetchColumn();
    if ($nulls > 0) {
        throw new RuntimeException("0002: $nulls aanbod-rijen hebben nog geen expires_at na UPDATE.");
    }
    $pdo->exec('ALTER TABLE aanbod MODIFY expires_at DATETIME NOT NULL');

    if (!stekjes_mysql_index_exists($pdo, 'aanbod', 'idx_aanbod_expires')) {
        $pdo->exec('ALTER TABLE aanbod ADD KEY idx_aanbod_expires (expires_at)');
    }
    if (!stekjes_mysql_index_exists($pdo, 'aanbod', 'uq_aanbod_renew_token')) {
        $pdo->exec('ALTER TABLE aanbod ADD UNIQUE KEY uq_aanbod_renew_token (renew_token)');
    }
}

function stekjes_mysql_revert_0002(PDO $pdo): void
{
    if (stekjes_mysql_index_exists($pdo, 'aanbod', 'uq_aanbod_renew_token')) {
        $pdo->exec('ALTER TABLE aanbod DROP INDEX uq_aanbod_renew_token');
    }
    if (stekjes_mysql_index_exists($pdo, 'aanbod', 'idx_aanbod_expires')) {
        $pdo->exec('ALTER TABLE aanbod DROP INDEX idx_aanbod_expires');
    }
    foreach (['renew_token_expires', 'renew_token', 'expiry_reminder_sent_at', 'expires_at'] as $col) {
        if (stekjes_mysql_column_exists($pdo, 'aanbod', $col)) {
            $pdo->exec("ALTER TABLE aanbod DROP COLUMN `$col`");
        }
    }
}
