<?php

return [
    'up' => function (PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ticket_attachments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(190) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                is_inline TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ticket_attachments_stored_name (stored_name),
                KEY idx_ticket_attachments_ticket_id (ticket_id),
                KEY idx_ticket_attachments_user_id (user_id),
                KEY idx_ticket_attachments_created_at (created_at),
                CONSTRAINT fk_ticket_attachments_ticket_id FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
                CONSTRAINT fk_ticket_attachments_user_id FOREIGN KEY (user_id) REFERENCES users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
];
