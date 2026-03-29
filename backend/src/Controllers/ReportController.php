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

    private function requireModOrAdmin(Request $req, Response $res): array|Response
    {
        $userId = $req->getAttribute('user_id');
        if ($userId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = ($this->makePdo)();
        $roleStmt = $pdo->prepare("SELECT r.Name FROM dbo.Forum_Users u LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID WHERE u.User_ID = :uid");
        $roleStmt->execute([':uid' => $userId]);
        $role = $roleStmt->fetchColumn();

        if (!in_array($role, ['moderator', 'admin'], true)) {
            return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
        }

        return ['userId' => (int)$userId, 'pdo' => $pdo];
    }

    public function getReports(Request $req, Response $res): Response
    {
        try {
            $auth = $this->requireModOrAdmin($req, $res);
            if ($auth instanceof Response) {
                return $auth;
            }

            $pdo = $auth['pdo'];

            $sql = "
                SELECT
                    r.ReportID,
                    r.PostID,
                    p.Title AS PostTitle,
                    CONCAT(up.FirstName, ' ', up.LastName) AS PostAuthor,
                    r.CommentID,
                    c.Content AS CommentText,
                    NULLIF(CONCAT(uc.FirstName, ' ', uc.LastName),'') AS CommentAuthor,
                    r.CreatedAt,
                    rt.TagName AS Reason,
                    r.ReportUserID AS ReporterId,
                    CONCAT(ur.FirstName, ' ', ur.LastName) AS ReporterName
                FROM dbo.Forum_Reports r
                INNER JOIN dbo.Forum_ReportTags rt ON r.ReportTagID = rt.ReportTagID
                LEFT JOIN dbo.Forum_Posts p ON r.PostID = p.PostID
                LEFT JOIN dbo.Forum_Comments c ON r.CommentID = c.CommentId
                LEFT JOIN dbo.Forum_Users up ON up.User_ID = p.AuthorID
                LEFT JOIN dbo.Forum_Users uc ON uc.User_ID = c.UserId
                LEFT JOIN dbo.Forum_Users ur ON ur.User_ID = r.ReportUserID
                WHERE r.Resolved = 0
                ORDER BY r.CreatedAt DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $reports = [];
            foreach ($rows as $row) {
                $reports[] = [
                    'reportId'      => (int)$row['ReportID'],
                    'postId'        => (int)($row['PostID'] ?? 0) ?: null,
                    'postTitle'     => $row['PostTitle'] ?? null,
                    'postAuthor'    => $row['PostAuthor'] ?? null,
                    'commentId'     => (int)($row['CommentID'] ?? 0) ?: null,
                    'commentText'   => $row['CommentText'] ?? null,
                    'commentAuthor' => $row['CommentAuthor'] ?? null,
                    'source'        => (int)($row['CommentID'] ?? 0) > 0 ? 'Comment' : 'Post',
                    'reason'        => $row['Reason'] ?? 'Other',
                    'createdAt'     => $row['CreatedAt'],
                    'reporter'      => [
                        'id'       => (int)$row['ReporterId'],
                        'fullName' => $row['ReporterName'] ?? null,
                    ],
                ];
            }

            return json($res, ['ok' => true, 'reports' => $reports]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function resolveReport(Request $req, Response $res, array $args): Response
    {
        try {
            $auth = $this->requireModOrAdmin($req, $res);
            if ($auth instanceof Response) {
                return $auth;
            }

            $pdo = $auth['pdo'];
            $userId = $auth['userId'];
            $reportId = (int)$args['id'];

            $stmt = $pdo->prepare("UPDATE dbo.Forum_Reports SET Resolved = 1, ResolvedBy = :uid, ResolvedAt = SYSUTCDATETIME() WHERE ReportID = :id AND Resolved = 0");
            $stmt->execute([':uid' => $userId, ':id' => $reportId]);

            if ($stmt->rowCount() === 0) {
                return json($res, ['ok' => false, 'error' => 'Report not found or already resolved'], 404);
            }

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getReportTags(Request $req, Response $res): Response
    {
        try {
            if (($userId = $req->getAttribute('user_id')) === null) {
                return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
            }

            $pdo = ($this->makePdo)();

            $sql = "SELECT ReportTagID, TagName FROM dbo.Forum_ReportTags
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

            $checkSql = "SELECT TOP 1 ReportID FROM dbo.Forum_Reports
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

            $sql = "INSERT INTO dbo.Forum_Reports (ReportUserID, PostID, CommentID, ReportTagID, CreatedAt, Resolved)
                    VALUES (:userId, :postId, :commentId, :tagId, SYSUTCDATETIME(), 0)";

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