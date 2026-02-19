<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

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

        // Duplicate check (case-insensitive, trimmed)
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

        // Ensure exists
        $exists = $pdo->prepare("SELECT 1 FROM dbo.Tags WHERE TagID = :id");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) return json($res, ['ok' => false, 'error' => 'Tag not found.'], 404);

        // Duplicate check excluding self
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
