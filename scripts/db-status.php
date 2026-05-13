<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

function table_exists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $stmt->execute(['table_name' => $tableName]);

    return (int)$stmt->fetchColumn() > 0;
}

$pdo = db_connect();

$migrationTableExists = table_exists($pdo, 'schema_migrations');
$seedTableExists = table_exists($pdo, 'schema_seeds');
$usersTableExists = table_exists($pdo, 'users');

echo "database status\n";
echo 'schema_migrations table: ' . ($migrationTableExists ? 'present' : 'missing') . "\n";
echo 'schema_seeds table: ' . ($seedTableExists ? 'present' : 'missing') . "\n";
echo 'users table: ' . ($usersTableExists ? 'present' : 'missing') . "\n";

if ($migrationTableExists)
{
    $migrationCount = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    echo "applied migrations: {$migrationCount}\n";
}

if ($seedTableExists)
{
    $seedCount = (int)$pdo->query('SELECT COUNT(*) FROM schema_seeds')->fetchColumn();
    echo "applied seeders: {$seedCount}\n";
}

if ($usersTableExists)
{
    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "users count: {$userCount}\n";
}
