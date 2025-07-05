CREATE TABLE IF NOT EXISTS `users`
(
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `number` VARCHAR(20) UNIQUE NOT NULL COMMENT 'Номер',
    `name` VARCHAR(100) NOT NULL COMMENT 'Имя',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания'
) COMMENT 'Пользователи';

CREATE TABLE IF NOT EXISTS `mailings`
(
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `title`      VARCHAR(255) NOT NULL COMMENT 'Заголовок рассылки',
    `text`       TEXT         NOT NULL COMMENT 'Текст рассылки',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания рассылки',
    `status`     ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending' COMMENT 'Статус рассылки'
) COMMENT 'Рассылки';

CREATE TABLE IF NOT EXISTS `mailing_recipients`
(
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `mailing_id` INT NOT NULL COMMENT 'ID рассылки',
    `user_id`    INT NOT NULL COMMENT 'ID пользователя',
    `status`     ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `sent_at`    DATETIME NULL,
    FOREIGN KEY (`mailing_id`) REFERENCES `mailings` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    UNIQUE (`mailing_id`, `user_id`)
) COMMENT 'Связь пользователя и рассылки';

-- Рассылки
INSERT INTO `mailings` (`id`, `title`, `text`) VALUES (1, 'Новость #1', 'Новость #1 text');
INSERT INTO `mailings` (`id`,`title`, `text`) VALUES (2, 'Новость #2', 'Новость #2 text');
INSERT INTO `mailings` (`id`,`title`, `text`) VALUES (3, 'Новость #3', 'Новость #3 text');