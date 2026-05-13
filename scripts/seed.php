<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$pdo = db_connect();
ensure_schema_seeds_table($pdo);

$seedFiles = glob(__DIR__ . '/../database/seeders/*.php') ?: [];
sort($seedFiles);

$appliedVersions = $pdo->query('SELECT version FROM schema_seeds')->fetchAll(PDO::FETCH_COLUMN);
$appliedLookup = array_fill_keys($appliedVersions, true);

foreach ($seedFiles as $seedFile)
{
    $version = basename($seedFile, '.php');

    if (isset($appliedLookup[$version]))
    {
        echo "skip {$version}\n";
        continue;
    }

    $seeder = require $seedFile;
    if (!is_array($seeder) || !isset($seeder['run']) || !is_callable($seeder['run']))
    {
        fwrite(STDERR, "Invalid seeder file: {$seedFile}\n");
        exit(1);
    }

    try
    {
        $seeder['run']($pdo);

        $stmt = $pdo->prepare('INSERT INTO schema_seeds (version) VALUES (:version)');
        $stmt->execute(['version' => $version]);

        echo "seeded {$version}\n";
    }
    catch (Throwable $e)
    {
        fwrite(STDERR, "Seeder failed for {$version}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "seeding complete\n";
