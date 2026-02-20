<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

$app->get('/api/reports', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        if ($userId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }
        $pdo = $makePdo();
        $roleStmt = $pdo->prepare("SELECT r.Name FROM dbo.Users u LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID WHERE u.User_ID = :uid");
        $roleStmt->execute([':uid' => $userId]);
        $role = $roleStmt->fetchColumn();
        if (!in_array($role, ['moderator', 'admin'], true)) {
            return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $sql = "
            SELECT r.ReportID, r.PostID, r.CommentID, r.CreatedAt, rt.TagName AS Reason
            FROM dbo.Reports r
            INNER JOIN dbo.ReportTags rt ON r.ReportTagID = rt.ReportTagID
            WHERE r.Resolved = 0
            ORDER BY r.CreatedAt DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reports = [];
        foreach ($rows as $row) {
            $source = 'Post';
            $postId = (int)($row['PostID'] ?? 0);
            if ($postId && (int)($row['CommentID'] ?? 0) > 0) {
                $source = 'Comment';
            }
            if ($postId === 0 && (int)($row['CommentID'] ?? 0) > 0) {
                $source = 'Comment';
                // Comment reports may still have PostID for context; if not we might need to look up
            }
            $reports[] = [
                'reportId'  => (int)$row['ReportID'],
                'postId'    => $postId ?: null,
                'commentId' => (int)($row['CommentID'] ?? 0) ?: null,
                'source'    => $source,
                'reason'    => $row['Reason'] ?? 'Other',
                'createdAt' => $row['CreatedAt'],
            ];
        }

        return json($res, ['ok' => true, 'reports' => $reports]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->patch('/api/reports/{id}/resolve', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        if ($userId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }
        $pdo = $makePdo();
        $roleStmt = $pdo->prepare("SELECT r.Name FROM dbo.Users u LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID WHERE u.User_ID = :uid");
        $roleStmt->execute([':uid' => $userId]);
        $role = $roleStmt->fetchColumn();
        if (!in_array($role, ['moderator', 'admin'], true)) {
            return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $reportId = (int)$args['id'];
        $upd = $pdo->prepare("UPDATE dbo.Reports SET Resolved = 1, ResolvedBy = :uid, ResolvedAt = SYSDATETIME() WHERE ReportID = :id AND Resolved = 0");
        $upd->execute([':uid' => $userId, ':id' => $reportId]);

        if ($upd->rowCount() === 0) {
            return json($res, ['ok' => false, 'error' => 'Report not found or already resolved'], 404);
        }
        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->get('/api/report/tags', function(Request $req, Response $res) use ($makePdo) {
    try {
        if (($userId = $req->getAttribute('user_id')) === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();
        $sql = "SELECT ReportTagID, TagName FROM dbo.ReportTags
                ORDER BY CASE WHEN TagName = 'Other' THEN 1 ELSE 0 END, TagName ASC";
       
        $tags = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return json($res, ['ok' => true, 'tags' => $tags]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->post('/api/report', function(Request $req, Response $res) use ($makePdo) {
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

        $pdo = $makePdo();

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
});

