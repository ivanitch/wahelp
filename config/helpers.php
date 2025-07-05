<?php

/**
 * Читает $_ENV и $_SERVER
 */
if (!function_exists('env')) {
    function env(?string $key = null, $default = null)
    {
        if (!$key) return $_ENV;

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        } elseif (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }
}

/**
 * Читает файл .env
 */
if (!function_exists('env_prepare')) {
    function env_prepare()
    {
        $env = dirname(__DIR__) . '/.env';
        if (!file_exists($env)) {
            throw new RuntimeException("Unable to load environment file: " . $env);
        }

        $lines = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Удаляем BOM, если присутствует (для файлов, сохраненных как UTF-8 с BOM)
            $line = trim($line, "\xEF\xBB\xBF");

            // Проверка, что строка не комментарий и содержит знак равенства
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }
}
