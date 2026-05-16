<?php

return [
    'up' => function (PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => 'ticket_attachments',
            'column_name' => 'is_inline',
        ]);

        if ((int)$stmt->fetchColumn() === 0)
        {
            $pdo->exec('ALTER TABLE ticket_attachments ADD COLUMN is_inline TINYINT(1) NOT NULL DEFAULT 0 AFTER file_size');
        }
    },
];
