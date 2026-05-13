<?php

return [
    'up' => function (PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password VARCHAR(255) NOT NULL,
                is_admin TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
];
