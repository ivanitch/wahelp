<?php

declare(strict_types=1);

// Develop
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/helpers.php';

env_prepare();

try {
    $conn = \Wahelp\Connection::getInstance(
        require_once dirname(__DIR__) . '/config/database.php'
    );

    $repository = new \Wahelp\UserRepository($conn);

    dump($repository->getAll());


} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(
        ['error' => 'Database connection error: ' . $e->getMessage()]
    );

    exit();
}
