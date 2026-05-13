<?php

declare(strict_types=1);

function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '')
    {
        return $default;
    }

    return $value;
}

function db_connect(): PDO
{
    $host = env_or_default('DB_HOST', '127.0.0.1');
    $port = env_or_default('DB_PORT', '3306');
    $name = env_or_default('DB_NAME', 'phpmon');
    $user = env_or_default('DB_USER', 'root');
    $pass = env_or_default('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function ensure_schema_migrations_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            version VARCHAR(255) NOT NULL,
            migrated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schema_migrations_version (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensure_schema_seeds_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_seeds (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            version VARCHAR(255) NOT NULL,
            seeded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schema_seeds_version (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
