<?php

declare(strict_types=1);

# Develop
use Wahelp\repositories\MailingRepository;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

# Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

# Helpers
require_once dirname(__DIR__) . '/config/helpers.php';

# ENV
env_prepare();

# Connection
try {
    $conn = \Wahelp\Connection::getInstance(
        require_once dirname(__DIR__) . '/config/database.php'
    );
} catch (PDOException $e) {
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
                $fileUploader = new \Wahelp\services\FileUploaderService($conn);
                $result       = $fileUploader->uploadUsersFromCsv($_FILES['csv_file'] ?? null);
                echo json_encode(['message' => 'Users uploaded successfully', 'data' => $result]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/api/mailings/send':
        if ($requestMethod === 'POST') {
            $input     = json_decode(file_get_contents('php://input'), true);
            $mailingId = (int)($input['mailing_id'] ?? 0);

            try {
                $mailingRepository = new MailingRepository($conn);
                $result            = $mailingRepository->startMailingProcess($mailingId);
                echo json_encode(['message' => 'Mailing process started/resumed', 'data' => $result]);
            } catch (InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to start/resume mailing: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
