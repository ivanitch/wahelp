<?php

declare(strict_types=1);

namespace Wahelp;


use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Получает всех пользователей.
     *
     * @return array Массив ассоциативных массивов данных пользователей.
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users");
        return $stmt->fetchAll();
    }
}