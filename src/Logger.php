<?php

declare(strict_types=1);

namespace Wahelp;

class Logger
{
    private string $logFilePath;
    private string $dateFormat = 'Y-m-d H:i:s';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const DEBUG = 'DEBUG';

    public function __construct(string $logFileName = 'app.log')
    {
        $logDirectory = dirname(__DIR__) . '/logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }
        $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $logFileName;
    }

    /**
     * Записывает сообщение в лог-файл.
     *
     * @param string $message Сообщение для логирования.
     * @param string $level Уровень логирования (INFO, WARNING, ERROR, DEBUG).
     */
    public function log(string $message, string $level = self::INFO): void
    {
        $timestamp = date($this->dateFormat);
        $logEntry  = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message): void
    {
        $this->log($message, self::INFO);
    }

    public function warning(string $message): void
    {
        $this->log($message, self::WARNING);
    }

    public function error(string $message): void
    {
        $this->log($message, self::ERROR);
    }

    public function debug(string $message): void
    {
        $this->log($message, self::DEBUG);
    }
}
