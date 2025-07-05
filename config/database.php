<?php

return [
    'host'     => env('MYSQL_HOST') ?? 'localhost',
    'database' => env('MYSQL_DATABASE') ?? 'your_database_name',
    'username' => env('MYSQL_ROOT_USER') ?? 'root',
    'password' => env('MYSQL_ROOT_PASSWORD') ?? 'root',
    'charset'  => env('DB_CHARSET', 'utf8mb4'),
    'port'     => (int)(env('DB_PORT') ?? 3306)
];