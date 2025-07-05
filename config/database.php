<?php

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }
}

// Загружаем переменные окружения -> phpdotenv (@see https://github.com/vlucas/phpdotenv
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Удаляем BOM, если присутствует (для файлов, сохраненных как UTF-8 с BOM)
        $line = trim($line, "\xEF\xBB\xBF");

        // Проверяем, что строка не комментарий и содержит знак равенства
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);

            // Добавляем проверку, что ключ начинается с 'DB_'
            if (str_starts_with(trim($key), 'DB_')) { // Используем str_starts_with для PHP 8+, для 7.4 можно использовать strpos === 0
                $key = trim($key);
                $value = trim($value);
                $_ENV[$key] = $_SERVER[$key] = $value; // Устанавливаем и в $_ENV, и в $_SERVER
            }
        }
    }
}


return [
    'host' => $_ENV['DB_HOST'] ?? 'mysql',
    'database' => $_ENV['DB_NAME'] ?? 'your_database_name',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? 'password',
    'charset' => 'utf8mb4',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306)
];