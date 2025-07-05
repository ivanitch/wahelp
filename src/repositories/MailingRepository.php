<?php

declare(strict_types=1);

namespace Wahelp\repositories;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;


class MailingRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Создает новую рассылку и добавляет всех текущих пользователей в очередь рассылки.
     *
     * @param string $title Название рассылки.
     * @param string $text Текст рассылки.
     * @return int ID новой рассылки.
     * @throws InvalidArgumentException Если заголовок или текст пусты.
     * @throws Exception В случае ошибки БД или отсутствия пользователей.
     */
    public function createMailing(string $title, string $text): int
    {
        if (empty(trim($title)) || empty(trim($text))) {
            throw new InvalidArgumentException('Название и текст рассылки не могут быть пустыми.');
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Создаем запись о рассылке
            $stmt = $this->pdo->prepare("INSERT INTO mailings (title, text, status) VALUES (:title, :text, 'pending')");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':text', $text);
            $stmt->execute();
            $mailingId = (int)$this->pdo->lastInsertId();

            if ($mailingId === 0) {
                throw new Exception("Не удалось создать рассылку, ID вернулся 0.");
            }

            // 2. Получаем всех текущих пользователей
            $userRepository = new UserRepository($this->pdo);
            $allUsers = $userRepository->getAllUsers();

            if (empty($allUsers)) {
                // Если пользователей нет, все равно создаем рассылку, но без получателей.
                // Возможно, вы хотите выбросить исключение здесь, если рассылка без получателей не имеет смысла.
                // В рамках ТЗ, пока оставляем так, но добавляем сообщение.
                error_log("Нет пользователей для добавления в рассылку ID: " . $mailingId); // Логируем событие
                $this->pdo->commit();
                return $mailingId;
            }

            // 3. Добавляем всех пользователей в таблицу mailing_recipients со статусом 'pending'
            // Используем UNION ALL и VALUES для массовой вставки - более эффективно, чем цикл с отдельными INSERT
            // Это значительно улучшит производительность для большого количества пользователей.
            $values = [];
            $placeholders = [];
            $i = 0;
            foreach ($allUsers as $user) {
                // Замените на id из результата getUserById, если вы используете phone_number как уникальный ключ для получения ID
                // Если users.id - это AUTO_INCREMENT и вы его используете, то все верно.
                $values[] = $mailingId;
                $values[] = $user['id']; // ID пользователя из таблицы users
                $placeholders[] = "(?, ?, 'pending')";
                $i++;
            }

            if (!empty($placeholders)) {
                $insertRecipientSql = "INSERT INTO mailing_recipients (mailing_id, user_id, status) VALUES " . implode(', ', $placeholders);
                $stmtRecipient = $this->pdo->prepare($insertRecipientSql);
                $stmtRecipient->execute($values);
            }

            $this->pdo->commit();
            return $mailingId;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // Детализируем ошибку для отладки
            throw new Exception("Ошибка БД при создании рассылки и добавлении получателей: " . $e->getMessage() . " Код: " . $e->getCode());
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Общая ошибка при создании рассылки и добавлении получателей: " . $e->getMessage());
        }
    }

    /**
     * Запускает или возобновляет процесс "отправки" рассылки.
     *
     * @param int $mailingId ID рассылки.
     * @return array Статистика отправки.
     * @throws InvalidArgumentException Если рассылка не найдена.
     * @throws Exception В случае ошибки.
     */
    public function startMailingProcess(int $mailingId): array
    {
        // 1. Проверяем существование рассылки и её статус
        $mailing = $this->getMailingById($mailingId);
        if (!$mailing) {
            throw new InvalidArgumentException("Рассылка с ID {$mailingId} не найдена.");
        }

        // Если рассылка уже завершена, не запускаем повторно
        if ($mailing['status'] === 'completed') {
            return ['message' => 'Рассылка уже завершена.', 'sent_count' => $this->getSentRecipientsCount($mailingId), 'total_recipients' => $this->getTotalRecipientsCount($mailingId)];
        }

        // 2. Устанавливаем статус рассылки 'in_progress'
        // Обернем это в try-catch для надежности, чтобы статус не сбросился
        try {
            $this->updateMailingStatus($mailingId, 'in_progress');
        } catch (PDOException $e) {
            error_log("Не удалось обновить статус рассылки ID {$mailingId} на 'in_progress': " . $e->getMessage());
            // Возможно, стоит выбросить исключение, если не удалось установить статус
        }

        $recipientsProcessedInThisRun = 0; // Количество фактически обработанных в текущем запуске

        // 3. Выбираем всех получателей для данной рассылки со статусом 'pending'
        // Извлекаем только необходимые данные для отправки, чтобы уменьшить объем данных.
        $sql = "SELECT mr.id as recipient_id, u.phone_number, u.name
                FROM mailing_recipients mr
                JOIN users u ON mr.user_id = u.id
                WHERE mr.mailing_id = :mailing_id AND mr.status = 'pending'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();

        // 4. Имитация отправки и обновление статуса
        while ($recipient = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Здесь происходит фиктивная "отправка"
            $this->fictionalSendMethod($recipient['phone_number'], $mailing['title'], $mailing['text']);

            // Обновляем статус получателя на 'sent'
            try {
                $this->updateRecipientStatus($recipient['recipient_id'], 'sent');
                $recipientsProcessedInThisRun++;
            } catch (PDOException $e) {
                error_log("Ошибка при обновлении статуса получателя ID {$recipient['recipient_id']}: " . $e->getMessage());
                // Можно добавить логику для пометки как 'failed' вместо 'sent', если отправка не удалась
            }

            // Если рассылка очень большая, можно добавить ограничение по времени выполнения или количеству итераций
            // set_time_limit(0) в начале скрипта, если это консольный скрипт
            // или использовать таймаут, если это HTTP-запрос (но это не очень хорошо для длинных операций)
        }

        // 5. Проверяем, остались ли необработанные получатели
        $remainingPending = $this->getPendingRecipientsCount($mailingId);
        $newMailingStatus = 'in_progress'; // По умолчанию оставляем in_progress

        if ($remainingPending === 0) {
            $newMailingStatus = 'completed'; // Если все отправлено
        }

        try {
            $this->updateMailingStatus($mailingId, $newMailingStatus);
        } catch (PDOException $e) {
            error_log("Не удалось обновить финальный статус рассылки ID {$mailingId} на '{$newMailingStatus}': " . $e->getMessage());
        }


        return [
            'mailing_id' => $mailingId,
            'status' => $this->getMailingById($mailingId)['status'], // Получаем актуальный статус
            'processed_in_this_run' => $recipientsProcessedInThisRun,
            'total_sent_for_mailing' => $this->getSentRecipientsCount($mailingId),
            'remaining_to_send' => $remainingPending,
            'total_recipients' => $this->getTotalRecipientsCount($mailingId)
        ];
    }

    /**
     * Фиктивный метод отправки рассылки.
     * @param string $phoneNumber
     * @param string $title
     * @param string $text
     */
    private function fictionalSendMethod(string $phoneNumber, string $title, string $text): void
    {
        // Здесь могла бы быть реальная логика отправки через сторонний сервис (SMS, Email API и т.д.)
        // file_put_contents(__DIR__ . '/../logs/mailing_log.txt', "Отправлено {$title} для {$phoneNumber}: {$text}\n", FILE_APPEND);
        // Для тестового задания просто имитируем успех.
        // error_log("Симуляция отправки: {$title} для {$phoneNumber}"); // Можно использовать error_log для вывода в логи контейнера
    }

    /**
     * Обновляет статус рассылки.
     * @param int $mailingId
     * @param string $status
     * @throws PDOException
     */
    private function updateMailingStatus(int $mailingId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE mailings SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Обновляет статус конкретного получателя рассылки.
     * @param int $recipientId ID записи в mailing_recipients.
     * @param string $status
     * @throws PDOException
     */
    private function updateRecipientStatus(int $recipientId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE mailing_recipients SET status = :status, sent_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $recipientId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Получает рассылку по ID.
     * @param int $mailingId
     * @return array|false
     */
    public function getMailingById(int $mailingId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mailings WHERE id = :id");
        $stmt->bindParam(':id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получает количество получателей в статусе 'pending' для данной рассылки.
     * @param int $mailingId
     * @return int
     */
    private function getPendingRecipientsCount(int $mailingId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :mailing_id AND status = 'pending'");
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Получает количество получателей в статусе 'sent' для данной рассылки.
     * @param int $mailingId
     * @return int
     */
    private function getSentRecipientsCount(int $mailingId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :mailing_id AND status = 'sent'");
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Получает общее количество получателей для данной рассылки.
     * @param int $mailingId
     * @return int
     */
    private function getTotalRecipientsCount(int $mailingId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :mailing_id");
        $stmt->bindParam(':mailing_id', $mailingId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
