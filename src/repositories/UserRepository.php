<?php

declare(strict_types=1);

namespace Wahelp\repositories;

use PDO;
use PDOException;
use Wahelp\Logger;

final class UserRepository
{
    private Logger $logger;
    private PDO $pdo;

    public function __construct(Logger $logger, PDO $pdo)
    {
        $this->logger = $logger;
        $this->pdo = $pdo;
    }

    public function createOrUpdateUser(string $number, string $name): int
    {
        try {
            $sql = "INSERT INTO users (number, name) 
                VALUES (:number, :name)
                ON DUPLICATE KEY UPDATE name = VALUES(name)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':number', $number);
            $stmt->bindParam(':name', $name);
            $stmt->execute();

            $this->logger->debug("Пользователь $name ($number) успешно создан/обновлен.");
        } catch (PDOException $e) {
            $this->logger->error("Ошибка БД при создании/обновлении пользователя $number: " . $e->getMessage());
            throw $e;
        }

        return (int)$this->pdo->lastInsertId();
    }

    public function getUserById(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `users`");
        return $stmt->fetchAll();
    }
}
