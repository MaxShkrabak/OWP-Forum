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

// Middleware
$app->add(require __DIR__ . '/../middleware/cors.php');
$app->add(require __DIR__ . '/../middleware/session-auth.php');

// Routes
require __DIR__ . '/../routes/verify-email.php';      // Checks if email exists
require __DIR__ . '/../routes/password-auth.php';     // Password authentication
require __DIR__ . '/../routes/register-new-user.php'; // Registering new user
require __DIR__ . '/../routes/logout.php';            // Logs user out and clears session
require __DIR__ . '/../routes/me.php';                // User details for auth
require __DIR__ . '/../routes/avatar.php';            // Store avatar in database
require __DIR__ . '/../routes/create-post.php';      // Create a new forum post
require __DIR__ . '/../routes/upload-image.php';     // Image upload handler  

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