<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

/* -------------------- TAGS -------------------- */

$app->get('/api/admin/tags', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $sql = "SELECT TagID, Name, UsableByRoleID FROM dbo.Tags ORDER BY Name ASC";
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
            SELECT 1 FROM dbo.Tags
            WHERE LOWER(LTRIM(RTRIM(Name))) = LOWER(:name)
        ");
        $dup->execute([':name' => $name]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate tag name.'], 409);

        $ins = $pdo->prepare("INSERT INTO dbo.Tags (Name, UsableByRoleID) VALUES (:name, :minRole)");
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

        $exists = $pdo->prepare("SELECT 1 FROM dbo.Tags WHERE TagID = :id");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Tag not found.'], 404);

        $dup = $pdo->prepare("
            SELECT 1 FROM dbo.Tags
            WHERE LOWER(LTRIM(RTRIM(Name))) = LOWER(:name)
              AND TagID <> :id
        ");
        $dup->execute([':name' => $name, ':id' => $id]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate tag name.'], 409);

        $upd = $pdo->prepare("UPDATE dbo.Tags SET Name = :name, UsableByRoleID = :minRole WHERE TagID = :id");
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
        $pdo->prepare("DELETE FROM dbo.PostTags WHERE TagID = ?")->execute([$id]);

        $del = $pdo->prepare("DELETE FROM dbo.Tags WHERE TagID = ?");
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
        $sql = "SELECT ReportTagID, TagName FROM dbo.ReportTags ORDER BY TagName ASC";
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
            SELECT 1 FROM dbo.ReportTags
            WHERE LOWER(LTRIM(RTRIM(TagName))) = LOWER(:name)
        ");
        $dup->execute([':name' => $tagName]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate report tag.'], 409);

        $ins = $pdo->prepare("INSERT INTO dbo.ReportTags (TagName) VALUES (:name)");
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

        $exists = $pdo->prepare("SELECT 1 FROM dbo.ReportTags WHERE ReportTagID = :id");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Report tag not found.'], 404);

        $dup = $pdo->prepare("
            SELECT 1 FROM dbo.ReportTags
            WHERE LOWER(LTRIM(RTRIM(TagName))) = LOWER(:name)
              AND ReportTagID <> :id
        ");
        $dup->execute([':name' => $tagName, ':id' => $id]);
        if ($dup->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Duplicate report tag.'], 409);

        $upd = $pdo->prepare("UPDATE dbo.ReportTags SET TagName = :name WHERE ReportTagID = :id");
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

        $inUse = $pdo->prepare("SELECT TOP 1 1 FROM dbo.Reports WHERE ReportTagID = :id");
        $inUse->execute([':id' => $id]);
        if ($inUse->fetchColumn()) {
            return json($res, ['ok' => false, 'error' => 'Cannot delete: tag is in use by existing reports.'], 409);
        }

        $del = $pdo->prepare("DELETE FROM dbo.ReportTags WHERE ReportTagID = ?");
        $del->execute([$id]);

        if ($del->rowCount() === 0) {
            return json($res, ['ok' => false, 'error' => 'Report tag not found.'], 404);
        }

        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});