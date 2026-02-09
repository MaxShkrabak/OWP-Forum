<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

// Root
$app->get('/', function (Request $req, Response $res) {
    $res->getBody()->write("Slim is up  Try GET /ping, /users");
    return $res->withHeader('Content-Type', 'text/plain');
});

// Health check (DB + Users count)
$app->get('/ping', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();

        $n = (int)$pdo->query("SELECT COUNT(*) FROM dbo.Users")->fetchColumn();

        return json($res, ['ok' => true, 'users' => $n]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

// List users
$app->get('/users', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo  = $makePdo();

        $rows = $pdo->query("
            SELECT TOP (100) User_ID, Email, FirstName, LastName, RoleID, EmailVerified, Created, LastLogin
            FROM dbo.Users
            ORDER BY User_ID DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        return json($res, $rows);
    } catch (Throwable $e) {
        return json($res, ['error' => $e->getMessage()], 500);
    }
});