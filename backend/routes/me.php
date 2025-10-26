<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/me', function(Request $req, Response $res) use ($makePdo) {
    
    // Grab logged-in user's details from database
    $userId = $req->getAttribute('user_id');
    $pdo = $makePdo();
    $stmt = $pdo->prepare('SELECT User_ID, Email, FirstName, LastName FROM dbo.Users WHERE User_ID = :uid');
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $res->getBody()->write(json_encode(['ok' => true, 'user' => $user]));
    return $res->withHeader('Content-Type', 'application/json');
});
