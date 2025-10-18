<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/api/password-auth', function(Request $req, Response $res)) use ($makePdo) {
    $data = $req->getParsedBody() ?? [];
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');

    if($email === ''|| !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Valid email required']));
        return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    if ($password === '') {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Password required']));
        return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try{
        $pdo = $makePdo();
        $stmt = $pdo->prepare('SELECT password_hash FROM dbo.Users WHERE Email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $ok = $row && password_verify($password, $row['password_hash'] ?? '');
        $res->getBody()->write(json_encode(['ok' => $ok, 'error' => $ok ? null : 'Invalid credentials']));
        return $res->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Database error']));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }