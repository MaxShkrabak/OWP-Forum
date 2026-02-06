<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

$app->post("/api/create-comment", function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");

        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        //Check if post exists
        $data = $req->getParsedBody() ?? [];
        $postId = (int)($data['post_id'] ?? 0);
        $content = trim((string)($data['content'] ?? ''));

        if (!$postId || !$content) {
            return json($res, ['ok' => false, 'error' => 'Missing post_id or content'], 400);
        }

        $pdo = $makePdo();

        $getPostSql = "SELECT 1 FROM dbo.Posts WHERE PostID = :postId";
        $poststmt = $pdo->prepare($getPostSql);
        $poststmt->execute([':postId' => $postId]);

        if (!$poststmt->fetchColumn()) {
            return json($res, ['ok' => false, 'error' => 'Post not found'], 404);
        }

        $insertSql = "
            INSERT INTO dbo.Comments (PostID, UserID, Content)
            OUTPUT INSERTED.CommentID, INSERTED.CreatedAt
            VALUES (:postId, :userId, :content)
        ";

        $stmt = $pdo->prepare($insertSql);

        $stmt->execute([
            'postId' => $postId,
            'userId' => $userId,
            'content' => $content
        ]);

        $comment = $stmt->fetch();

        if (!$comment) {
            return json($res, ['ok' => false, 'error' => 'Failed to create comment'], 500);
        }
        
        $timestamp = strtotime($comment['CreatedAt']);

        return json($res, [
            'ok' => true,
            'comment' => [
                'id' => $comment['CommentID'],
                'postId' => $postId,
                'userId' => $userId,
                'content' => $content,
                'createdAt' => $timestamp
            ]
        ], 201);
    }
    catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error'], 500);
    }
});