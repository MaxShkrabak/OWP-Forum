<?php
namespace Forum\Helpers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

if (!function_exists('Forum\Helpers\json')) {
    function json(Response $res, array $data, int $status = 200): Response {
        $res->getBody()->write(json_encode($data));
        return $res
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}

if (!function_exists('Forum\Helpers\resolveReportsForPost')) {
    function resolveReportsForPost(\PDO $pdo, int $postId, int $resolvedByUserId): void
    {
        // Resolve reports directly on the post
        $pdo->prepare("
            UPDATE dbo.Reports
            SET Resolved = 1,
                ResolvedBy = :uid,
                ResolvedAt = SYSUTCDATETIME()
            WHERE Resolved = 0
              AND PostID = :pid
        ")->execute([':uid' => $resolvedByUserId, ':pid' => $postId]);

        // Resolve reports for any comments/replies under that post
        $pdo->prepare("
            UPDATE dbo.Reports
            SET Resolved = 1,
                ResolvedBy = :uid,
                ResolvedAt = SYSUTCDATETIME()
            WHERE Resolved = 0
              AND CommentID IN (SELECT CommentID FROM dbo.Comments WHERE PostID = :pid)
        ")->execute([':uid' => $resolvedByUserId, ':pid' => $postId]);
    }
}

if (!function_exists('Forum\Helpers\softDeleteCommentsForPost')) {
    function softDeleteCommentsForPost(\PDO $pdo, int $postId): void
    {
        $pdo->prepare("
            UPDATE dbo.Comments
            SET IsDeleted = 1,
                DeletedAt = SYSUTCDATETIME()
            WHERE PostID = :pid
              AND IsDeleted = 0
        ")->execute([':pid' => $postId]);
    }
}

if (!function_exists('Forum\Helpers\setSessionCookie')) {
    function setSessionCookie(string $rawToken): void {
        setcookie('session', $rawToken, [
            'expires'  => time() + 86400, // 24 hours
            'path'     => '/',
            'domain'   => 'localhost',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

if (!function_exists('Forum\Helpers\clearSessionCookie')) {
    function clearSessionCookie(): void {
        setcookie('session', '', [
            'expires'  => time() - 3600, // One hour ago
            'path'     => '/',
            'domain'   => 'localhost',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

if (!function_exists('Forum\Helpers\checkUserBan')) {
    function checkUserBan(\PDO $pdo, int $userId, Response $res): ?Response {
        $stmt = $pdo->prepare("
            SELECT 
                ISNULL(IsBanned, 0) AS IsBanned,
                BanType, 
                BannedUntil 
            FROM dbo.Users WHERE User_ID = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && (int)$user['IsBanned'] === 1) {
            $banType = $user['BanType'] ? trim((string)$user['BanType']) : null;
            $bannedUntil = $user['BannedUntil'] ?? null;

            $isRestricted = ($banType !== 'temporary' || !$bannedUntil) 
                || (new \DateTimeImmutable($bannedUntil, new \DateTimeZone('UTC')) > new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

            if ($isRestricted) {
                $msg = 'Your account is restricted from performing this action.';
                if ($banType === 'temporary' && $bannedUntil) {
                    $msg .= " Banned until: " . $bannedUntil;
                }
                return json($res, ['ok' => false, 'error' => $msg], 403);
            }
        }
        return null;
    }
}

if (!function_exists('Forum\Helpers\requireTermsAccepted')) {
    function requireTermsAccepted(Request $req, Response $res, \PDO $pdo): ?Response {
        $userId = $req->getAttribute('user_id');

        if ($userId === null) {
            return json($res, ['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $stmt = $pdo->prepare("
            SELECT ISNULL(termsAccepted, 0) AS termsAccepted
            FROM dbo.Users
            WHERE User_ID = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $accepted = (int)($row['termsAccepted'] ?? 0);

        if ($accepted === 0) {
            return json($res, ['ok' => false, 'error' => 'Terms not accepted'], 403);
        }

        return null;
    }
}