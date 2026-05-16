<?php

return [
    'up' => function (PDO $pdo): void
    {
        $pdo->exec(
            "ALTER TABLE tickets
             MODIFY status ENUM('new', 'open', 'in_progress', 'waiting_on_user', 'resolved', 'closed') NOT NULL DEFAULT 'new'"
        );
    },
];
