<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use PDO;

use function Forum\Helpers\json;

class AdminTagController extends BaseController
{
    public function listTags(Request $req, Response $res): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $rows = $pdo->query("SELECT TagID, Name, UsableByRoleID FROM dbo.Forum_Tags ORDER BY Name ASC")
                ->fetchAll(PDO::FETCH_ASSOC);
            return json($res, ['ok' => true, 'items' => $rows]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createTag(Request $req, Response $res): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $data = $req->getParsedBody() ?? [];

            $name = preg_replace('/\s+/', ' ', trim((string)($data['name'] ?? '')));
            $minRole = (int)($data['usableByRoleId'] ?? 1);

            if ($name === '') {
                return json($res, ['ok' => false, 'error' => 'Name is required.'], 400);
            }

            $dup = $pdo->prepare("SELECT 1 FROM dbo.Forum_Tags WHERE LOWER(LTRIM(RTRIM(Name))) = LOWER(:name)");
            $dup->execute([':name' => $name]);
            if ($dup->fetchColumn()) {
                return json($res, ['ok' => false, 'error' => 'Duplicate tag name.'], 409);
            }

            $pdo->prepare("INSERT INTO dbo.Forum_Tags (Name, UsableByRoleID) VALUES (:name, :minRole)")
                ->execute([':name' => $name, ':minRole' => $minRole]);

            return json($res, ['ok' => true], 201);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateTag(Request $req, Response $res, array $args): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid tag id.'], 400);
            }

            $data = $req->getParsedBody() ?? [];
            $name = preg_replace('/\s+/', ' ', trim((string)($data['name'] ?? '')));
            $minRole = (int)($data['usableByRoleId'] ?? 1);

            if ($name === '') {
                return json($res, ['ok' => false, 'error' => 'Name is required.'], 400);
            }

            $dup = $pdo->prepare("
                SELECT 1 FROM dbo.Forum_Tags
                WHERE LOWER(LTRIM(RTRIM(Name))) = LOWER(:name) AND TagID <> :id
            ");
            $dup->execute([':name' => $name, ':id' => $id]);
            if ($dup->fetchColumn()) {
                return json($res, ['ok' => false, 'error' => 'Duplicate tag name.'], 409);
            }

            $upd = $pdo->prepare("UPDATE dbo.Forum_Tags SET Name = :name, UsableByRoleID = :minRole WHERE TagID = :id");
            $upd->execute([':name' => $name, ':minRole' => $minRole, ':id' => $id]);

            if ($upd->rowCount() === 0) {
                return json($res, ['ok' => false, 'error' => 'Tag not found.'], 404);
            }

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteTag(Request $req, Response $res, array $args): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid tag id.'], 400);
            }

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
            if ($pdo->inTransaction()) $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listReportTags(Request $req, Response $res): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $rows = $pdo->query("SELECT ReportTagID, TagName FROM dbo.Forum_ReportTags ORDER BY TagName ASC")
                ->fetchAll(PDO::FETCH_ASSOC);
            return json($res, ['ok' => true, 'items' => $rows]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createReportTag(Request $req, Response $res): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $data = $req->getParsedBody() ?? [];
            $tagName = preg_replace('/\s+/', ' ', trim((string)($data['tagName'] ?? '')));

            if ($tagName === '') {
                return json($res, ['ok' => false, 'error' => 'Tag name is required.'], 400);
            }

            $dup = $pdo->prepare("SELECT 1 FROM dbo.Forum_ReportTags WHERE LOWER(LTRIM(RTRIM(TagName))) = LOWER(:name)");
            $dup->execute([':name' => $tagName]);
            if ($dup->fetchColumn()) {
                return json($res, ['ok' => false, 'error' => 'Duplicate report tag.'], 409);
            }

            $pdo->prepare("INSERT INTO dbo.Forum_ReportTags (TagName) VALUES (:name)")
                ->execute([':name' => $tagName]);

            return json($res, ['ok' => true], 201);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateReportTag(Request $req, Response $res, array $args): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid report tag id.'], 400);
            }

            $data = $req->getParsedBody() ?? [];
            $tagName = preg_replace('/\s+/', ' ', trim((string)($data['tagName'] ?? '')));

            if ($tagName === '') {
                return json($res, ['ok' => false, 'error' => 'Tag name is required.'], 400);
            }

            $dup = $pdo->prepare("
                SELECT 1 FROM dbo.Forum_ReportTags
                WHERE LOWER(LTRIM(RTRIM(TagName))) = LOWER(:name) AND ReportTagID <> :id
            ");
            $dup->execute([':name' => $tagName, ':id' => $id]);
            if ($dup->fetchColumn()) {
                return json($res, ['ok' => false, 'error' => 'Duplicate report tag.'], 409);
            }

            $upd = $pdo->prepare("UPDATE dbo.Forum_ReportTags SET TagName = :name WHERE ReportTagID = :id");
            $upd->execute([':name' => $tagName, ':id' => $id]);

            if ($upd->rowCount() === 0) {
                return json($res, ['ok' => false, 'error' => 'Report tag not found.'], 404);
            }

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteReportTag(Request $req, Response $res, array $args): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid report tag id.'], 400);
            }

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
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
