<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

// Simple test route (to confirm file loads)
$app->get('/api/admin/ping', function(Request $req, Response $res) {
    return json($res, ['ok' => true, 'message' => 'Admin routes loaded']);
});

$app->get('/api/admin/me', function(Request $req, Response $res) use ($makePdo) {

    // SessionMiddleware attaches this when authenticated
    $userId = $req->getAttribute('user_id');

    if ($userId === null) {
        return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
    }

    $pdo = $makePdo();

    $sql = "
        SELECT u.User_ID, u.Email, u.RoleID, r.Name as RoleName
        FROM dbo.Users u
        LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
        WHERE u.User_ID = :uid
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return json($res, ['ok' => false, 'error' => 'User not found'], 404);
    }

    // Admin = RoleID 4
    if ((int)$user['RoleID'] < 4) {
        return json($res, ['ok' => false, 'error' => 'Forbidden (admin only)'], 403);
    }

    return json($res, ['ok' => true, 'user' => $user]);
});

//  Search users

$app->get('/api/admin/users', function(Request $req, Response $res) use ($makePdo) {
    try {
        $adminId = $req->getAttribute('user_id');
        if ($adminId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();

        // Verify admin
        $roleStmt = $pdo->prepare("SELECT RoleID FROM dbo.Users WHERE User_ID = :uid");
        $roleStmt->execute([':uid' => $adminId]);
        $adminRole = (int)($roleStmt->fetchColumn() ?? 0);

        if ($adminRole < 4) {
            return json($res, ['ok' => false, 'error' => 'Forbidden (admin only)'], 403);
        }

        $params = $req->getQueryParams();
        $q = trim((string)($params['q'] ?? ''));

        $bindings = [];
        $where = '';

        if ($q !== '') {
            $where = "
                WHERE (
                    u.Email     LIKE :emailLike
                    OR u.FirstName LIKE :firstLike
                    OR u.LastName  LIKE :lastLike
            ";
            $bindings[':emailLike'] = '%' . $q . '%';
            $bindings[':firstLike'] = '%' . $q . '%';
            $bindings[':lastLike']  = '%' . $q . '%';
            if (ctype_digit($q)) {
                $where .= " OR u.User_ID = :uidSearch";
                $bindings[':uidSearch'] = (int)$q;
            }
            $where .= ")";
        }

        // Single query when ban columns exist (migrations 008, 009)
        $users = [];
        try {
            $sql = "
                SELECT TOP 50
                    u.User_ID, u.Email, u.FirstName, u.LastName, u.RoleID,
                    r.Name as RoleName,
                    ISNULL(u.IsBanned, 0) as IsBanned, u.BanType, u.BannedUntil
                FROM dbo.Users u
                LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                $where
                ORDER BY u.User_ID DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // Ban columns may not exist: run without them, then fetch ban data in second query
            $sql = "
                SELECT TOP 50
                    u.User_ID, u.Email, u.FirstName, u.LastName, u.RoleID,
                    r.Name as RoleName
                FROM dbo.Users u
                LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                $where
                ORDER BY u.User_ID DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as &$u) {
                $u['IsBanned'] = 0;
                $u['BanType'] = null;
                $u['BannedUntil'] = null;
            }
            unset($u);
            try {
                $ids = array_map(fn($u) => (int)$u['User_ID'], $users);
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $banStmt = $pdo->prepare("SELECT User_ID, ISNULL(IsBanned, 0), BanType, BannedUntil FROM dbo.Users WHERE User_ID IN ($placeholders)");
                    $banStmt->execute($ids);
                    $banMap = [];
                    while ($row = $banStmt->fetch(PDO::FETCH_NUM)) {
                        $uid = (int)$row[0];
                        $banMap[$uid] = [
                            'IsBanned' => (int)$row[1],
                            'BanType' => $row[2] ? trim((string)$row[2]) : null,
                            'BannedUntil' => $row[3] ? $row[3] : null,
                        ];
                    }
                    foreach ($users as &$u) {
                        $bid = (int)$u['User_ID'];
                        $u['IsBanned'] = $banMap[$bid]['IsBanned'] ?? 0;
                        $u['BanType'] = $banMap[$bid]['BanType'] ?? null;
                        $u['BannedUntil'] = $banMap[$bid]['BannedUntil'] ?? null;
                    }
                    unset($u);
                }
            } catch (Throwable $e2) {
                // ignore
            }
        }

        return json($res, ['ok' => true, 'users' => $users]);

    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});


//  Update role

$app->patch('/api/admin/users/{id}/role', function(Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $adminId = $req->getAttribute('user_id');
        if ($adminId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();

        // Verify admin
        $roleStmt = $pdo->prepare("SELECT RoleID FROM dbo.Users WHERE User_ID = :uid");
        $roleStmt->execute([':uid' => $adminId]);
        $adminRole = (int)($roleStmt->fetchColumn() ?? 0);

        if ($adminRole < 4) {
            return json($res, ['ok' => false, 'error' => 'Forbidden (admin only)'], 403);
        }

        $targetUserId = (int)($args['id'] ?? 0);
        if ($targetUserId <= 0) {
            return json($res, ['ok' => false, 'error' => 'Invalid user id'], 400);
        }

        $data = $req->getParsedBody() ?? [];
        $newRoleId = (int)($data['roleId'] ?? 0);

        if ($newRoleId < 1 || $newRoleId > 4) {
            return json($res, ['ok' => false, 'error' => 'roleId must be between 1 and 4'], 400);
        }

        // Optional safety: prevent demoting yourself
        if ($targetUserId === (int)$adminId) {
            return json($res, ['ok' => false, 'error' => 'You cannot change your own role'], 400);
        }

        // Update
        $update = $pdo->prepare("
            UPDATE dbo.Users
            SET RoleID = :roleId
            WHERE User_ID = :uid
        ");
        $update->execute([
            ':roleId' => $newRoleId,
            ':uid'    => $targetUserId
        ]);

        return json($res, ['ok' => true]);

    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

// Ban / unban user

$app->patch('/api/admin/users/{id}/ban', function(Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $adminId = $req->getAttribute('user_id');
        if ($adminId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();

        $roleStmt = $pdo->prepare("SELECT RoleID FROM dbo.Users WHERE User_ID = :uid");
        $roleStmt->execute([':uid' => $adminId]);
        $adminRole = (int)($roleStmt->fetchColumn() ?? 0);

        if ($adminRole < 4) {
            return json($res, ['ok' => false, 'error' => 'Forbidden (admin only)'], 403);
        }

        $targetUserId = (int)($args['id'] ?? 0);
        if ($targetUserId <= 0) {
            return json($res, ['ok' => false, 'error' => 'Invalid user id'], 400);
        }

        $data = $req->getParsedBody() ?? [];
        $banned = isset($data['banned']) ? (bool)$data['banned'] : true;
        $banType = $banned ? trim(strtolower((string)($data['banType'] ?? 'permanent'))) : null;
        $bannedUntil = $banned && $banType === 'temporary' ? ($data['bannedUntil'] ?? null) : null;

        if ($banType !== null && $banType !== 'permanent' && $banType !== 'temporary') {
            $banType = 'permanent';
        }
        if ($banned && $banType === 'temporary') {
            if (empty($bannedUntil)) {
                return json($res, ['ok' => false, 'error' => 'bannedUntil is required for a temporary ban'], 400);
            }
            $until = \DateTimeImmutable::createFromFormat('Y-m-d', trim($bannedUntil), new \DateTimeZone('UTC'));
            if (!$until) {
                $until = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $bannedUntil, new \DateTimeZone('UTC'));
            }
            if (!$until) {
                return json($res, ['ok' => false, 'error' => 'bannedUntil must be a date (YYYY-MM-DD)'], 400);
            }
            // Compare calendar dates only so "tomorrow" in user's timezone is valid
            $todayUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
            $untilDate = $until->format('Y-m-d');
            if ($untilDate < $todayUtc) {
                return json($res, ['ok' => false, 'error' => 'bannedUntil must be today or a future date (YYYY-MM-DD)'], 400);
            }
            $until = $until->setTime(23, 59, 59);
            $bannedUntil = $until->format('Y-m-d H:i:s');
        } else {
            $bannedUntil = null;
        }

        if ($targetUserId === (int)$adminId) {
            return json($res, ['ok' => false, 'error' => 'You cannot ban yourself'], 400);
        }

        try {
            if ($banned) {
                $update = $pdo->prepare("
                    UPDATE dbo.Users
                    SET IsBanned = 1, BanType = :banType, BannedUntil = :bannedUntil
                    WHERE User_ID = :uid
                ");
                $update->execute([
                    ':banType' => $banType,
                    ':bannedUntil' => $bannedUntil,
                    ':uid' => $targetUserId
                ]);
            } else {
                $update = $pdo->prepare("
                    UPDATE dbo.Users
                    SET IsBanned = 0, BanType = NULL, BannedUntil = NULL
                    WHERE User_ID = :uid
                ");
                $update->execute([':uid' => $targetUserId]);
            }
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'IsBanned') !== false || strpos($e->getMessage(), 'BanType') !== false) {
                return json($res, ['ok' => false, 'error' => 'Ban feature requires database migration. Run: php database/migrate.php from the backend folder.'], 503);
            }
            throw $e;
        }

        return json($res, ['ok' => true, 'banned' => $banned, 'banType' => $banType, 'bannedUntil' => $bannedUntil]);

    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

// --- Admin Categories (BB-148) ---

function requireAdmin(Request $req, Response $res, $makePdo) {
    $userId = $req->getAttribute('user_id');
    if ($userId === null) {
        return [json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401), null];
    }
    $pdo = $makePdo();
    $roleStmt = $pdo->prepare("SELECT RoleID FROM dbo.Users WHERE User_ID = :uid");
    $roleStmt->execute([':uid' => $userId]);
    $role = (int)($roleStmt->fetchColumn() ?? 0);
    if ($role < 4) {
        return [json($res, ['ok' => false, 'error' => 'Forbidden (admin only)'], 403), null];
    }
    return [null, $pdo];
}

// List all categories (admin)
$app->get('/api/admin/categories', function (Request $req, Response $res) use ($makePdo) {
    [$err, $pdo] = requireAdmin($req, $res, $makePdo);
    if ($err !== null) return $err;

    try {
        $stmt = $pdo->query("SELECT CategoryID, Name, UsableByRoleID FROM dbo.Categories ORDER BY Name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'categoryId' => (int)$r['CategoryID'],
            'name' => $r['Name'],
            'usableByRoleID' => (int)$r['UsableByRoleID'],
        ], $rows);
        return json($res, ['ok' => true, 'items' => $items]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

// Create category (admin), prevent duplicates
$app->post('/api/admin/categories', function (Request $req, Response $res) use ($makePdo) {
    [$err, $pdo] = requireAdmin($req, $res, $makePdo);
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
        $check = $pdo->prepare("SELECT CategoryID FROM dbo.Categories WHERE Name = :name");
        $check->execute([':name' => $name]);
        if ($check->fetch()) {
            return json($res, ['ok' => false, 'error' => 'A category with this name already exists.'], 409);
        }
        $pdo->prepare("INSERT INTO dbo.Categories (Name, UsableByRoleID) VALUES (:name, :rid)")
            ->execute([':name' => $name, ':rid' => $usableByRoleID]);
        $idStmt = $pdo->query("SELECT SCOPE_IDENTITY() AS id");
        $newId = (int)($idStmt->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        return json($res, ['ok' => true, 'categoryId' => $newId, 'name' => $name]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

// Update category (admin), prevent duplicates
$app->patch('/api/admin/categories/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    [$err, $pdo] = requireAdmin($req, $res, $makePdo);
    if ($err !== null) return $err;

    $id = (int)($args['id'] ?? 0);
    if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid category id.'], 400);

    $data = $req->getParsedBody() ?? [];
    $name = trim((string)($data['name'] ?? ''));
    $usableByRoleID = isset($data['usableByRoleID']) ? (int)$data['usableByRoleID'] : null;
    if ($name === '') {
        return json($res, ['ok' => false, 'error' => 'Category name is required.'], 400);
    }

    try {
        $check = $pdo->prepare("SELECT CategoryID FROM dbo.Categories WHERE Name = :name AND CategoryID != :id");
        $check->execute([':name' => $name, ':id' => $id]);
        if ($check->fetch()) {
            return json($res, ['ok' => false, 'error' => 'A category with this name already exists.'], 409);
        }
        if ($usableByRoleID !== null) {
            if ($usableByRoleID < 1 || $usableByRoleID > 4) {
                return json($res, ['ok' => false, 'error' => 'usableByRoleID must be between 1 and 4.'], 400);
            }
            $pdo->prepare("UPDATE dbo.Categories SET Name = :name, UsableByRoleID = :rid WHERE CategoryID = :id")
                ->execute([':name' => $name, ':rid' => $usableByRoleID, ':id' => $id]);
        } else {
            $pdo->prepare("UPDATE dbo.Categories SET Name = :name WHERE CategoryID = :id")
                ->execute([':name' => $name, ':id' => $id]);
        }
        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

// Delete category (admin): move posts to General, then delete
$app->delete('/api/admin/categories/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    [$err, $pdo] = requireAdmin($req, $res, $makePdo);
    if ($err !== null) return $err;

    $id = (int)($args['id'] ?? 0);
    if ($id <= 0) return json($res, ['ok' => false, 'error' => 'Invalid category id.'], 400);

    try {
        $pdo->beginTransaction();
        $generalStmt = $pdo->prepare("SELECT CategoryID FROM dbo.Categories WHERE Name = N'General'");
        $generalStmt->execute();
        $general = $generalStmt->fetch(PDO::FETCH_ASSOC);
        if (!$general) {
            $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => 'General category not found. Cannot delete.'], 400);
        }
        $generalId = (int)$general['CategoryID'];
        if ($generalId === $id) {
            $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => 'Cannot delete the General category.'], 400);
        }
        $move = $pdo->prepare("UPDATE dbo.Posts SET CategoryID = :generalId WHERE CategoryID = :id");
        $move->execute([':generalId' => $generalId, ':id' => $id]);
        $del = $pdo->prepare("DELETE FROM dbo.Categories WHERE CategoryID = :id");
        $del->execute([':id' => $id]);
        $pdo->commit();
        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});