<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use PDO;
use Closure;
use function Forum\Helpers\json;

class ReportController
{
    private Closure $makePdo;

    public function __construct(Closure $makePdo)
    {
        $this->makePdo = $makePdo;
    }

    public function getReportTags(Request $req, Response $res): Response
    {
        try {
            if (($userId = $req->getAttribute('user_id')) === null) {
                return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
            }

            $pdo = ($this->makePdo)();

            $sql = "SELECT ReportTagID, TagName FROM dbo.ReportTags
                    ORDER BY CASE WHEN TagName = 'Other' THEN 1 ELSE 0 END, TagName ASC";

            $tags = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            return json($res, ['ok' => true, 'tags' => $tags]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function submitReport(Request $req, Response $res): Response
    {
        try {
            if (($userId = $req->getAttribute('user_id')) === null) {
                return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
            }

            $body = $req->getParsedBody();
            $targetId = $body['id'] ?? null;
            $tagId    = $body['tagID'] ?? null;
            $type     = $body['type'] ?? 'post';

            if (!$targetId || !$tagId) {
                return json($res, ['ok' => false, 'error' => 'Missing required fields'], 400);
            }

            $postId    = ($type === 'post') ? $targetId : null;
            $commentId = ($type === 'comment') ? $targetId : null;

            $pdo = ($this->makePdo)();

            $checkSql = "SELECT TOP 1 ReportID FROM dbo.Reports
                         WHERE ReportUserID = :userId
                         AND COALESCE(PostID, 0) = :postId
                         AND COALESCE(CommentID, 0) = :commentId
                         AND Resolved = 0";

            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([
                ':userId'    => $userId,
                ':postId'    => (int)$postId,
                ':commentId' => (int)$commentId
            ]);

            if ($checkStmt->fetch()) {
                return json($res, ['ok' => false, 'error' => "You have already reported this $type."], 400);
            }

            $sql = "INSERT INTO dbo.Reports (ReportUserID, PostID, CommentID, ReportTagID, CreatedAt, Resolved)
                    VALUES (:userId, :postId, :commentId, :tagId, SYSDATETIME(), 0)";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':userId'    => $userId,
                ':postId'    => $postId,
                ':commentId' => $commentId,
                ':tagId'     => $tagId
            ]);

            return json($res, ['ok' => $success, 'message' => 'Report submitted successfully']);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
