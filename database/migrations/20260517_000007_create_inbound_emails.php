<?php

return [
    'up' => function (PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS inbound_emails (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                message_id VARCHAR(255) NULL,
                mailbox_uid VARCHAR(255) NULL,
                from_email VARCHAR(190) NOT NULL,
                from_name VARCHAR(190) NULL,
                subject VARCHAR(255) NULL,
                received_at DATETIME NULL,
                processed_at DATETIME NULL,
                status ENUM('pending', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'pending',
                error TEXT NULL,
                raw_path VARCHAR(255) NULL,
                ticket_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY inbound_emails_message_id_unique (message_id),
                KEY inbound_emails_mailbox_uid_index (mailbox_uid),
                KEY inbound_emails_status_index (status),
                KEY inbound_emails_from_email_index (from_email),
                KEY inbound_emails_ticket_id_index (ticket_id),
                CONSTRAINT inbound_emails_ticket_id_foreign
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
];
