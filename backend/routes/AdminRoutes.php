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