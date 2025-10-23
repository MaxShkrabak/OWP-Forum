<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/api/login', function(Request $req, Response $res) use ($makePdo) {
    $data = $req->getParsedBody() ?? [];
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $otp = trim((string)($data['otp'] ?? ''));

    if($email =='' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Valid email required']));
        return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    if ($otp === '') {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Password required']));
        return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $pdo = $makePdo();
    $stmt = $pdo->prepare('SELECT User_ID, EmailVerified FROM dbo.Users WHERE Email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user){
       $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Invalid credentials']));
       return $res->withHeader('Content-Type', 'application/json');
    }

    $expectedOtp = $_ENV['GLOBAL_OTP'] ?? '';

    if(!hash_equals($expectedOtp, $otp)) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Invalid credentials']));
        return $res->withHeader('Content-Type', 'application/json');
    }

    // User input matched with the global OTP
    if((int)($user['EmailVerified'] ?? 0) === 0) {
        $pdo->prepare('UPDATE dbo.Users SET EmailVerified = 1, LastLogin=SYSDATETIME() WHERE User_ID = :uid')
            ->execute([':uid' => $user['User_ID']]);
    } else {
        $pdo->prepare('UPDATE dbo.Users SET LastLogin=SYSDATETIME() WHERE User_ID = :uid')
            ->execute([':uid' => $user['User_ID']]);
    }

    $res->getBody()->write(json_encode(['ok' => true]));
    return $res->withHeader('Content-Type', 'application/json');
});