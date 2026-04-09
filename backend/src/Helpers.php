<?php
namespace Forum\Helpers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Throwable;

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
            UPDATE dbo.Forum_Reports
            SET Resolved = 1,
                ResolvedBy = :uid,
                ResolvedAt = SYSUTCDATETIME()
            WHERE Resolved = 0
              AND PostID = :pid
        ")->execute([':uid' => $resolvedByUserId, ':pid' => $postId]);

        // Resolve reports for any comments/replies under that post
        $pdo->prepare("
            UPDATE dbo.Forum_Reports
            SET Resolved = 1,
                ResolvedBy = :uid,
                ResolvedAt = SYSUTCDATETIME()
            WHERE Resolved = 0
              AND CommentID IN (SELECT CommentID FROM dbo.Forum_Comments WHERE PostID = :pid)
        ")->execute([':uid' => $resolvedByUserId, ':pid' => $postId]);
    }
}

if (!function_exists('Forum\Helpers\softDeleteCommentsForPost')) {
    function softDeleteCommentsForPost(\PDO $pdo, int $postId): void
    {
        $pdo->prepare("
            UPDATE dbo.Forum_Comments
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
            FROM dbo.Forum_Users WHERE User_ID = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && (int)$user['IsBanned'] === 1) {
            $banType = $user['BanType'] ? trim((string)$user['BanType']) : null;
            $bannedUntil = $user['BannedUntil'] ?? null;

            if ($banType !== 'temporary' || !$bannedUntil) {
                $msg = 'Your account is restricted from performing this action.';
                if ($banType === 'temporary' && $bannedUntil) {
                    $msg .= " Banned until: " . $bannedUntil;
                }
                return json($res, ['ok' => false, 'error' => $msg], 403);
            }

            $banUntilDate = new \DateTimeImmutable($bannedUntil, new \DateTimeZone('UTC'));
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            // Temporary ban active
            if ($banUntilDate > $now) {
                $msg = 'Your account is restricted from performing this action. Banned until: ' . $bannedUntil;
                return json($res, ['ok' => false, 'error' => $msg], 403);
            }

            // Clear temporary ban since it has expired
            $clearStmt = $pdo->prepare("
                UPDATE dbo.Forum_Users
                SET IsBanned = 0,
                    BanType = NULL,
                    BannedUntil = NULL
                WHERE User_ID = :uid
            ");
            $clearStmt->execute([':uid' => $userId]);
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
            FROM dbo.Forum_Users
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

if (!function_exists('Forum\\Helpers\\createNotification')) {
    function createNotification(PDO $pdo, int $userId, int $postId, string $type): bool
    {
        if ($userId <= 0 || $postId <= 0) return false;
        if (!in_array($type, ['postLike', 'postReply'], true)) return false;

        try {
            $sql = "
                IF NOT EXISTS (
                    SELECT 1
                    FROM dbo.Forum_Notifications
                    WHERE UserID = ?
                      AND PostID = ?
                      AND [Type] = ?
                      AND IsRead = 0
                )
                BEGIN
                    INSERT INTO dbo.Forum_Notifications (UserID, PostID, [Type], IsRead)
                    VALUES (?, ?, ?, 0)
                END
            ";

            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $userId,
                $postId,
                $type,
                $userId,
                $postId,
                $type,
            ]);
        } catch (Throwable $e) {
            error_log('createNotification failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('Forum\\Helpers\\markNotificationsRead')) {
    function markNotificationsRead(PDO $pdo, int $userId, array $notificationIds): bool
    {
        $ids = array_values(array_filter(array_map('intval', $notificationIds), fn($v) => $v > 0));
        if ($userId <= 0 || empty($ids)) return false;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
            UPDATE dbo.Forum_Notifications
            SET IsRead = 1
            WHERE UserID = ? AND NotificationID IN ($placeholders)
        ";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute(array_merge([$userId], $ids));
    }
}

if (!function_exists('Forum\Helpers\fetchTagNamesByPostIds')) {
    function fetchTagNamesByPostIds(PDO $pdo, array $postIds): array
    {
        if (empty($postIds)) return [];
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $pdo->prepare("
            SELECT pt.PostID, t.Name
            FROM dbo.Forum_PostTags pt
            JOIN dbo.Forum_Tags t ON t.TagID = pt.TagID
            WHERE pt.PostID IN ($placeholders)
            ORDER BY t.Name ASC
        ");
        $stmt->execute($postIds);
        $tagsByPostId = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tagsByPostId[(int)$row['PostID']][] = $row['Name'];
        }
        return $tagsByPostId;
    }
}

if (!function_exists('Forum\Helpers\fetchCounts')) {
    function fetchCounts(PDO $pdo, string $table, string $placeholders, array $postIds, string $countAlias): array
    {
        try {
            $sql = "
                SELECT PostID, COUNT(*) AS $countAlias
                FROM $table
                WHERE PostID IN ($placeholders)
                GROUP BY PostID
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($postIds);

            $counts = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $counts[(int)$row['PostID']] = (int)$row[$countAlias];
            }
            return $counts;
        } catch (Throwable $e) {
            return [];
        }
    }
}