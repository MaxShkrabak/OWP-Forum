<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

/* -------------------- TAGS -------------------- */

$app->get('/api/admin/tags', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $sql = "SELECT TagID, Name, UsableByRoleID FROM dbo.Forum_Tags ORDER BY Name ASC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return json($res, ['ok' => true, 'items' => $rows]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->post('/api/admin/tags', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $data = $req->getParsedBody() ?? [];

        $name = preg_replace('/\s+/', ' ', trim((string)($data['name'] ?? '')));
        $minRole = (int)($data['usableByRoleId'] ?? 1);

        if ($name === '') return json($res, ['ok' => false, 'error' => 'Name is required.'], 400);

        $dup = $pdo->prepare("
            SELECT 1 FROM dbo.Forum_Tags
            WHERE LOWER(LTRIM(RTRIM(Name))) = LOWER(:name)
        ");
        $dup->execute([':name' => $name]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate tag name.'], 409);

        $ins = $pdo->prepare("INSERT INTO dbo.Forum_Tags (Name, UsableByRoleID) VALUES (:name, :minRole)");
        $ins->execute([':name' => $name, ':minRole' => $minRole]);

        return json($res, ['ok' => true], 201);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->patch('/api/admin/tags/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid tag id.'], 400);

        $data = $req->getParsedBody() ?? [];
        $name = preg_replace('/\s+/', ' ', trim((string)($data['name'] ?? '')));
        $minRole = (int)($data['usableByRoleId'] ?? 1);

        if ($name === '') return json($res, ['ok' => false, 'error' => 'Name is required.'], 400);

        $exists = $pdo->prepare("SELECT 1 FROM dbo.Forum_Tags WHERE TagID = :id");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Tag not found.'], 404);

        $dup = $pdo->prepare("
            SELECT 1 FROM dbo.Forum_Tags
            WHERE LOWER(LTRIM(RTRIM(Name))) = LOWER(:name)
              AND TagID <> :id
        ");
        $dup->execute([':name' => $name, ':id' => $id]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate tag name.'], 409);

        $upd = $pdo->prepare("UPDATE dbo.Forum_Tags SET Name = :name, UsableByRoleID = :minRole WHERE TagID = :id");
        $upd->execute([':name' => $name, ':minRole' => $minRole, ':id' => $id]);

        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->delete('/api/admin/tags/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid tag id.'], 400);

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM dbo.Forum_PostTags WHERE TagID = ?")->execute([$id]);

        $del = $pdo->prepare("DELETE FROM dbo.Forum_Tags WHERE TagID = ?");
        $del->execute([$id]);

        if ($del->rowCount() === 0) {
            $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => 'Tag not found.'], 404);
        }

        $pdo->commit();
        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

/* -------------------- REPORT TAGS -------------------- */

$app->get('/api/admin/report-tags', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $sql = "SELECT ReportTagID, TagName FROM dbo.Forum_ReportTags ORDER BY TagName ASC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return json($res, ['ok' => true, 'items' => $rows]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->post('/api/admin/report-tags', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $data = $req->getParsedBody() ?? [];

        $tagName = preg_replace('/\s+/', ' ', trim((string)($data['tagName'] ?? '')));
        if ($tagName === '') return json($res, ['ok' => false, 'error' => 'Tag name is required.'], 400);

        $dup = $pdo->prepare("
            SELECT 1 FROM dbo.Forum_ReportTags
            WHERE LOWER(LTRIM(RTRIM(TagName))) = LOWER(:name)
        ");
        $dup->execute([':name' => $tagName]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate report tag.'], 409);

        $ins = $pdo->prepare("INSERT INTO dbo.Forum_ReportTags (TagName) VALUES (:name)");
        $ins->execute([':name' => $tagName]);

        return json($res, ['ok' => true], 201);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->patch('/api/admin/report-tags/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid report tag id.'], 400);

        $data = $req->getParsedBody() ?? [];
        $tagName = preg_replace('/\s+/', ' ', trim((string)($data['tagName'] ?? '')));
        if ($tagName === '') return json($res, ['ok' => false, 'error' => 'Tag name is required.'], 400);

        $exists = $pdo->prepare("SELECT 1 FROM dbo.Forum_ReportTags WHERE ReportTagID = :id");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Report tag not found.'], 404);

        $dup = $pdo->prepare("
            SELECT 1 FROM dbo.Forum_ReportTags
            WHERE LOWER(LTRIM(RTRIM(TagName))) = LOWER(:name)
              AND ReportTagID <> :id
        ");
        $dup->execute([':name' => $tagName, ':id' => $id]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate report tag.'], 409);

        $upd = $pdo->prepare("UPDATE dbo.Forum_ReportTags SET TagName = :name WHERE ReportTagID = :id");
        $upd->execute([':name' => $tagName, ':id' => $id]);

        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->delete('/api/admin/report-tags/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid report tag id.'], 400);

        $inUse = $pdo->prepare("SELECT TOP 1 1 FROM dbo.Forum_Reports WHERE ReportTagID = :id");
        $inUse->execute([':id' => $id]);
        if ($inUse->fetchColumn()) {
            return json($res, ['ok' => false, 'error' => 'Cannot delete: tag is in use by existing reports.'], 409);
        }

        $del = $pdo->prepare("DELETE FROM dbo.Forum_ReportTags WHERE ReportTagID = ?");
        $del->execute([$id]);

        if ($del->rowCount() === 0) {
            return json($res, ['ok' => false, 'error' => 'Report tag not found.'], 404);
        }

        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

/* -------------------- ADMIN USERS (LOOKUP) -------------------- */
/**
 * GET /api/admin/users/{id}
 * Returns minimal user info for admin UI lookups (e.g., "Reported by")
 *
 * Response:
 *  { ok: true, user: { User_ID, FirstName, LastName } }
 */
$app->get('/api/admin/users/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid user id.'], 400);

        $stmt = $pdo->prepare("
            SELECT TOP 1
                User_ID,
                FirstName,
                LastName
            FROM dbo.Forum_Users
            WHERE User_ID = :id
        ");
        $stmt->execute([':id' => $id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return json($res, ['ok' => false, 'error' => 'User not found.'], 404);
        }

        return json($res, ['ok' => true, 'user' => $user]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

/* -------------------- ADMIN REPORTS (NEW SAFE ENDPOINT) -------------------- */
/**
 * GET /api/admin/reports
 * New endpoint for admin UI (does NOT modify /api/reports).
 * Includes reporterId + reporterName so the frontend doesn't need extra user lookups.
 *
 * Response:
 *  { ok: true, reports: [{ reportId, postId, commentId, source, reason, createdAt, reporterId, reporterName }] }
 */
$app->get('/api/admin/reports', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        if ($userId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();

        // Only moderator/admin
        $roleStmt = $pdo->prepare("
            SELECT r.Name
            FROM dbo.Forum_Users u
            LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
            WHERE u.User_ID = :uid
        ");
        $roleStmt->execute([':uid' => $userId]);
        $role = $roleStmt->fetchColumn();

        if (!in_array($role, ['moderator', 'admin'], true)) {
            return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
        }

        // NOTE: assumes dbo.Forum_Reports.ReportUserID is the reporter (person who submitted the report)
        $sql = "
            SELECT
                r.ReportID,
                r.PostID,
                r.CommentID,
                r.CreatedAt,
                rt.TagName AS Reason,
                r.ReportUserID AS ReporterId,
                u.FirstName AS ReporterFirstName,
                u.LastName AS ReporterLastName
            FROM dbo.Forum_Reports r
            INNER JOIN dbo.Forum_ReportTags rt ON r.ReportTagID = rt.ReportTagID
            LEFT JOIN dbo.Forum_Users u ON u.User_ID = r.ReportUserID
            WHERE r.Resolved = 0
            ORDER BY r.CreatedAt DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reports = [];
        foreach ($rows as $row) {
            $postId = (int)($row['PostID'] ?? 0);
            $commentId = (int)($row['CommentID'] ?? 0);
            $source = ($commentId > 0) ? 'Comment' : 'Post';

            $first = trim((string)($row['ReporterFirstName'] ?? ''));
            $last  = trim((string)($row['ReporterLastName'] ?? ''));
            $reporterName = trim($first . ' ' . $last);

            $reports[] = [
                'reportId'     => (int)$row['ReportID'],
                'postId'       => $postId ?: null,
                'commentId'    => $commentId ?: null,
                'source'       => $source,
                'reason'       => $row['Reason'] ?? 'Other',
                'createdAt'    => $row['CreatedAt'],
                'reporterId'   => (int)($row['ReporterId'] ?? 0) ?: null,
                'reporterName' => $reporterName,
            ];
        }

        return json($res, ['ok' => true, 'reports' => $reports]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});