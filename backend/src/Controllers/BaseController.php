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
        return [null, ($this->makePdo)(), $userId];
    }

    protected function requireRole(int $minRole, Request $req, Response $res): array
    {
        $userId = $req->getAttribute('user_id');
        if ($userId === null) {
            return [json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401), null, null];
        }

        $pdo = ($this->makePdo)();
        $stmt = $pdo->prepare("SELECT RoleID FROM dbo.Forum_Users WHERE User_ID = :uid");
        $stmt->execute([':uid' => $userId]);
        $role = (int)($stmt->fetchColumn() ?? 0);

        if ($role < $minRole) {
            $msg = $minRole >= 4 ? 'Forbidden (admin only)' : 'Forbidden';
            return [json($res, ['ok' => false, 'error' => $msg], 403), null, null];
        }

        return [null, $pdo, $userId];
    }
}
