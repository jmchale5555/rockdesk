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
            'table_name' => 'tickets',
            'column_name' => 'email_token',
        ]);

        if ((int)$stmt->fetchColumn() === 0)
        {
            $pdo->exec('ALTER TABLE tickets ADD COLUMN email_token VARCHAR(64) NULL AFTER ticket_number');
            $pdo->exec('CREATE UNIQUE INDEX tickets_email_token_unique ON tickets (email_token)');
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_email_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id BIGINT UNSIGNED NOT NULL,
                message_id VARCHAR(255) NOT NULL,
                email_type VARCHAR(80) NOT NULL,
                recipients TEXT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY ticket_email_messages_message_id_unique (message_id),
                KEY ticket_email_messages_ticket_id_index (ticket_id),
                CONSTRAINT ticket_email_messages_ticket_id_foreign
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    },
];
