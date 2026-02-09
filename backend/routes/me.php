<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/me', function(Request $req, Response $res) use ($makePdo) {
    try {
        // Verify auth
        $userId = $req->getAttribute('user_id');

        if ($userId === null) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not Authenticated']));
            return $res->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
        $pdo = $makePdo();
        
        // Grab logged-in user's details from database
        $stmt = $pdo->prepare('SELECT User_ID, Email, FirstName, LastName, Avatar FROM dbo.Users WHERE User_ID = :uid');
        $stmt->execute([':uid' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $res->getBody()->write(json_encode(['ok' => true, 'user' => $user]));
        return $res->withHeader('Content-Type', 'application/json');
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});