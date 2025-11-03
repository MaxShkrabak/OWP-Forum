<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/categories', function(Request $req, Response $res) use ($makePdo) {
   try {
        $pdo = $makePdo();
        $userId = (int)($req->getAttribute('user_id') ?? 0);
        if ($userId <= 0) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Unauthorized']));
            return $res->withStatus(401)->withHeader('Content-Type', 'application/json');
        } 
        
        $stmt = $pdo->prepare('SELECT RoleID FROM dbo.Users WHERE User_ID = :uid');
        $stmt->execute([':uid' => $userId]);
        $roleID = (int)($stmt->fetchColumn() ?: 0);
        if ($roleID <= 0) {
            $roleID = 1; // Default to lowest role
        }

        $sql = 'SELECT CategoryID AS id, 
            Name AS name 
            FROM dbo.Categories 
            WHERE UsableByRoleID <= :roleID 
            ORDER BY Name';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':roleID' => $roleID]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $res->getBody()->write(json_encode(['ok' => true, 'maxSelectable' => 1, 'items' => $items], JSON_UNESCAPED_UNICODE));
        
        return $res
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=300');

    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Failed to fetch categories']));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});