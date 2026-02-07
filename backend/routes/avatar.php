<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/api/user/avatar', function (Request $req, Response $res) use ($makePdo) {
    // Store the users selected icon file name in the database
    try {
        $userId = $req->getAttribute('user_id');

        $data = $req->getParsedBody();
        $newAvatarPath = trim((string)($data['avatar'] ?? ''));

        // Check its not null
        if ($newAvatarPath === '') {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'No avatar provided']));
            return $res->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $avatarFilename = basename($newAvatarPath);

        $pdo = $makePdo();

        // Store icon type in database
        $updateStmt = $pdo->prepare("
            UPDATE dbo.Users 
            SET Avatar = :avatar 
            WHERE User_ID = :uid
        ");
        $updateStmt->execute([
            ':avatar' => $avatarFilename,
            ':uid'    => $userId
        ]);

        // Success
        $res->getBody()->write(json_encode([
            'ok' => true, 
            'message' => 'Avatar updated successfully',
            'newAvatar' => $avatarFilename
        ]));
        return $res->withHeader('Content-Type', 'application/json');
    
    } catch (Throwable $e) {
        // Failed to save icon
        $res->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});