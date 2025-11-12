<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app->post("/api/create-post", function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        if (!$userId) {
            $res->getBody()->write(json_encode(['ok' => false, "error" => "Not Authenticated"]));
            return $res->withStatus(401)->withHeader("Content-Type", "application/json");
        }

        $data = $req->getParsedBody() ?? [];
        $title = trim((string)($data['title'] ?? ''));
        $category = trim((string)($data['category'] ?? ''));
        $content = (string)($data['content'] ?? '');

        if ($title === '' || $content === '') {
            $res->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'Title and content are required.'
            ]));
            return $res->withStatus(400)->withHeader("Content-Type", "application/json");
        }

        if ($category === "") {
            $res->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'Category is required.'
            ]));
            return $res->withStatus(400)->withHeader("Content-Type", "application/json");
        }

        $pdo = $makePdo();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT CategoryID FROM dbo.Categories WHERE Name = :name");
        $stmt->execute([':name' => $category]);
        $categoryId = $stmt->fetchColumn();

        if (!$categoryId) {
            $pdo->rollBack();
            $res->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'Unknown category: ' . $category,
            ]));
            return $res->withStatus(400)->withHeader("Content-Type", "application/json");
        }

        $sql = "
            INSERT INTO dbo.Posts (Title, CategoryID, AuthorID, Content)
            OUTPUT INSERTED.PostID
            VALUES (:title, :categoryId, :authorId, :content)
        ";

        $insertStmt = $pdo->prepare($sql);
       
        $insertStmt->execute([
            ':title' => $title,
            ':categoryId' => $categoryId,
            ':authorId' => $userId,
            ':content' => $content,
        ]);

        $postId = (int)$insertStmt->fetchColumn();
        $pdo->commit();

        $res->getBody()->write(json_encode([
            'ok' => true,
            'postId' => $postId,
        ]));
        return $res->withHeader("Content-Type", "application/json");

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $res->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'Server error: ' . $e->getMessage(),
        ]));
        return $res->withStatus(500)->withHeader("Content-Type", "application/json");
    }
});