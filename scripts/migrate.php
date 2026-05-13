<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$pdo = db_connect();
ensure_schema_migrations_table($pdo);

$migrationFiles = glob(__DIR__ . '/../database/migrations/*.php') ?: [];
sort($migrationFiles);

$appliedVersions = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$appliedLookup = array_fill_keys($appliedVersions, true);

foreach ($migrationFiles as $migrationFile)
{
    $version = basename($migrationFile, '.php');

    if (isset($appliedLookup[$version]))
    {
        echo "skip {$version}\n";
        continue;
    }

    $migration = require $migrationFile;
    if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up']))
    {
        fwrite(STDERR, "Invalid migration file: {$migrationFile}\n");
        exit(1);
    }

    try
    {
        $migration['up']($pdo);

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
        $stmt->execute(['version' => $version]);

        echo "migrated {$version}\n";
    }
    catch (Throwable $e)
    {
        fwrite(STDERR, "Migration failed for {$version}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "migrations complete\n";
