<?php
declare(strict_types=1);

namespace Forum\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

final class TermsController
{
    public function accept(Request $req, Response $res, PDO $pdo): Response
    {
        try {
            $userId = $req->getAttribute('user_id');

            if ($userId === null) {
                return json($res, ['ok' => false, 'error' => 'Unauthorized'], 401);
            }

            $stmt = $pdo->prepare("
                UPDATE dbo.Users
                SET termsAccepted = 1,
                    termsAcceptedAt = GETDATE()
                WHERE User_ID = :uid
            ");

            $stmt->execute([':uid' => $userId]);

            return json($res, ['ok' => true], 200);
        } catch (\Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function acceptByUserId(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare("
            UPDATE dbo.Users
            SET termsAccepted = 1,
                termsAcceptedAt = GETDATE()
            WHERE User_ID = :uid
        ");

        $stmt->execute([':uid' => $userId]);
    }
}