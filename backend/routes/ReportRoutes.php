<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Forum\Controllers\ReportController;

use function Forum\Helpers\json;

$reportController = new ReportController($makePdo);

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
            FROM dbo.Reports r
            INNER JOIN dbo.ReportTags rt ON r.ReportTagID = rt.ReportTagID
            LEFT JOIN dbo.Posts p ON r.PostID = p.PostID
            LEFT JOIN dbo.Comments c ON r.CommentID = c.CommentId
            LEFT JOIN dbo.Users up ON up.User_ID = p.AuthorID
            LEFT JOIN dbo.Users uc ON uc.User_ID = c.UserId
            LEFT JOIN dbo.Users ur ON ur.User_ID = r.ReportUserID
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
                'postTitle' => $row['PostTitle'] ?? null,
                'postAuthor' => $row['PostAuthor'] ?? null,
                'commentId' => (int)($row['CommentID'] ?? 0) ?: null,
                'commentText' => $row['CommentText'] ?? null,
                'commentAuthor' => $row['CommentAuthor'] ?? null,
                'source'    => $source,
                'reason'    => $row['Reason'] ?? 'Other',
                'createdAt' => $row['CreatedAt'],
                'reporter' => [
                    'id' => (int)$row['ReporterId'],
                    'fullName' => $row['ReporterName'] ?? null,
                ],
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

$app->get('/api/report/tags', [$reportController, 'getReportTags']);

$app->post('/api/report', [$reportController, 'submitReport']);