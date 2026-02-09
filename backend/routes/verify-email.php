<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/api/verify-email', function (Request $req, Response $res) use ($makePdo) {
    try {
        // grabs data from the frontend
        $data = $req->getParsedBody();
        $email = trim((string)($data['email'] ?? ''));

        // user didn't enter an email (left prompt empty)
        if ($email === '') {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Email required']));
            return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $pdo = $makePdo();

        // searches the Users table for the provided email from frontend
        $check = $pdo->prepare("SELECT 1 FROM dbo.Users WHERE Email = :email");  
        $check->execute([':email' => $email]);

        // if user email was found in database returns 1 otherwise 0                           
        $existsInUsers = ((int)$check->fetchColumn() > 0);

        $payload = ['ok' => true, 'emailExists' => $existsInUsers];
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json');

    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
