<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load the .env data
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
if ($isDev) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$app = AppFactory::create();

// Database
$databaseSetup = require __DIR__ . '/../src/Database.php';
$makePdo = $databaseSetup();

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware($isDev, true, true);
$app->add(require __DIR__ . '/../middleware/SessionMiddleware.php');

require __DIR__ . '/../src/Helpers.php';

$routeLoader = require __DIR__ . '/../src/routes.php';
$routeLoader($app, $makePdo);

$app->run();