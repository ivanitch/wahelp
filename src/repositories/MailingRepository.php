<?php

declare(strict_types=1);

namespace Wahelp\repositories;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use Wahelp\Logger;

final class MailingRepository
{
    private Logger $logger;
    private PDO $pdo;

    public function __construct(Logger $logger, PDO $pdo)
    {
        $this->logger = $logger;
        $this->pdo    = $pdo;
    }

    public function createMailing(string $title, string $text): int
    {
        if (empty(trim($title)) || empty(trim($text))) {
            $this->logger->warning("Попытка создания рассылки с пустым названием или текстом.");
            throw new InvalidArgumentException('Название и текст рассылки не могут быть пустыми.');
        }

        $this->pdo->beginTransaction();

        $mailingId = 0;

        try {
            // 1. Создаем запись о рассылке
            $stmt = $this->pdo->prepare("INSERT INTO mailings (title, text, status) VALUES (:title, :text, 'pending')");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':text', $text);
            $stmt->execute();
            $mailingId = (int)$this->pdo->lastInsertId();

            if ($mailingId === 0) {
                $this->logger->error("Не удалось создать рассылку, lastInsertId вернул 0 для рассылки с названием '{$title}'.");
                throw new Exception("Не удалось создать рассылку.");
            }
            $this->logger->info("Создана новая рассылка ID: {$mailingId} с названием '{$title}'.");

            $userRepository = new UserRepository($this->logger, $this->pdo);
            $allUsers       = $userRepository->getAll();

            if (empty($allUsers)) {
                $this->logger->warning("Нет пользователей для добавления в рассылку ID: {$mailingId}. Рассылка создана, но без получателей.");
                $this->pdo->commit();
                return $mailingId;
            }

            $values       = [];
            $placeholders = [];

            foreach ($allUsers as $user) {
                $values[]       = $mailingId;
                $values[]       = $user['id'];
                $placeholders[] = "(?, ?, 'pending')";
            }

            if (!empty($placeholders)) {
                $insertRecipientSql = "INSERT INTO mailing_recipients (mailing_id, user_id, status) VALUES " . implode(', ', $placeholders);
                $stmtRecipient      = $this->pdo->prepare($insertRecipientSql);
                $stmtRecipient->execute($values);
                $this->logger->info("Добавлено " . count($allUsers) . " получателей в рассылку ID: {$mailingId}.");
            }

            $this->pdo->commit();
            $this->logger->info("Транзакция для рассылки ID: {$mailingId} успешно завершена.");
            return $mailingId;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $this->logger->error("Ошибка БД при создании рассылки ID: " . ($mailingId ?? 'N/A') . ". Сообщение: " . $e->getMessage() . " Код: " . $e->getCode());
            throw new Exception("Ошибка БД при создании рассылки: " . $e->getMessage(), (int)$e->getCode());
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Общая ошибка при создании рассылки ID: " . ($mailingId ?? 'N/A') . ". Сообщение: " . $e->getMessage());
            throw new Exception("Общая ошибка при создании рассылки: " . $e->getMessage());
        }
    }

    public function startMailingProcess(int $mailingId): array
    {
        $mailing = $this->getMailingById($mailingId);
        if (!$mailing) {
            $this->logger->warning("Попытка запуска несуществующей рассылки с ID: {$mailingId}.");
            throw new InvalidArgumentException("Рассылка с ID {$mailingId} не найдена.");
        }

        $this->logger->info("Попытка запуска/возобновления рассылки ID: {$mailingId}. Текущий статус: {$mailing['status']}.");

        if ($mailing['status'] === 'completed') {
            $totalSent       = $this->getSentRecipientsCount($mailingId);
            $totalRecipients = $this->getTotalRecipientsCount($mailingId);
            $this->logger->info("Рассылка ID: {$mailingId} уже завершена. Отправлено: {$totalSent}/{$totalRecipients}. Отправка не требуется.");
            return [
                'message'          => 'Рассылка уже завершена.',
                'sent_count'       => $totalSent,
                'total_recipients' => $totalRecipients
            ];
        }

        try {
            $this->updateMailingStatus($mailingId, 'in_progress');
            $this->logger->debug("Статус рассылки ID: $mailingId изменен на 'in_progress'.");
        } catch (PDOException $e) {
            $this->logger->error("Не удалось обновить статус рассылки ID $mailingId на 'in_progress': " . $e->getMessage());
            throw new Exception("Не удалось изменить статус рассылки на 'in_progress'.", 0, $e); // Оборачиваем оригинальное исключение
        }

        $recipientsProcessedInThisRun = 0;

        $sql  = "SELECT mr.id as recipient_id, u.phone_number, u.name
                FROM mailing_recipients mr
                JOIN users u ON mr.user_id = u.id
                WHERE mr.mailing_id = :mailing_id AND mr.status = 'pending'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();

        while ($recipient = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->logger->debug("Попытка отправки сообщения для получателя ID: {$recipient['recipient_id']} ({$recipient['name']} - {$recipient['phone_number']}) в рассылке ID: {$mailingId}.");
            $this->fictionalSendMethod($recipient['phone_number'], $mailing['title'], $mailing['text']);

            try {
                $this->updateRecipientStatus($recipient['recipient_id'], 'sent');
                $recipientsProcessedInThisRun++;
                $this->logger->debug("Получатель ID: {$recipient['recipient_id']} успешно помечен как 'sent'.");
            } catch (PDOException $e) {
                $this->logger->error("Ошибка при обновлении статуса получателя ID {$recipient['recipient_id']} для рассылки ID {$mailingId}: " . $e->getMessage());
                // В зависимости от логики, здесь можно пометить статус как 'failed' вместо 'sent'
                // $this->updateRecipientStatus($recipient['recipient_id'], 'failed');
            }
        }

        $remainingPending = $this->getPendingRecipientsCount($mailingId);
        $newMailingStatus = 'in_progress';

        if ($remainingPending === 0) {
            $newMailingStatus = 'completed';
            $this->logger->info("Рассылка ID: {$mailingId} успешно завершена. Все получатели обработаны.");
        } else {
            $this->logger->warning("Рассылка ID: {$mailingId} не завершена. Осталось {$remainingPending} получателей. Статус остается 'in_progress'.");
        }

        try {
            $this->updateMailingStatus($mailingId, $newMailingStatus);
        } catch (PDOException $e) {
            $this->logger->error("Не удалось обновить финальный статус рассылки ID {$mailingId} на '{$newMailingStatus}': " . $e->getMessage());
            throw new Exception("Не удалось обновить финальный статус рассылки.", 0, $e);
        }

        $totalSentForMailing    = $this->getSentRecipientsCount($mailingId);
        $totalRecipientsOverall = $this->getTotalRecipientsCount($mailingId);

        return [
            'mailing_id'             => $mailingId,
            'status'                 => $this->getMailingById($mailingId)['status'],
            'processed_in_this_run'  => $recipientsProcessedInThisRun,
            'total_sent_for_mailing' => $totalSentForMailing,
            'remaining_to_send'      => $remainingPending,
            'total_recipients'       => $totalRecipientsOverall
        ];
    }

    private function fictionalSendMethod(string $number, string $title, string $text): void
    {
        // Code ...

        $this->logger->debug("Фиктивная отправка: '$title' для номера $number. Текст: '{$text}'");
    }

    private function updateMailingStatus(int $mailingId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE mailings SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function updateRecipientStatus(int $recipientId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE mailing_recipients SET status = :status, sent_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $recipientId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getMailingById(int $mailingId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mailings WHERE id = :id");
        $stmt->bindParam(':id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getPendingRecipientsCount(int $mailingId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :mailing_id AND status = 'pending'");
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function getSentRecipientsCount(int $mailingId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :mailing_id AND status = 'sent'");
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function getTotalRecipientsCount(int $mailingId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :mailing_id");
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
