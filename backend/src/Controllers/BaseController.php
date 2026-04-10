<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Closure;
use PDO;

use function Forum\Helpers\json;

abstract class BaseController
{
    protected Closure $makePdo;

    public function __construct(Closure $makePdo)
    {
        $this->makePdo = $makePdo;
    }

    protected function requireAuth(Request $req, Response $res): array
    {
        $userId = $req->getAttribute('user_id');
        if ($userId === null) {
            return [json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401), null, null];
        }
        return [null, ($this->makePdo)(), (int)$userId];
    }

    protected function requireRole(int $minRole, Request $req, Response $res): array
    {
        $userId = $req->getAttribute('user_id');
        if ($userId === null) {
            return [json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401), null, null];
        }

        $pdo = ($this->makePdo)();
        $stmt = $pdo->prepare("SELECT RoleID FROM dbo.Forum_Users WHERE UserID = :uid");
        $stmt->execute([':uid' => $userId]);
        $role = (int)($stmt->fetchColumn() ?? 0);

        if ($role < $minRole) {
            $msg = $minRole >= 4 ? 'Forbidden (admin only)' : 'Forbidden';
            return [json($res, ['ok' => false, 'error' => $msg], 403), null, null];
        }

        return [null, $pdo, (int)$userId];
    }
    protected function getUserRoleId(PDO $pdo, ?int $userId): int
{
    if (!$userId || $userId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT ISNULL(RoleID, 0)
        FROM dbo.Forum_Users
        WHERE UserID = :uid
    ");
    $stmt->execute([':uid' => $userId]);

    $roleId = (int)($stmt->fetchColumn() ?? 0);
    return $roleId >= 0 ? $roleId : 0;
}

protected function getCategoryVisibilityRoleId(PDO $pdo, int $categoryId): int
{
    $stmt = $pdo->prepare("
        SELECT ISNULL(VisibleFromRoleID, 0)
        FROM dbo.Forum_Categories
        WHERE CategoryID = :categoryId
    ");
    $stmt->execute([':categoryId' => $categoryId]);

    $roleId = (int)($stmt->fetchColumn() ?? 0);
    return $roleId >= 0 ? $roleId : 0;
}

protected function canViewCategory(PDO $pdo, int $categoryId, ?int $userId): bool
{
    $userRoleId = $this->getUserRoleId($pdo, $userId);

    if ($userRoleId >= 4) {
        return true;
    }

    $visibleFromRoleId = $this->getCategoryVisibilityRoleId($pdo, $categoryId);
    return $userRoleId >= $visibleFromRoleId;
}
}
