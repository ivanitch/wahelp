<?php

declare(strict_types=1);

namespace Wahelp;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function getInstance($config): PDO
    {
        if (self::$instance === null) {
            $dsn = "mysql:host={$config['host']}"
                . ";port={$config['port']}"
                . ";dbname={$config['database']}"
                . ";charset={$config['charset']}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $options
                );
            } catch (PDOException $e) {
                // @todo: getMessage() -> log
                throw new PDOException(
                    "Ошибка подключения к базе данных: " . $e->getMessage(),
                    (int)$e->getCode()
                );
            }
        }

        return self::$instance;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }
}
