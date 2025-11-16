<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// GET /api/tags  -> list tags from dbo.Tags
$app->get('/api/tags', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();

        $stmt = $pdo->query('SELECT TagID, Name FROM dbo.Tags ORDER BY Name ASC');
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $res->getBody()->write(json_encode(['ok' => true, 'items' => $items]));
        return $res->withHeader('Content-Type', 'application/json');
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
