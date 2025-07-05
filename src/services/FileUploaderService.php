<?php

declare(strict_types=1);

namespace Wahelp\services;

use PDO;
use RuntimeException;
use SplFileObject;
use Throwable;
use Wahelp\repositories\UserRepository;

class FileUploaderService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Загружает пользователей из CSV-файла в базу данных.
     *
     * @param array|null $fileData
     *
     * @return int[]
     */
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

        try {
            $file = new SplFileObject($filePath, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
            $file->setCsvControl(',', '"', '\\'); // Разделитель, ограничитель, escape-символ

            // === Пропускаем заголовок, если он есть ===
            // $file->current(); // Прочитать первую строку (заголовок)
            // $file->next(); // Перейти к следующей строке

            $this->pdo->beginTransaction();

            $userRepository = new UserRepository($this->pdo);

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
        } catch (Throwable $e) {
            $this->pdo->rollBack();
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
