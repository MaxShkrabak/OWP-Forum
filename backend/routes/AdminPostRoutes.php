<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use function Forum\Helpers\json;
use function Forum\Helpers\resolveReportsForPost;

// Soft Delete (Role 3+)
$app->patch('/api/admin/posts/{id}/soft-delete', function(Request $req, Response $res, array $args) use ($makePdo) {
    $userId = $req->getAttribute('user_id');
    $pdo = $makePdo();

    // Verify Role 3 (Moderator) or 4 (Admin)
    $roleStmt = $pdo->prepare("SELECT RoleID FROM dbo.Users WHERE User_ID = :uid");
    $roleStmt->execute([':uid' => $userId]);
    $userRole = (int)$roleStmt->fetchColumn();

    if ($userRole < 3) {
        return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
    }

    $postId = (int)$args['id'];

    $pdo->beginTransaction();
    try {
        // Update IsDeleted and DeletedAt
        $stmt = $pdo->prepare("
            UPDATE dbo.Posts 
            SET IsDeleted = 1, DeletedAt = SYSUTCDATETIME() 
            WHERE PostID = :pid AND IsDeleted = 0
        ");
        $stmt->execute([':pid' => $postId]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => 'Post not found or already deleted'], 404);
        }

        // Resolve reports for this post + comments/replies under it
        resolveReportsForPost($pdo, $postId, (int)$userId);

        $pdo->commit();
        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

// Update Metadata (Category/Tags)
$app->patch('/api/admin/posts/{id}/metadata', function(Request $req, Response $res, array $args) use ($makePdo) {
    $userId = $req->getAttribute('user_id');
    $pdo = $makePdo();
    $data = $req->getParsedBody();
    $postId = (int)$args['id'];

    $pdo->beginTransaction();
    try {
        if (isset($data['CategoryID'])) {
            $stmt = $pdo->prepare("UPDATE dbo.Posts SET CategoryID = :cid WHERE PostID = :pid");
            $stmt->execute([':cid' => $data['CategoryID'], ':pid' => $postId]);
        }
        
        // Tag logic: Clear old and insert new
        if (isset($data['TagIDs'])) {
            $pdo->prepare("DELETE FROM dbo.PostTags WHERE PostID = :pid")->execute([':pid' => $postId]);
            $tagStmt = $pdo->prepare("INSERT INTO dbo.PostTags (PostID, TagID) VALUES (:pid, :tid)");
            foreach ($data['TagIDs'] as $tagId) {
                $tagStmt->execute([':pid' => $postId, ':tid' => $tagId]);
            }
        }
        $pdo->commit();
        return json($res, ['ok' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});