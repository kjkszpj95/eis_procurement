<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') .
             ';dbname=' . ($_ENV['DB_NAME'] ?? 'legal_entities') .
             ';charset=' . ($_ENV['DB_CHARSET'] ?? 'utf8mb4'),
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];
