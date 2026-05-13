<?php

return [
    'up' => function (PDO $pdo): void
    {
        $indexExists = static function (PDO $pdo, string $indexName): bool
        {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS count
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND INDEX_NAME = :index_name'
            );
            $stmt->execute([
                'table_name' => 'users',
                'index_name' => $indexName,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        };

        $constraintExists = static function (PDO $pdo, string $constraintName): bool
        {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS count
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND CONSTRAINT_NAME = :constraint_name'
            );
            $stmt->execute([
                'table_name' => 'users',
                'constraint_name' => $constraintName,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        };

        if (!$indexExists($pdo, 'idx_users_is_admin'))
        {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_is_admin (is_admin)');
        }

        if (!$indexExists($pdo, 'idx_users_created_at'))
        {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_created_at (created_at)');
        }

        if (!$constraintExists($pdo, 'chk_users_is_admin'))
        {
            $pdo->exec('ALTER TABLE users ADD CONSTRAINT chk_users_is_admin CHECK (is_admin IN (0, 1))');
        }
    },
];
