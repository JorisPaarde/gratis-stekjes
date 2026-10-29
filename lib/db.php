<?php

declare(strict_types=1);

/**
 * PDO DB-laag: MySQL op productie, SQLite voor tests/CI.
 * Versie: 0.2.0
 */

function stekjes_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = stekjes_config();
    $driver = strtolower((string) ($config['db_driver'] ?? 'mysql'));

    if ($driver === 'sqlite') {
        $path = (string) ($config['db_path'] ?? '');
        if ($path === '') {
            throw new RuntimeException('db_path ontbreekt voor sqlite');
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $host = trim((string) ($config['db_host'] ?? ''));
        if ($host === '') {
            $host = 'localhost';
        }
        $name = (string) ($config['db_name'] ?? '');
        $user = (string) ($config['db_user'] ?? '');
        $pass = (string) ($config['db_pass'] ?? '');
        $charset = (string) ($config['db_charset'] ?? 'utf8mb4');
        if ($name === '') {
            throw new RuntimeException('db_name ontbreekt');
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('pdo_mysql-extensie ontbreekt');
        }
        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

function stekjes_db_driver(): string
{
    return strtolower((string) (stekjes_config()['db_driver'] ?? 'mysql'));
}

/** Placeholder-stijl: MySQL = ?, SQLite = ? — beide PDO named/positional ok. */
function stekjes_now_sql(): string
{
    return stekjes_db_driver() === 'sqlite' ? "datetime('now')" : 'UTC_TIMESTAMP()';
}
