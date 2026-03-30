<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use PDO;

use function Forum\Helpers\json;

class AdminController extends BaseController
{
    public function ping(Request $req, Response $res): Response
    {
        return json($res, ['ok' => true, 'message' => 'Admin routes loaded']);
    }

    public function me(Request $req, Response $res): Response
    {
        [$err, $pdo] = $this->requireRole(4, $req, $res);
        if ($err !== null) return $err;

        $userId = $req->getAttribute('user_id');
        $stmt = $pdo->prepare("
            SELECT u.User_ID as userId, u.Email as email, u.RoleID as roleId, r.Name as roleName
            FROM dbo.Forum_Users u
            LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
            WHERE u.User_ID = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return json($res, ['ok' => false, 'error' => 'User not found'], 404);
        }

        return json($res, ['ok' => true, 'user' => $user]);
    }

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

    public function listCategories(Request $req, Response $res): Response
    {
        [$err, $pdo] = $this->requireRole(4, $req, $res);
        if ($err !== null) return $err;

        try {
            $rows = $pdo->query("SELECT CategoryID, Name, UsableByRoleID FROM dbo.Forum_Categories ORDER BY Name ASC")
                ->fetchAll(PDO::FETCH_ASSOC);

            $items = array_map(fn($r) => [
                'categoryId'     => (int)$r['CategoryID'],
                'name'           => $r['Name'],
                'usableByRoleID' => (int)$r['UsableByRoleID'],
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

        if ($name === '') {
            return json($res, ['ok' => false, 'error' => 'Category name is required.'], 400);
        }
        if ($usableByRoleID < 1 || $usableByRoleID > 4) {
            return json($res, ['ok' => false, 'error' => 'usableByRoleID must be between 1 and 4.'], 400);
        }

        try {
            $check = $pdo->prepare("SELECT CategoryID FROM dbo.Forum_Categories WHERE Name = :name");
            $check->execute([':name' => $name]);
            if ($check->fetch()) {
                return json($res, ['ok' => false, 'error' => 'A category with this name already exists.'], 409);
            }

            $pdo->prepare("INSERT INTO dbo.Forum_Categories (Name, UsableByRoleID) VALUES (:name, :rid)")
                ->execute([':name' => $name, ':rid' => $usableByRoleID]);

            $newId = (int)($pdo->query("SELECT SCOPE_IDENTITY() AS id")->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
            return json($res, ['ok' => true, 'categoryId' => $newId, 'name' => $name]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateCategory(Request $req, Response $res, array $args): Response
    {
        [$err, $pdo] = $this->requireRole(4, $req, $res);
        if ($err !== null) return $err;

        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid category id.'], 400);

        $data = $req->getParsedBody() ?? [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return json($res, ['ok' => false, 'error' => 'Category name is required.'], 400);
        }

        $usableByRoleID = isset($data['usableByRoleID']) ? (int)$data['usableByRoleID'] : null;
        if ($usableByRoleID !== null && ($usableByRoleID < 1 || $usableByRoleID > 4)) {
            return json($res, ['ok' => false, 'error' => 'usableByRoleID must be between 1 and 4.'], 400);
        }

        try {
            $check = $pdo->prepare("SELECT CategoryID FROM dbo.Forum_Categories WHERE Name = :name AND CategoryID != :id");
            $check->execute([':name' => $name, ':id' => $id]);
            if ($check->fetch()) {
                return json($res, ['ok' => false, 'error' => 'A category with this name already exists.'], 409);
            }

            if ($usableByRoleID !== null) {
                $pdo->prepare("UPDATE dbo.Forum_Categories SET Name = :name, UsableByRoleID = :rid WHERE CategoryID = :id")
                    ->execute([':name' => $name, ':rid' => $usableByRoleID, ':id' => $id]);
            } else {
                $pdo->prepare("UPDATE dbo.Forum_Categories SET Name = :name WHERE CategoryID = :id")
                    ->execute([':name' => $name, ':id' => $id]);
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

    public function getReports(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo] = $this->requireRole(3, $req, $res);
            if ($err !== null) return $err;

            $stmt = $pdo->prepare("
                SELECT
                    r.ReportID, r.PostID, r.CommentID, r.CreatedAt,
                    rt.TagName AS Reason,
                    r.ReportUserID AS ReporterId,
                    u.FirstName AS ReporterFirstName,
                    u.LastName AS ReporterLastName
                FROM dbo.Forum_Reports r
                INNER JOIN dbo.Forum_ReportTags rt ON r.ReportTagID = rt.ReportTagID
                LEFT JOIN dbo.Forum_Users u ON u.User_ID = r.ReportUserID
                WHERE r.Resolved = 0
                ORDER BY r.CreatedAt DESC
            ");
            $stmt->execute();

            $reports = array_map(function ($row) {
                $commentId = (int)($row['CommentID'] ?? 0);
                $first = trim((string)($row['ReporterFirstName'] ?? ''));
                $last  = trim((string)($row['ReporterLastName'] ?? ''));
                return [
                    'reportId'     => (int)$row['ReportID'],
                    'postId'       => (int)($row['PostID'] ?? 0) ?: null,
                    'commentId'    => $commentId ?: null,
                    'source'       => $commentId > 0 ? 'Comment' : 'Post',
                    'reason'       => $row['Reason'] ?? 'Other',
                    'createdAt'    => $row['CreatedAt'],
                    'reporterId'   => (int)($row['ReporterId'] ?? 0) ?: null,
                    'reporterName' => trim("$first $last"),
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            return json($res, ['ok' => true, 'reports' => $reports]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
