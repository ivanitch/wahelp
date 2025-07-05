<?php

declare(strict_types=1);

use Wahelp\Logger;
use Wahelp\repositories\MailingRepository;
use Wahelp\services\ImportService;

# Autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

# Logger
$logger = new Logger('api.log');

# Helpers
require_once dirname(__DIR__) . '/config/functions.php';

# $_ENV
env_prepare();

if (env('APP_ENV') !== 'production') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

# Connection
try {
    $conn = \Wahelp\Connection::getInstance(
        require_once dirname(__DIR__) . '/config/database.php'
    );
    $logger->info("Успешное подключение к базе данных.");
} catch (PDOException $e) {
    $logger->error("Ошибка подключения к базе данных: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(
        ['error' => 'Database connection error: ' . $e->getMessage()]
    );

    exit();
}

# Headers
header('Content-Type: application/json');

# Router
#    -> Controller
#        -> Service
#            -> Repository
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];
switch ($requestUri) {
    case '/':
        if ($requestMethod === 'GET') {
            echo json_encode("Hello, World 👋");
        }
        break;
    case '/api/users/import':
        if ($requestMethod === 'POST') {
            try {
                $fileUploader = new ImportService($logger, $conn);
                $result       = $fileUploader->uploadUsersFromCsv($_FILES['csv_file'] ?? null);
                $logger->info("Пользователи успешно загружены из CSV. Результат: " . json_encode($result));
                echo json_encode(['message' => 'Users uploaded successfully', 'data' => $result]);
            } catch (Exception $e) {
                $logger->error("Ошибка при загрузке пользователей из CSV: " . $e->getMessage());
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            $logger->warning("Недопустимый метод запроса для /api/users/import: " . $requestMethod);
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/api/mailings/send':
        if ($requestMethod === 'POST') {
            $input     = json_decode(file_get_contents('php://input'), true);
            $mailingId = (int)($input['mailing_id'] ?? 0);

            try {
                $mailingRepository = new MailingRepository($logger, $conn);
                $result            = $mailingRepository->startMailingProcess($mailingId);
                $logger->info("Запущен/возобновлен процесс рассылки ID: $mailingId. Результат: " . json_encode($result));
                echo json_encode(['message' => 'Mailing process started/resumed', 'data' => $result]);
            } catch (InvalidArgumentException $e) {
                $logger->warning("Неверный ID рассылки для запуска: " . $e->getMessage());
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            } catch (Exception $e) {
                $logger->error("Ошибка при запуске/возобновлении рассылки ID {$mailingId}: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Failed to start/resume mailing: ' . $e->getMessage()]);
            }
        } else {
            $logger->warning("Недопустимый метод запроса для `/api/mailings/send`: " . $requestMethod);
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    default:
        $logger->warning("Эндпоинт не найден: $requestUri::$requestMethod.");
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
