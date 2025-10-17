<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

// testing adding users 
// http://localhost:8080/add-user → inserts one user
// http://localhost:8080/users

ini_set('display_errors', '1');
error_reporting(E_ALL);

// IMPORTANT: vendor is one level up from /public
require __DIR__ . '/../vendor/autoload.php';

// Load the .env data
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// This allows requests from the frontend port to the backend port (if the ports are different)
$app->add(function (Request $request, $handler) {
    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $resp = new \Slim\Psr7\Response(200);
    } else {
        $resp = $handler->handle($request);
    }

    return $resp
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')  // frontend port
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});

$server   = $_ENV['DB_SERVER'];           // Docker-mapped or local
$database = $_ENV['DB_DATABASE'];         // name of the database
$user     = $_ENV['DB_USER'];             // db username
$pass     = $_ENV['DB_PASS'];             // db password
$dsn      = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1";

$makePdo = function () use ($dsn, $user, $pass): PDO {
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
    ]);
};

require __DIR__ . '/../routes/verify-email.php'; // used for checking if email exists

// Root
$app->get('/', function (Request $req, Response $res) {
    $res->getBody()->write("Slim is up ✅  Try GET /ping, /users, /add-user");
    return $res->withHeader('Content-Type', 'text/plain');
});

// Health check (DB + Users count)
$app->get('/ping', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $n = (int)$pdo->query("SELECT COUNT(*) FROM dbo.Users")->fetchColumn();
        $res->getBody()->write(json_encode(['ok' => true, 'users' => $n]));
        return $res->withHeader('Content-Type', 'application/json');
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// Insert one demo row (adjust table/columns if needed)
$app->get('/add-user', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $stmt = $pdo->prepare("
            INSERT INTO dbo.Users (Email, FirstName, LastName, Role)
            VALUES (:email, :fn, :ln, :role)
        ");
        $stmt->execute([
            ':email' => 'test+'.time().'@example.com',
            ':fn'    => 'Jeff',
            ':ln'    => 'Sardella',
            ':role'  => 'member'
        ]);

        $res->getBody()->write(json_encode(['ok' => true]));
        return $res->withHeader('Content-Type', 'application/json');
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// List users
$app->get('/users', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo  = $makePdo();
        $rows = $pdo->query("
            SELECT TOP (100) User_ID, Email, FirstName, LastName, Role, EmailVerified, Created, LastLogin
            FROM dbo.Users
            ORDER BY User_ID DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $res->getBody()->write(json_encode($rows));
        return $res->withHeader('Content-Type', 'application/json');
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

$app->run();