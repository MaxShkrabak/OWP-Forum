<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

use function Forum\Helpers\json;
use function Forum\Helpers\resolveReportsForPost;
use function Forum\Helpers\softDeleteCommentsForPost;

class AdminPostController extends BaseController
{

    public function softDelete(Request $req, Response $res, array $args): Response
    {
        [$err, $pdo, $userId] = $this->requireRole(3, $req, $res);
        if ($err !== null) return $err;

        $postId = (int)$args['id'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE dbo.Forum_Posts
                SET IsDeleted = 1, DeletedAt = SYSUTCDATETIME()
                WHERE PostID = :pid AND IsDeleted = 0
            ");
            $stmt->execute([':pid' => $postId]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Post not found or already deleted'], 404);
            }

            softDeleteCommentsForPost($pdo, $postId);
            resolveReportsForPost($pdo, $postId, (int)$userId);

            $pdo->commit();
            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateMetadata(Request $req, Response $res, array $args): Response
    {
        [$err, $pdo] = $this->requireRole(3, $req, $res);
        if ($err !== null) return $err;

        $postId = (int)$args['id'];
        $data = $req->getParsedBody();

        $pdo->beginTransaction();
        try {
            if (isset($data['CategoryID'])) {
                $pdo->prepare("UPDATE dbo.Forum_Posts SET CategoryID = :cid WHERE PostID = :pid")
                    ->execute([':cid' => $data['CategoryID'], ':pid' => $postId]);
            }

            if (isset($data['isCommentsDisabled'])) {
                $flag = $data['isCommentsDisabled'] ? 1 : 0;
                $pdo->prepare("UPDATE dbo.Forum_Posts SET IsCommentsDisabled = :flag WHERE PostID = :pid")
                    ->execute([':flag' => $flag, ':pid' => $postId]);
            }

            if (isset($data['TagIDs'])) {
                $pdo->prepare("DELETE FROM dbo.Forum_PostTags WHERE PostID = :pid")->execute([':pid' => $postId]);
                $tagStmt = $pdo->prepare("INSERT INTO dbo.Forum_PostTags (PostID, TagID) VALUES (:pid, :tid)");
                foreach ($data['TagIDs'] as $tagId) {
                    $tagStmt->execute([':pid' => $postId, ':tid' => $tagId]);
                }
            }

            $pdo->commit();
            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
