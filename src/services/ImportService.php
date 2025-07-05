<?php

declare(strict_types=1);

namespace Wahelp\services;

use PDO;
use RuntimeException;
use SplFileObject;
use Throwable;
use Wahelp\Logger;
use Wahelp\repositories\UserRepository;

class ImportService
{
    private Logger $logger;
    private PDO $pdo;

    public function __construct(Logger $logger, PDO $pdo)
    {
        $this->logger = $logger;
        $this->pdo    = $pdo;
    }

    public function uploadUsersFromCsv(?array $fileData): array
    {
        if (!isset($fileData) || $fileData['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Файл не был загружен или произошла ошибка загрузки.');
        }

        if ($fileData['type'] !== 'text/csv' && pathinfo($fileData['name'], PATHINFO_EXTENSION) !== 'csv') {
            throw new RuntimeException('Неверный формат файла. Ожидается CSV.');
        }

        $filePath       = $fileData['tmp_name'];
        $processedCount = 0;
        $skippedCount   = 0;

        $this->logger->info("Начата загрузка пользователей из CSV-файла.");

        try {
            $file = new SplFileObject($filePath, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
            $file->setCsvControl(',', '"', '\\'); // Разделитель, ограничитель, escape-символ

            // === Пропускаем заголовок, если он есть ===
            // $file->current(); // Прочитать первую строку (заголовок)
            // $file->next(); // Перейти к следующей строке

            $this->pdo->beginTransaction();

            $userRepository = new UserRepository($this->logger, $this->pdo);

            foreach ($file as $row) {
                if (!is_array($row) || count($row) < 2) {
                    $skippedCount++;
                    continue;
                }

                $number = trim($row[0]);
                $name   = trim($row[1]);

                if (empty($number) || empty($name)) {
                    $skippedCount++;
                    continue;
                }

                $userRepository->createOrUpdateUser($number, $name);
                $processedCount++;
            }

            $this->pdo->commit();
            $this->logger->info("Успешно загружено $processedCount записей из CSV. Пропущено: $skippedCount.");
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error("Ошибка обработки CSV-файла или записи в БД: " . $e->getMessage());
            throw new RuntimeException('Ошибка обработки CSV-файла или записи в БД: ' . $e->getMessage());
        } finally {
            if (isset($file)) unset($file);
        }

        return [
            'processed_records' => $processedCount,
            'skipped_records'   => $skippedCount
        ];
    }
}
