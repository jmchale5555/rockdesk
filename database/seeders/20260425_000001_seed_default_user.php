<?php

return [
    'run' => function (PDO $pdo): void
    {
        $passwordHash = password_hash('password', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, is_admin, created_at)
             VALUES (:name, :email, :password, :is_admin, NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                password = VALUES(password),
                is_admin = VALUES(is_admin),
                updated_at = NOW()'
        );

        $stmt->execute([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => $passwordHash,
            'is_admin' => 1,
        ]);
    },
];
