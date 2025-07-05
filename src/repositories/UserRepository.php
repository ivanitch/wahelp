<?php

declare(strict_types=1);

namespace Wahelp\repositories;

use PDO;

final class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Создает нового пользователя или обновляет существующего, если номер телефона уже есть.
     *
     * @param string $number
     * @param string $name
     *
     * @return int
     */
    public function createOrUpdateUser(string $number, string $name): int
    {
        // Обновляем имя, если номер уже есть
        $sql = "INSERT INTO users (number, name) 
                VALUES (:number, :name)
                ON DUPLICATE KEY UPDATE name = VALUES(name)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':number', $number);
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Получает пользователя по ID
     *
     * @param int $userId
     *
     * @return mixed
     */
    public function getUserById(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Получает всех пользователей.
     *
     * @return array Массив ассоциативных массивов данных пользователей.
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `users`");
        return $stmt->fetchAll();
    }
}
