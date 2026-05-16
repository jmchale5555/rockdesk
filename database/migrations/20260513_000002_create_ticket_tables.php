<?php

return [
    'up' => function (PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tickets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_number VARCHAR(32) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                assigned_to BIGINT UNSIGNED NULL,
                subject VARCHAR(190) NOT NULL,
                body TEXT NOT NULL,
                status ENUM('new', 'open', 'in_progress', 'waiting_on_user', 'resolved', 'closed') NOT NULL DEFAULT 'new',
                priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                resolved_at DATETIME NULL DEFAULT NULL,
                closed_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_tickets_ticket_number (ticket_number),
                KEY idx_tickets_user_id (user_id),
                KEY idx_tickets_assigned_to (assigned_to),
                KEY idx_tickets_status (status),
                KEY idx_tickets_priority (priority),
                KEY idx_tickets_created_at (created_at),
                KEY idx_tickets_updated_at (updated_at),
                CONSTRAINT fk_tickets_user_id FOREIGN KEY (user_id) REFERENCES users (id),
                CONSTRAINT fk_tickets_assigned_to FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ticket_comments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                is_internal TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_ticket_comments_ticket_id (ticket_id),
                KEY idx_ticket_comments_user_id (user_id),
                KEY idx_ticket_comments_created_at (created_at),
                CONSTRAINT fk_ticket_comments_ticket_id FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
                CONSTRAINT fk_ticket_comments_user_id FOREIGN KEY (user_id) REFERENCES users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ticket_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(64) NOT NULL,
                old_value VARCHAR(190) NULL,
                new_value VARCHAR(190) NULL,
                body TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ticket_events_ticket_id (ticket_id),
                KEY idx_ticket_events_user_id (user_id),
                KEY idx_ticket_events_event_type (event_type),
                KEY idx_ticket_events_created_at (created_at),
                CONSTRAINT fk_ticket_events_ticket_id FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
                CONSTRAINT fk_ticket_events_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
];
