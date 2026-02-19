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

        // Basic search (email / first / last). If empty, just return top 50 users.
        // IsBanned not in SELECT so this works before migration 008_user_ban.sql is run
        $sql = "
            SELECT TOP 50
                u.User_ID, u.Email, u.FirstName, u.LastName, u.RoleID,
                r.Name as RoleName
            FROM dbo.Users u
            LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
        ";

        $bindings = [];

        if ($q !== '') {
            $sql .= "
                WHERE (
                    u.Email     LIKE :emailLike
                    OR u.FirstName LIKE :firstLike
                    OR u.LastName  LIKE :lastLike
            ";

            $bindings[':emailLike'] = '%' . $q . '%';
            $bindings[':firstLike'] = '%' . $q . '%';
            $bindings[':lastLike']  = '%' . $q . '%';

            // If numeric, allow User_ID match too
            if (ctype_digit($q)) {
                $sql .= " OR u.User_ID = :uidSearch";
                $bindings[':uidSearch'] = (int)$q;
            }

            $sql .= ")";
        }

        $sql .= " ORDER BY u.User_ID DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add IsBanned if column exists
        foreach ($users as &$u) {
            $u['IsBanned'] = 0;
        }
        unset($u);
        try {
            $ids = array_map(fn($u) => (int)$u['User_ID'], $users);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $banStmt = $pdo->prepare("SELECT User_ID, ISNULL(IsBanned, 0) FROM dbo.Users WHERE User_ID IN ($placeholders)");
                $banStmt->execute($ids);
                $banMap = [];
                while ($row = $banStmt->fetch(PDO::FETCH_NUM)) {
                    $banMap[(int)$row[0]] = (int)$row[1];
                }
                foreach ($users as &$u) {
                    $u['IsBanned'] = $banMap[(int)$u['User_ID']] ?? 0;
                }
                unset($u);
            }
        } catch (Throwable $e) {
            // IsBanned column may not exist yet
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

        if ($targetUserId === (int)$adminId) {
            return json($res, ['ok' => false, 'error' => 'You cannot ban yourself'], 400);
        }

        try {
            $update = $pdo->prepare("
                UPDATE dbo.Users
                SET IsBanned = :banned
                WHERE User_ID = :uid
            ");
            $update->execute([
                ':banned' => $banned ? 1 : 0,
                ':uid'    => $targetUserId
            ]);
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'IsBanned') !== false) {
                return json($res, ['ok' => false, 'error' => 'Ban feature requires database migration. Run: php database/migrate.php from the backend folder.'], 503);
            }
            throw $e;
        }

        return json($res, ['ok' => true, 'banned' => $banned]);

    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});