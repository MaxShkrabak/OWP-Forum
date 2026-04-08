<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use PDO;

use function Forum\Helpers\json;

class AdminController extends BaseController
{
    public function getUsers(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;

            try {
                $pdo->exec("
                    UPDATE dbo.Forum_Users
                    SET IsBanned = 0, BanType = NULL, BannedUntil = NULL
                    WHERE ISNULL(IsBanned, 0) = 1
                      AND BanType = 'temporary'
                      AND BannedUntil IS NOT NULL
                      AND BannedUntil <= GETDATE()
                ");
            } catch (Throwable) {
            }

            $q = trim((string)($req->getQueryParams()['q'] ?? ''));
            $bindings = [];
            $where = '';

            if ($q !== '') {
                $where = "WHERE (u.Email LIKE :emailLike OR u.FirstName LIKE :firstLike OR u.LastName LIKE :lastLike";
                $bindings = [':emailLike' => "%$q%", ':firstLike' => "%$q%", ':lastLike' => "%$q%"];
                if (ctype_digit($q)) {
                    $where .= ' OR u.User_ID = :uidSearch';
                    $bindings[':uidSearch'] = (int)$q;
                }
                $where .= ')';
            }

            $base = "
                SELECT TOP 50
                    u.User_ID as userId, u.Email as email, u.FirstName as firstName,
                    u.LastName as lastName, u.RoleID as roleId, r.Name as roleName
                FROM dbo.Forum_Users u
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                $where
                ORDER BY u.User_ID DESC
            ";

            try {
                $stmt = $pdo->prepare(str_replace(
                    'u.RoleID as roleId, r.Name as roleName',
                    'u.RoleID as roleId, r.Name as roleName, ISNULL(u.IsBanned, 0) as isBanned, u.BanType as banType, u.BannedUntil as bannedUntil',
                    $base
                ));
                $stmt->execute($bindings);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                $stmt = $pdo->prepare($base);
                $stmt->execute($bindings);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($users as &$u) {
                    $u['isBanned'] = 0;
                    $u['banType'] = null;
                    $u['bannedUntil'] = null;
                }
                unset($u);
            }

            return json($res, ['ok' => true, 'users' => $users]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserById(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(3, $req, $res);
            if ($err !== null) return $err;

            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid user id.'], 400);
            }

            $stmt = $pdo->prepare("SELECT User_ID, FirstName, LastName FROM dbo.Forum_Users WHERE User_ID = :id");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return json($res, ['ok' => false, 'error' => 'User not found.'], 404);
            }

            return json($res, ['ok' => true, 'user' => $user]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateRole(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;

            $adminId = $req->getAttribute('user_id');
            $targetId = (int)($args['id'] ?? 0);
            if ($targetId <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid user id'], 400);
            }

            if ($targetId === (int)$adminId) {
                return json($res, ['ok' => false, 'error' => 'You cannot change your own role'], 400);
            }

            $data = $req->getParsedBody() ?? [];
            $newRoleId = (int)($data['roleId'] ?? 0);
            if ($newRoleId < 1 || $newRoleId > 4) {
                return json($res, ['ok' => false, 'error' => 'roleId must be between 1 and 4'], 400);
            }

            $pdo->prepare("UPDATE dbo.Forum_Users SET RoleID = :roleId WHERE User_ID = :uid")
                ->execute([':roleId' => $newRoleId, ':uid' => $targetId]);

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function setBan(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;

            $adminId = $req->getAttribute('user_id');
            $targetId = (int)($args['id'] ?? 0);
            if ($targetId <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid user id'], 400);
            }

            if ($targetId === (int)$adminId) {
                return json($res, ['ok' => false, 'error' => 'You cannot ban yourself'], 400);
            }

            $data = $req->getParsedBody() ?? [];
            $banned = isset($data['banned']) ? (bool)$data['banned'] : true;

            if ($banned) {
                $targetStmt = $pdo->prepare("SELECT RoleID FROM dbo.Forum_Users WHERE User_ID = :uid");
                $targetStmt->execute([':uid' => $targetId]);
                if ((int)($targetStmt->fetchColumn() ?? 0) === 4) {
                    return json($res, ['ok' => false, 'error' => 'You cannot ban another administrator'], 403);
                }
            }

            $banType = $banned ? trim(strtolower((string)($data['banType'] ?? 'permanent'))) : null;
            if ($banType !== null && $banType !== 'permanent' && $banType !== 'temporary') {
                $banType = 'permanent';
            }

            $bannedUntil = null;
            if ($banned && $banType === 'temporary') {
                $raw = $data['bannedUntil'] ?? null;
                if (empty($raw)) {
                    return json($res, ['ok' => false, 'error' => 'bannedUntil is required for a temporary ban'], 400);
                }

                $until = \DateTimeImmutable::createFromFormat('Y-m-d', trim($raw), new \DateTimeZone('UTC'))
                    ?: \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $raw, new \DateTimeZone('UTC'));

                if (!$until) {
                    return json($res, ['ok' => false, 'error' => 'bannedUntil must be a date (YYYY-MM-DD)'], 400);
                }

                $todayUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
                if ($until->format('Y-m-d') < $todayUtc) {
                    return json($res, ['ok' => false, 'error' => 'bannedUntil must be today or a future date (YYYY-MM-DD)'], 400);
                }

                $bannedUntil = $until->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            }

            try {
                if ($banned) {
                    $pdo->prepare("
                        UPDATE dbo.Forum_Users
                        SET IsBanned = 1, BanType = :banType, BannedUntil = :bannedUntil
                        WHERE User_ID = :uid
                    ")->execute([':banType' => $banType, ':bannedUntil' => $bannedUntil, ':uid' => $targetId]);
                } else {
                    $pdo->prepare("
                        UPDATE dbo.Forum_Users
                        SET IsBanned = 0, BanType = NULL, BannedUntil = NULL
                        WHERE User_ID = :uid
                    ")->execute([':uid' => $targetId]);
                }
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'IsBanned') || str_contains($e->getMessage(), 'BanType')) {
                    return json($res, ['ok' => false, 'error' => 'Ban feature requires database migration. Run: php database/migrate.php from the backend folder.'], 503);
                }
                throw $e;
            }

            return json($res, ['ok' => true, 'banned' => $banned, 'banType' => $banType, 'bannedUntil' => $bannedUntil]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updatePostMetadata(Request $req, Response $res, array $args): Response
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

    public function listRoles(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;

            $rows = $pdo->query("SELECT RoleID as id, Name as name FROM dbo.Forum_Roles ORDER BY RoleID ASC")
                ->fetchAll(PDO::FETCH_ASSOC);

            $roles = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $rows);

            return json($res, ['ok' => true, 'roles' => $roles]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listCategories(Request $req, Response $res): Response
    {
        [$err, $pdo] = $this->requireRole(4, $req, $res);
        if ($err !== null) return $err;

        try {
            $rows = $pdo->query("
            SELECT
                CategoryID,
                Name,
                UsableByRoleID,
                VisibleFromRoleID
            FROM dbo.Forum_Categories
            ORDER BY Name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

            $items = array_map(fn($r) => [
                'categoryId' => (int)$r['CategoryID'],
                'name' => $r['Name'],
                'usableByRoleID' => (int)$r['UsableByRoleID'],
                'visibleFromRoleId' => $r['VisibleFromRoleID'] === null ? null : (int)$r['VisibleFromRoleID'],
            ], $rows);

            return json($res, ['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createCategory(Request $req, Response $res): Response
    {
        [$err, $pdo] = $this->requireRole(4, $req, $res);
        if ($err !== null) return $err;

        $data = $req->getParsedBody() ?? [];
        $name = trim((string)($data['name'] ?? ''));
        $usableByRoleID = (int)($data['usableByRoleID'] ?? 1);

        $visibleFromRoleId = array_key_exists('visibleFromRoleId', $data)
            ? ($data['visibleFromRoleId'] === null || $data['visibleFromRoleId'] === '' ? null : (int)$data['visibleFromRoleId'])
            : null;

        if ($name === '') {
            return json($res, ['ok' => false, 'error' => 'Category name is required.'], 400);
        }

        if ($usableByRoleID < 1 || $usableByRoleID > 4) {
            return json($res, ['ok' => false, 'error' => 'usableByRoleID must be between 1 and 4.'], 400);
        }

        if ($visibleFromRoleId !== null && ($visibleFromRoleId < 1 || $visibleFromRoleId > 4)) {
            return json($res, ['ok' => false, 'error' => 'visibleFromRoleId must be null or between 1 and 4.'], 400);
        }

        try {
            $check = $pdo->prepare("SELECT CategoryID FROM dbo.Forum_Categories WHERE Name = :name");
            $check->execute([':name' => $name]);
            if ($check->fetch()) {
                return json($res, ['ok' => false, 'error' => 'A category with this name already exists.'], 409);
            }

            $pdo->prepare("
            INSERT INTO dbo.Forum_Categories (Name, UsableByRoleID, VisibleFromRoleID)
            VALUES (:name, :rid, :visibleFromRoleId)
        ")->execute([
                ':name' => $name,
                ':rid' => $usableByRoleID,
                ':visibleFromRoleId' => $visibleFromRoleId
            ]);

            $newId = (int)($pdo->query("SELECT SCOPE_IDENTITY() AS id")->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);

            return json($res, [
                'ok' => true,
                'categoryId' => $newId,
                'name' => $name,
                'visibleFromRoleId' => $visibleFromRoleId
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function updateCategory(Request $req, Response $res, array $args): Response
    {
        [$err, $pdo] = $this->requireRole(4, $req, $res);
        if ($err !== null) return $err;

        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return json($res, ['ok' => false, 'error' => 'Invalid category id.'], 400);
        }

        $data = $req->getParsedBody() ?? [];
        $name = trim((string)($data['name'] ?? ''));

        $usableByRoleID = isset($data['usableByRoleID']) ? (int)$data['usableByRoleID'] : null;

        $visibleFromRoleId = array_key_exists('visibleFromRoleId', $data)
            ? ($data['visibleFromRoleId'] === null || $data['visibleFromRoleId'] === '' ? null : (int)$data['visibleFromRoleId'])
            : null;

        if ($name === '') {
            return json($res, ['ok' => false, 'error' => 'Category name is required.'], 400);
        }

        if ($usableByRoleID !== null && ($usableByRoleID < 1 || $usableByRoleID > 4)) {
            return json($res, ['ok' => false, 'error' => 'usableByRoleID must be between 1 and 4.'], 400);
        }

        if ($visibleFromRoleId !== null && ($visibleFromRoleId < 1 || $visibleFromRoleId > 4)) {
            return json($res, ['ok' => false, 'error' => 'visibleFromRoleId must be null or between 1 and 4.'], 400);
        }

        try {
            $check = $pdo->prepare("
            SELECT CategoryID
            FROM dbo.Forum_Categories
            WHERE Name = :name AND CategoryID != :id
        ");
            $check->execute([':name' => $name, ':id' => $id]);
            if ($check->fetch()) {
                return json($res, ['ok' => false, 'error' => 'A category with this name already exists.'], 409);
            }

            if ($usableByRoleID !== null) {
                $pdo->prepare("
                UPDATE dbo.Forum_Categories
                SET Name = :name,
                    UsableByRoleID = :rid,
                    VisibleFromRoleID = :visibleFromRoleId
                WHERE CategoryID = :id
            ")->execute([
                    ':name' => $name,
                    ':rid' => $usableByRoleID,
                    ':visibleFromRoleId' => $visibleFromRoleId,
                    ':id' => $id
                ]);
            } else {
                $pdo->prepare("
                UPDATE dbo.Forum_Categories
                SET Name = :name,
                    VisibleFromRoleID = :visibleFromRoleId
                WHERE CategoryID = :id
            ")->execute([
                    ':name' => $name,
                    ':visibleFromRoleId' => $visibleFromRoleId,
                    ':id' => $id
                ]);
            }

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteCategory(Request $req, Response $res, array $args): Response
    {
        [$err, $pdo] = $this->requireRole(4, $req, $res);
        if ($err !== null) return $err;

        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid category id.'], 400);

        try {
            $pdo->beginTransaction();

            $general = $pdo->query("SELECT CategoryID FROM dbo.Forum_Categories WHERE Name = N'General'")->fetch(PDO::FETCH_ASSOC);
            if (!$general) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'General category not found. Cannot delete.'], 400);
            }

            $generalId = (int)$general['CategoryID'];
            if ($generalId === $id) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Cannot delete the General category.'], 400);
            }

            $pdo->prepare("UPDATE dbo.Forum_Posts SET CategoryID = :generalId WHERE CategoryID = :id")
                ->execute([':generalId' => $generalId, ':id' => $id]);

            $pdo->prepare("DELETE FROM dbo.Forum_Categories WHERE CategoryID = :id")
                ->execute([':id' => $id]);

            $pdo->commit();
            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function listTags(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
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
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
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
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
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
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
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
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
            $rows = array_map(
                fn($r) => ['tagId' => (int)$r['ReportTagID'], 'name' => $r['TagName']],
                $pdo->query("SELECT ReportTagID, TagName FROM dbo.Forum_ReportTags ORDER BY TagName ASC")->fetchAll(PDO::FETCH_ASSOC)
            );
            return json($res, ['ok' => true, 'items' => $rows]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createReportTag(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
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
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
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
            [$err, $pdo] = $this->requireRole(4, $req, $res);
            if ($err !== null) return $err;
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
