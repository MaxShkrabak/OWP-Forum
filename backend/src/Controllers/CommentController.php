<?php

namespace Forum\Controllers;

use PDO;
use Throwable;
use Closure;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;
use function Forum\Helpers\checkUserBan;
use function Forum\Helpers\createNotification;

class CommentController extends BaseController
{
    private Closure $sendCommentEmail;

    public function __construct(Closure $makePdo, ?Closure $sendCommentEmail = null)
    {
        parent::__construct($makePdo);
        $this->sendCommentEmail = $sendCommentEmail ?? fn(array $message) => $this->dispatchCommentNotification($message);
    }

    private function formatUserRow(array $row): array
    {
        return [
            'userId'    => (int)($row['UserId'] ?? $row['UserID'] ?? 0),
            'firstName' => $row['FirstName'] ?? null,
            'lastName'  => $row['LastName'] ?? null,
            'avatar'    => $row['Avatar'] ?? null,
            'role'      => $row['RoleName'] ?? 'user'
        ];
    }

    private function maybeSendCommentNotification(PDO $pdo, int $postId, int $commenterId): void
    {
        $postOwnerStmt = $pdo->prepare("
            SELECT p.PostID, p.Title, p.AuthorID, p.LastCommentNotificationSentAt,
                   u.Email, u.FirstName, u.LastName,
                   ISNULL(u.EmailNotificationsEnabled, 1) AS EmailNotificationsEnabled
            FROM dbo.Forum_Posts p
            JOIN dbo.Forum_Users u ON u.User_ID = p.AuthorID
            WHERE p.PostID = :postId");
        $postOwnerStmt->execute([':postId' => $postId]);
        $post = $postOwnerStmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) return;
        if ((int)$post['AuthorID'] === $commenterId) return;
        if (!(bool)$post['EmailNotificationsEnabled']) return;
        if (!$this->cooldownPassed($post['LastCommentNotificationSentAt'] ?? null)) return;

        try {
            $fullName = trim(($post['FirstName'] ?? '') . ' ' . ($post['LastName'] ?? ''));
            $message = $this->buildCommentNotificationMessage($post['Email'], $fullName, $post['Title']);
            $sent = ($this->sendCommentEmail)($message);

            if ($sent) {
                $updateStmt = $pdo->prepare("UPDATE dbo.Forum_Posts SET LastCommentNotificationSentAt = SYSUTCDATETIME() WHERE PostID = :postId");
                $updateStmt->execute([':postId' => $postId]);
            }
        } catch (Throwable $e) {
            error_log("Failed to send comment notification: " . $e->getMessage());
            return;
        }
    }

    private function cooldownPassed(?string $lastSentAt): bool
    {
        if (!$lastSentAt) return true;
        $cooldownMinutes = (int)($_ENV['COMMENT_EMAIL_COOLDOWN_MINUTES'] ?? 10);
        $lastTime = strtotime($lastSentAt);

        if ($lastTime === false) return true;

        return $lastTime <= time() - ($cooldownMinutes * 60);
    }

    private function buildCommentNotificationMessage(string $email, string $name, string $postTitle): array
    {
        $fromEmail = $_ENV['EMAIL_FROM_ADDRESS'] ?? '';
        $fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'OWP Forum';

        $safeName = htmlspecialchars($name !== '' ? $name : $email, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8');

        return [
            'sender' => [
                'email' => $fromEmail,
                'name' => $fromName
            ],
            'to' => [[
                'email' => $email,
                'name' => $name !== '' ? $name : $email
            ]],
            'subject' => "New comment on your post: {$postTitle}",
            'htmlContent' => "<p>Hi {$safeName},</p><p>Your post titled <strong>{$safeTitle}</strong> has received a new comment. Visit the post to see the discussion!</p><p>Best,<br/>OWP Forum Team</p>"
        ];
    }

    private function dispatchCommentNotification(array $payload): bool
    {
        $apiKey = $_ENV['EMAIL_API_KEY'] ?? '';
        $fromEmail = $_ENV['EMAIL_FROM_ADDRESS'] ?? '';
        $useSandbox = filter_var($_ENV['EMAIL_SANDBOX'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        if ($apiKey === '' || $fromEmail === '') {
            error_log("Email API key or from address not configured. Cannot send notification.");
            return false;
        }

        $headers = [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json'
        ];

        if ($useSandbox) {
            $headers[] = 'X-Sib-Sandbox: drop';
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            error_log("cURL error while sending email: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            error_log("Failed to send email notification. Status: {$status}, Response: {$response}");
            return false;
        }

        return true;
    }
    private function getCommentRateLimitRole(PDO $pdo, int $userId): ?string
    {
        $roleStmt = $pdo->prepare("
            SELECT LOWER(r.NAME)
            FROM dbo.Forum_Users u
            LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
            WHERE u.User_ID = :uid
        ");

        $roleStmt->execute([':uid' => $userId]);
        $roleName = $roleStmt->fetchColumn();

        return is_string($roleName) ? trim($roleName) : null;
    }

    private function ApplyCommentRateLimit(?string $roleName): bool
    {
        if ($roleName === null || $roleName === '') {
            return true;
        }

        return in_array($roleName, ['user', 'student'], true);
    }

    private function getHourlyCommentResetSeconds(PDO $pdo, int $userId, int $commentsPerHourLimit): ?int
    {
        $offset = max($commentsPerHourLimit - 1, 0);

        $hourlyResetTimeStmt = $pdo->prepare("
            Select CreatedAt
            FROM dbo.Forum_Comments
            WHERE UserID = :uid
            AND isDeleted = 0
            AND CreatedAt >= DATEADD(HOUR, -1, SYSUTCDATETIME())
            ORDER BY CreatedAt DESC
            OFFSET ($offset) ROWS FETCH NEXT 1 ROWS ONLY 
        ");
        $hourlyResetTimeStmt->execute([':uid' => $userId]);
        $createdAt = $hourlyResetTimeStmt->fetchColumn();

        if (!$createdAt) {
            return null;
        }

        $limitWindowCommentTime = new \DateTimeImmutable((string)$createdAt, new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $secondsLeft = 3600 - ($now->getTimestamp() - $limitWindowCommentTime->getTimestamp());

        return max(1, $secondsLeft);
    }

    private function createCommentRateLimit(PDO $pdo, int $userId, Response $res): ?Response
    {
        $commentCooldownSeconds = 15;
        $commentsPerHourLimit = 50;

        $roleName = $this->getCommentRateLimitRole($pdo, $userId);

        if (!$this->ApplyCommentRateLimit($roleName)) {
            return null;
        }

        $lockstmt = $pdo->prepare("
            DECLARE @result INT;
            EXEC @result = sp_getapplock
                @lockOwner = 'Transaction',
                @Resource = :res,
                @LockMode = 'Exclusive',
                @LockTimeout = 5000;
            SELECT @result;
        ");
        $lockstmt->execute([':res' => "create_comment_user_$userId"]);
        $lockResult = (int)($lockstmt->fetchColumn() ?? -999);

        if ($lockResult < 0) {
            $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => 'Could not acquire lock for rate limiting'], 503);
        }

        $recentCommentStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM dbo.Forum_Comments
            WHERE UserId = :uid
              AND IsDeleted = 0
              AND CreatedAt >= DATEADD(HOUR, -1, SYSUTCDATETIME())
        ");
        $recentCommentStmt->execute([':uid' => $userId]);
        $recentComments = (int)$recentCommentStmt->fetchColumn();

        if ($recentComments >= $commentsPerHourLimit) {
            $secondsLeft = $this->getHourlyCommentResetSeconds($pdo, $userId, $commentsPerHourLimit);
            $pdo->rollBack();
            return json($res, [
                'ok' => false,
                'error' => $secondsLeft !== null
                    ? "You've reached the {$commentsPerHourLimit} comments per hour limit. Try again in {$secondsLeft} seconds."
                    : "You've reached the {$commentsPerHourLimit} comments per hour limit. Please try again soon.",
                'rateLimit' => [
                    'type' => 'hourly_limit',
                    'secondsLeft' => $secondsLeft,
                    'limit' => $commentsPerHourLimit,
                ],
            ], 429);
        }

        if ($commentCooldownSeconds <= 0) {
            return null;
        }

        $lastCommentStmt = $pdo->prepare("
            SELECT TOP 1 CreatedAt
            FROM dbo.Forum_Comments
            WHERE UserId = :uid AND IsDeleted = 0
            ORDER BY CreatedAt DESC
        ");
        $lastCommentStmt->execute([':uid' => $userId]);
        $lastCreatedAt = $lastCommentStmt->fetchColumn();

        if (!$lastCreatedAt) {
            return null;
        }

        $lastTime = new \DateTimeImmutable((string)$lastCreatedAt, new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $diffSeconds = $now->getTimestamp() - $lastTime->getTimestamp();

        if ($diffSeconds < $commentCooldownSeconds) {
            $secondsLeft = $commentCooldownSeconds - $diffSeconds;
            $pdo->rollBack();
            return json($res, [
                'ok' => false,
                'error' => "You're commenting too fast. Please wait {$secondsLeft} seconds before commenting again.",
                'rateLimit' => [
                    'type' => 'cooldown',
                    'secondsLeft' => $secondsLeft,
                ],
            ], 429);
        }

        return null;
    }

    public function createComment(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $banResponse = checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            $data = $req->getParsedBody() ?? [];
            $postId = isset($args['postId']) ? (int)$args['postId'] : (int)($data['post_id'] ?? 0);
            $content = trim((string)($data['content'] ?? ''));
            $parentCommentId = !empty($data['parentCommentId']) ? (int)$data['parentCommentId'] : null;

            if (!$postId || trim($content) === '') {
                return json($res, ['ok' => false, 'error' => 'Missing post_id or content'], 400);
            }

            $postCheckStmt = $pdo->prepare("
                SELECT IsCommentsDisabled FROM dbo.Forum_Posts WHERE PostID = :pid AND IsDeleted = 0
            ");
            $postCheckStmt->execute([':pid' => $postId]);
            $postCheck = $postCheckStmt->fetch(PDO::FETCH_ASSOC);

            if (!$postCheck) {
                return json($res, ['ok' => false, 'error' => 'Post not found.'], 404);
            }

            if ((int)$postCheck['IsCommentsDisabled'] === 1) {
                $roleStmt = $pdo->prepare("SELECT ISNULL(RoleID, 1) FROM dbo.Forum_Users WHERE User_ID = :uid");
                $roleStmt->execute([':uid' => $userId]);
                $userRoleId = (int)($roleStmt->fetchColumn() ?? 1);
                if ($userRoleId < 3) {
                    return json($res, ['ok' => false, 'error' => 'Comments are disabled on this post.'], 403);
                }
            }

            $pdo->beginTransaction();

            if ($rateLimitResponse = $this->createCommentRateLimit($pdo, (int)$userId, $res)) {
                return $rateLimitResponse;
            }

            $insertSql = "INSERT INTO dbo.Forum_Comments (PostID, UserId, Content, ParentCommentId) 
                          OUTPUT INSERTED.CommentId, INSERTED.CreatedAt 
                          VALUES (:postId, :userId, :content, :parentCommentId)";

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                ':postId' => $postId,
                ':userId' => $userId,
                ':content' => $content,
                ':parentCommentId' => $parentCommentId
            ]);
            $inserted = $stmt->fetch(PDO::FETCH_ASSOC);

            $postOwnerStmt = $pdo->prepare("
    SELECT AuthorID FROM dbo.Forum_Posts WHERE PostID = :postId
");
            $postOwnerStmt->execute([':postId' => $postId]);
            $postOwner = $postOwnerStmt->fetch(PDO::FETCH_ASSOC);

            if ($postOwner) {
                $postOwnerId = (int)($postOwner['AuthorID'] ?? 0);
                if ($postOwnerId > 0 && $postOwnerId !== (int)$userId) {
                    createNotification($pdo, $postOwnerId, $postId, 'postReply');
                }
            }

            $commentDetailsSql = $pdo->prepare("
                SELECT c.CommentId, c.PostId, c.ParentCommentId, c.Content, c.CreatedAt, c.UpdatedAt, c.UserId, c.TotalScore,
                       u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                       0 AS MyVote,
                       (SELECT COUNT(*) FROM dbo.Forum_Comments r WHERE r.ParentCommentId = c.CommentId AND r.IsDeleted = 0) AS ReplyCount
                FROM dbo.Forum_Comments c
                JOIN dbo.Forum_Users u ON u.User_ID = c.UserId
                JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                WHERE c.CommentId = :commentId
            ");
            $commentDetailsSql->execute([':commentId' => (int)$inserted['CommentId']]);
            $row = $commentDetailsSql->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Failed to load created comment'], 500);
            }

            $pdo->commit();
            $this->maybeSendCommentNotification($pdo, $postId, (int)$userId);

            return json($res, [
                'ok' => true,
                'comment' => [
                    'commentId' => (int)$row['CommentId'],
                    'postId'    => (int)$row['PostId'],
                    'score'     => (int)$row['TotalScore'],
                    'myVote'    => 0,
                    'user'      => $this->formatUserRow($row),
                    'content'   => $row['Content'],
                    'createdAt' => strtotime($row['CreatedAt']),
                    'updatedAt' => isset($row['UpdatedAt']) && $row['UpdatedAt'] !== null
                        ? strtotime($row['UpdatedAt'])
                        : null,
                    'replyCount' => (int)$row['ReplyCount'],
                    'parentCommentId' => $row['ParentCommentId'] ? (int)$row['ParentCommentId'] : null,
                    'isDeleted' => false
                ]
            ], 201);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getPostComments(Request $req, Response $res, array $args): Response
    {
        try {
            $postId = (int)$args['postId'];
            $userId = $req->getAttribute("user_id") ?? 0;

            $queryParams = $req->getQueryParams();
            $limit = min(max((int)($queryParams['limit'] ?? 50), 1), 100);
            $page = max((int)($queryParams['page'] ?? 1), 1);
            $offset = ($page - 1) * $limit;

            $sort = isset($queryParams['sort']) ? (string)$queryParams['sort'] : 'latest';
            switch ($sort) {
                case 'oldest':
                    $orderBy = 'c.CreatedAt ASC';
                    break;
                case 'mostLiked':
                    $orderBy = 'c.TotalScore DESC, c.CreatedAt DESC';
                    break;
                case 'latest':
                default:
                    $orderBy = 'c.CreatedAt DESC';
                    break;
            }

            $pdo = ($this->makePdo)();

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dbo.Forum_Comments WHERE PostId = :postId AND IsDeleted = 0");
            $countStmt->execute([':postId' => $postId]);
            $totalComments = (int)$countStmt->fetchColumn();

            $sql = "SELECT c.CommentId, c.PostId, c.ParentCommentId, c.Content, c.CreatedAt, c.UpdatedAt, c.UserId, c.TotalScore, c.IsDeleted,
                           u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                           ISNULL(cv.VoteValue, 0) AS MyVote,
                           (SELECT COUNT(*) FROM dbo.Forum_Comments cr WHERE cr.ParentCommentId = c.CommentId AND (cr.IsDeleted = 0 OR (SELECT COUNT(*) FROM dbo.Forum_Comments sub WHERE sub.ParentCommentId = cr.CommentId AND sub.IsDeleted = 0) > 0)) AS ReplyCount
                    FROM dbo.Forum_Comments c
                    JOIN dbo.Forum_Users u ON u.User_ID = c.UserId
                    JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                    LEFT JOIN dbo.Forum_CommentVotes cv ON cv.CommentId = c.CommentId AND cv.UserId = :currentUserId
                    WHERE c.PostId = :postId AND c.ParentCommentId IS NULL
                      AND (c.IsDeleted = 0 OR (SELECT COUNT(*) FROM dbo.Forum_Comments cr WHERE cr.ParentCommentId = c.CommentId AND (cr.IsDeleted = 0 OR (SELECT COUNT(*) FROM dbo.Forum_Comments sub WHERE sub.ParentCommentId = cr.CommentId AND sub.IsDeleted = 0) > 0)) > 0)
                    ORDER BY {$orderBy}
                    OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':postId', $postId, PDO::PARAM_INT);
            $stmt->bindValue(':currentUserId', (int)$userId, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $items = array_map(function ($row) {
                $isDeleted = (int)$row['IsDeleted'] === 1;
                return [
                    'commentId' => (int)$row['CommentId'],
                    'postId'    => (int)$row['PostId'],
                    'score'     => $isDeleted ? 0 : (int)$row['TotalScore'],
                    'myVote'    => $isDeleted ? 0 : (int)$row['MyVote'],
                    'user'      => $isDeleted ? null : $this->formatUserRow($row),
                    'content'   => $isDeleted ? null : $row['Content'],
                    'createdAt' => strtotime($row['CreatedAt']),
                    'updatedAt' => isset($row['UpdatedAt']) && $row['UpdatedAt'] !== null
                        ? strtotime($row['UpdatedAt'])
                        : null,
                    'replyCount' => (int)$row['ReplyCount'],
                    'parentCommentId' => $row['ParentCommentId'] ? (int)$row['ParentCommentId'] : null,
                    'isDeleted' => $isDeleted
                ];
            }, $rows);

            return json($res, ['ok' => true, 'items' => $items, 'total' => $totalComments]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteComment(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $commentId = (int)$args['id'];

            $commentStmt = $pdo->prepare("
                SELECT c.CommentId, c.UserId, c.IsDeleted, r.Name AS RequesterRole
                FROM dbo.Forum_Comments c
                CROSS APPLY (
                    SELECT rr.Name
                    FROM dbo.Forum_Users u
                    LEFT JOIN dbo.Forum_Roles rr ON u.RoleID = rr.RoleID
                    WHERE u.User_ID = :uid
                ) r
                WHERE c.CommentId = :id
            ");
            $commentStmt->execute([
                ':id' => $commentId,
                ':uid' => (int)$userId
            ]);
            $row = $commentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (int)$row['IsDeleted'] === 1) {
                return json($res, ['ok' => false, 'error' => 'Comment not found'], 404);
            }

            $isOwner = (int)$row['UserId'] === (int)$userId;
            $role = strtolower((string)($row['RequesterRole'] ?? ''));
            $isModeratorOrAdmin = in_array($role, ['moderator', 'admin'], true);

            if (!$isOwner && !$isModeratorOrAdmin) {
                return json($res, ['ok' => false, 'error' => 'You cannot delete this comment'], 403);
            }

            $stmt = $pdo->prepare("UPDATE dbo.Forum_Comments SET IsDeleted = 1, DeletedAt = SYSUTCDATETIME() WHERE CommentId = :id AND IsDeleted = 0");
            $stmt->execute([':id' => $commentId]);

            if ($stmt->rowCount() === 0) {
                return json($res, ['ok' => false, 'error' => 'Failed to delete comment'], 500);
            }

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getReplies(Request $req, Response $res, array $args): Response
    {
        try {
            $parentId = (int)$args['parentId'];
            $userId = $req->getAttribute("user_id") ?? 0;
            $pdo = ($this->makePdo)();

            $sql = "SELECT c.CommentId, c.PostId, c.ParentCommentId, c.Content, c.CreatedAt, c.UpdatedAt, c.UserId, c.TotalScore, c.IsDeleted,
                           u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                           ISNULL(cv.VoteValue, 0) AS MyVote,
                           (SELECT COUNT(*) FROM dbo.Forum_Comments cr WHERE cr.ParentCommentId = c.CommentId AND (cr.IsDeleted = 0 OR (SELECT COUNT(*) FROM dbo.Forum_Comments sub WHERE sub.ParentCommentId = cr.CommentId AND sub.IsDeleted = 0) > 0)) AS ReplyCount
                    FROM dbo.Forum_Comments c
                    JOIN dbo.Forum_Users u ON u.User_ID = c.UserId
                    JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                    LEFT JOIN dbo.Forum_CommentVotes cv ON cv.CommentId = c.CommentId AND cv.UserId = :currentUserId
                    WHERE c.ParentCommentId = :parentId
                      AND (c.IsDeleted = 0 OR (SELECT COUNT(*) FROM dbo.Forum_Comments cr WHERE cr.ParentCommentId = c.CommentId AND cr.IsDeleted = 0) > 0)
                    ORDER BY c.CreatedAt ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':parentId' => $parentId, ':currentUserId' => (int)$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $items = array_map(function ($row) {
                $isDeleted = (int)$row['IsDeleted'] === 1;
                return [
                    'commentId' => (int)$row['CommentId'],
                    'postId'    => (int)$row['PostId'],
                    'score'     => $isDeleted ? 0 : (int)$row['TotalScore'],
                    'myVote'    => $isDeleted ? 0 : (int)$row['MyVote'],
                    'user'      => $isDeleted ? null : $this->formatUserRow($row),
                    'content'   => $isDeleted ? null : $row['Content'],
                    'createdAt' => strtotime($row['CreatedAt']),
                    'updatedAt' => isset($row['UpdatedAt']) && $row['UpdatedAt'] !== null
                        ? strtotime($row['UpdatedAt'])
                        : null,
                    'replyCount' => (int)$row['ReplyCount'],
                    'parentCommentId' => (int)$row['ParentCommentId'],
                    'isDeleted' => $isDeleted
                ];
            }, $rows);

            return json($res, ['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateComment(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $commentId = (int)($args['id'] ?? 0);
            if ($commentId <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid comment id'], 400);
            }

            $data = $req->getParsedBody() ?? [];
            $content = trim((string)($data['content'] ?? ''));
            if ($content === '') {
                return json($res, ['ok' => false, 'error' => 'Content cannot be empty'], 400);
            }

            $banResponse = checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            $stmt = $pdo->prepare("SELECT CommentId, UserId, IsDeleted FROM dbo.Forum_Comments WHERE CommentId = :id");
            $stmt->execute([':id' => $commentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (int)$row['IsDeleted'] === 1) {
                return json($res, ['ok' => false, 'error' => 'Comment not found'], 404);
            }

            if ((int)$row['UserId'] !== (int)$userId) {
                return json($res, ['ok' => false, 'error' => 'You cannot edit this comment'], 403);
            }

            $update = $pdo->prepare("
                UPDATE dbo.Forum_Comments
                SET Content = :content,
                    UpdatedAt = SYSUTCDATETIME()
                WHERE CommentId = :id AND IsDeleted = 0
            ");
            $update->execute([
                ':content' => $content,
                ':id' => $commentId
            ]);

            if ($update->rowCount() === 0) {
                return json($res, ['ok' => false, 'error' => 'Failed to update comment'], 500);
            }

            $detailsStmt = $pdo->prepare("
                SELECT c.CommentId, c.PostId, c.ParentCommentId, c.Content, c.CreatedAt, c.UpdatedAt, c.UserId, c.TotalScore,
                       u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                       0 AS MyVote,
                       (SELECT COUNT(*) FROM dbo.Forum_Comments r WHERE r.ParentCommentId = c.CommentId AND r.IsDeleted = 0) AS ReplyCount
                FROM dbo.Forum_Comments c
                JOIN dbo.Forum_Users u ON u.User_ID = c.UserId
                JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                WHERE c.CommentId = :commentId
            ");
            $detailsStmt->execute([':commentId' => $commentId]);
            $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);

            if (!$details) {
                return json($res, ['ok' => false, 'error' => 'Failed to load updated comment'], 500);
            }

            return json($res, [
                'ok' => true,
                'comment' => [
                    'commentId' => (int)$details['CommentId'],
                    'postId'    => (int)$details['PostId'],
                    'score'     => (int)$details['TotalScore'],
                    'myVote'    => 0,
                    'user'      => $this->formatUserRow($details),
                    'content'   => $details['Content'],
                    'createdAt' => strtotime($details['CreatedAt']),
                    'updatedAt' => isset($details['UpdatedAt']) && $details['UpdatedAt'] !== null
                        ? strtotime($details['UpdatedAt'])
                        : null,
                    'replyCount' => (int)$details['ReplyCount'],
                    'parentCommentId' => $details['ParentCommentId'] ? (int)$details['ParentCommentId'] : null,
                    'isDeleted' => false
                ]
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function vote(Request $req, Response $res, array $args): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $commentId = (int)($args['id'] ?? 0);
            $data = $req->getParsedBody() ?? [];

            $action = strtolower((string)($data['dir'] ?? $data['action'] ?? ''));

            $banResponse = checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            $pdo->beginTransaction();

            $del = $pdo->prepare("DELETE FROM dbo.Forum_CommentVotes WHERE CommentId = :cid AND UserId = :uid");
            $del->execute([':cid' => $commentId, ':uid' => (int)$userId]);

            $newVoteValue = 0;
            if ($action === 'upvote') {
                $newVoteValue = 1;
            } elseif ($action === 'downvote') {
                $newVoteValue = -1;
            }

            if ($newVoteValue !== 0) {
                $ins = $pdo->prepare("INSERT INTO dbo.Forum_CommentVotes (CommentId, UserId, VoteValue) VALUES (:cid, :uid, :val)");
                $ins->execute([':cid' => $commentId, ':uid' => (int)$userId, ':val' => $newVoteValue]);
            }

            $pdo->commit();

            $scoreStmt = $pdo->prepare("SELECT TotalScore FROM dbo.Forum_Comments WHERE CommentId = :cid");
            $scoreStmt->execute([':cid' => $commentId]);
            $totalScore = (int)$scoreStmt->fetchColumn();

            return json($res, [
                'ok' => true,
                'score' => $totalScore,
                'myVote' => $newVoteValue
            ]);
        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}