<?php

declare(strict_types=1);

namespace Forum\Controllers;

use PDO;
use Throwable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;
use function Forum\Helpers\markNotificationsRead;
use function Forum\Helpers\fetchCounts;
use function Forum\Helpers\fetchTagNamesByPostIds;

final class UserController extends BaseController
{
    public function updateAvatar(Request $req, Response $res): Response
    {
        try {
            $userId = $req->getAttribute('user_id');
            $data = $req->getParsedBody();
            $newAvatarPath = trim((string)($data['avatar'] ?? ''));

            if ($newAvatarPath === '') {
                return json($res, ['ok' => false, 'error' => 'No avatar provided'], 400);
            }

            $avatarFilename = basename($newAvatarPath);
            $pdo = ($this->makePdo)();

            $pdo->prepare("
                UPDATE dbo.Forum_Users SET Avatar = :avatar WHERE User_ID = :uid
            ")->execute([':avatar' => $avatarFilename, ':uid' => $userId]);

            return json($res, [
                'ok' => true,
                'message' => 'Avatar updated successfully',
                'newAvatar' => $avatarFilename,
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getNotificationSettings(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;
            $stmt = $pdo->prepare("
                SELECT ISNULL(EmailNotificationsEnabled, 1) as EmailNotificationsEnabled
                FROM dbo.Forum_Users WHERE User_ID = :uid
            ");
            $stmt->execute([':uid' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return json($res, ['ok' => false, 'error' => 'User not found'], 404);
            }

            return json($res, [
                'ok' => true,
                'settings' => [
                    'emailNotifications' => (bool)$result['EmailNotificationsEnabled'],
                ],
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateNotificationSettings(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $data = $req->getParsedBody() ?? [];
            if (!array_key_exists('emailNotifications', $data)) {
                return json($res, ['ok' => false, 'error' => 'Invalid emailNotifications value'], 400);
            }

            $emailNotifications = filter_var($data['emailNotifications'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($emailNotifications === null) {
                return json($res, ['ok' => false, 'error' => 'Invalid emailNotifications value'], 400);
            }

            $pdo->prepare("
                UPDATE dbo.Forum_Users SET EmailNotificationsEnabled = :enabled WHERE User_ID = :uid
            ")->execute([':enabled' => $emailNotifications ? 1 : 0, ':uid' => $userId]);

            return json($res, [
                'ok' => true,
                'settings' => ['emailNotifications' => $emailNotifications],
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getNotifications(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $sql = "
                SELECT TOP 20
                    n.NotificationID,
                    n.PostID,
                    n.[Type],
                    n.IsRead,
                    n.CreatedAt,
                    p.Title
                FROM dbo.Forum_Notifications n
                JOIN dbo.Forum_Posts p ON p.PostID = n.PostID
                WHERE n.UserID = :uid
                  AND n.IsRead = 0
                  AND p.IsDeleted = 0
                ORDER BY n.CreatedAt DESC, n.NotificationID DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $items = array_map(function ($row) {
                return [
                    'notificationId' => (int)$row['NotificationID'],
                    'postId'         => (int)$row['PostID'],
                    'type'           => (string)$row['Type'],
                    'isRead'         => (bool)$row['IsRead'],
                    'title'          => (string)$row['Title'],
                    'createdAt'      => $row['CreatedAt']
                ];
            }, $rows);

            return json($res, ['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function markNotificationsRead(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $data = $req->getParsedBody() ?? [];
            $notificationIds = is_array($data['notificationIds'] ?? null) ? $data['notificationIds'] : [];

            if (empty($notificationIds)) {
                return json($res, ['ok' => false, 'error' => 'notificationIds is required'], 400);
            }

            $ok = markNotificationsRead($pdo, $userId, $notificationIds);

            return json($res, ['ok' => $ok]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function acceptTerms(Request $req, Response $res): Response
    {
        try {
            [$err, $pdo, $userId] = $this->requireAuth($req, $res);
            if ($err !== null) return $err;

            $pdo->prepare("
                UPDATE dbo.Forum_Users
                SET termsAccepted = 1, termsAcceptedAt = GETDATE()
                WHERE User_ID = :uid
            ")->execute([':uid' => $userId]);

            return json($res, ['ok' => true], 200);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function acceptTermsByUserId(PDO $pdo, int $userId): void
    {
        $pdo->prepare("
            UPDATE dbo.Forum_Users
            SET termsAccepted = 1, termsAcceptedAt = GETDATE()
            WHERE User_ID = :uid
        ")->execute([':uid' => $userId]);
    }

    public function getProfile(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = (int)$args['uid'];
            $pdo = ($this->makePdo)();

            $stmt = $pdo->prepare("
                SELECT User_ID, FirstName, LastName, Avatar, Name AS RoleName
                FROM dbo.Forum_Users u
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                WHERE User_ID = :uid
            ");
            $stmt->execute([':uid' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return json($res, ['ok' => false, 'error' => 'User not found'], 404);
            }

            return json($res, ['ok' => true, 'user' => [
                'userId'    => (int)$user['User_ID'],
                'firstName' => $user['FirstName'],
                'lastName'  => $user['LastName'],
                'avatar'    => $user['Avatar'],
                'roleName'  => $user['RoleName'],
            ]]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getProfileStats(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = (int)$args['uid'];
            $pdo = ($this->makePdo)();

            $stmt = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM dbo.Forum_Posts WHERE AuthorID = :uid1 AND IsDeleted = 0) AS postCount,
                    (SELECT COALESCE(SUM(pv.VoteValue), 0) FROM dbo.Forum_PostVotes pv INNER JOIN dbo.Forum_Posts p ON pv.PostID = p.PostID WHERE p.AuthorID = :uid2 AND p.IsDeleted = 0) AS voteScore,
                    (SELECT COUNT(*) FROM dbo.Forum_Comments WHERE UserID = :uid3 AND IsDeleted = 0) AS commentCount
            ");
            $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return json($res, [
                'ok' => true,
                'stats' => [
                    'postCount'    => (int)$row['postCount'],
                    'voteScore'    => (int)$row['voteScore'],
                    'commentCount' => (int)$row['commentCount'],
                ],
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getProfilePosts(Request $req, Response $res, array $args): Response
    {
        try {
            $authorId     = (int)$args['uid'];
            $viewerUserId = (int)($req->getAttribute('user_id') ?? 0);
            $pdo = ($this->makePdo)();

            $params = $req->getQueryParams();
            $limit = min(max((int)($params['limit'] ?? 5), 1), 50);
            $page  = max((int)($params['page'] ?? 1), 1);

            $sort = strtolower($params['sort'] ?? 'latest');
            $orderBy = match ($sort) {
                'oldest'   => 'p.CreatedAt ASC',
                'title'    => 'p.Title ASC',
                'upvotes'  => 'p.TotalScore DESC, p.CreatedAt DESC',
                'comments' => 'commentCount DESC, p.CreatedAt DESC',
                default    => 'p.CreatedAt DESC',
            };

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dbo.Forum_Posts WHERE AuthorID = :uid AND IsDeleted = 0");
            $countStmt->execute([':uid' => $authorId]);
            $totalPosts = (int)$countStmt->fetchColumn();

            $totalPages = (int)ceil($totalPosts / $limit);
            $page = ($page > $totalPages && $totalPages > 0) ? $totalPages : $page;
            $offset = ($page - 1) * $limit;

            $getPostsSql = "
                SELECT p.AuthorID, p.PostID, p.Title, p.CreatedAt, p.CategoryID, p.TotalScore,
                       (SELECT COUNT(*) FROM dbo.Forum_Comments cm WHERE cm.PostID = p.PostID AND cm.IsDeleted = 0) AS commentCount,
                       u.FirstName, u.LastName, u.Avatar, u.User_ID,
                       r.Name AS RoleName, c.Name AS CategoryName,
                       ISNULL(pv.VoteValue, 0) AS myVote,
                       CASE WHEN pin.PostID IS NOT NULL THEN 1 ELSE 0 END AS isPinned
                FROM dbo.Forum_Posts p
                LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.User_ID
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
                LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.User_ID = :viewerId
                LEFT JOIN dbo.Forum_Pinned pin ON p.PostID = pin.PostID
                WHERE p.AuthorID = :uid AND p.IsDeleted = 0
                ORDER BY $orderBy
                OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
            ";

            $rowstmt = $pdo->prepare($getPostsSql);
            $rowstmt->execute([':uid' => $authorId, ':viewerId' => $viewerUserId]);
            $rows = $rowstmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return json($res, ['posts' => [], 'postsByCategory' => [], 'totalPosts' => 0]);
            }

            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            $tagsByPostId = fetchTagNamesByPostIds($pdo, $postIds);
            $likeCounts = fetchCounts($pdo, 'dbo.PostLikes', $placeholders, $postIds, 'LikeCount');

            $posts = [];
            $categoriesMap = [];

            foreach ($rows as $row) {
                $pid = (int)$row['PostID'];
                $catId = (int)$row['CategoryID'];

                $post = [
                    'postId'       => $pid,
                    'categoryId'   => $catId,
                    'title'        => $row['Title'],
                    'createdAt'    => $row['CreatedAt'],
                    'authorId'     => (int)($row['User_ID'] ?? 0),
                    'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole'   => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags'         => $tagsByPostId[$pid] ?? [],
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'likeCount'    => $likeCounts[$pid] ?? 0,
                    'totalScore'   => (int)($row['TotalScore'] ?? 0),
                    'myVote'       => (int)($row['myVote'] ?? 0),
                    'isPinned'     => (bool)($row['isPinned'] ?? false),
                ];

                $posts[] = $post;

                if (!isset($categoriesMap[$catId])) {
                    $categoriesMap[$catId] = [
                        'categoryId'   => $catId,
                        'categoryName' => $row['CategoryName'] ?? 'Uncategorized',
                        'posts'        => [],
                    ];
                }
                $categoriesMap[$catId]['posts'][] = $post;
            }

            $postsByCategory = array_values($categoriesMap);
            usort($postsByCategory, fn($a, $b) => strcmp($a['categoryName'], $b['categoryName']));

            return json($res, [
                'posts'           => $posts,
                'postsByCategory' => $postsByCategory,
                'totalPosts'      => count($posts),
                'meta'            => [
                    'limit'      => $limit,
                    'sort'       => ($sort === 'oldest' || $sort === 'title') ? $sort : 'latest',
                    'page'       => $page,
                    'totalPosts' => $totalPosts,
                    'totalPages' => $totalPages,
                ],
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getProfileLikedPosts(Request $req, Response $res, array $args): Response
    {
        try {
            $profileUserId = (int)$args['uid'];
            $viewerUserId  = (int)($req->getAttribute('user_id') ?? 0);

            $pdo = ($this->makePdo)();

            $params = $req->getQueryParams();
            $limit = min(max((int)($params['limit'] ?? 5), 1), 50);
            $page  = max((int)($params['page'] ?? 1), 1);

            $sort = strtolower($params['sort'] ?? 'latest');
            $orderBy = match ($sort) {
                'oldest'   => 'p.CreatedAt ASC',
                'upvotes'  => 'p.TotalScore DESC, p.CreatedAt DESC',
                'comments' => 'commentCount DESC, p.CreatedAt DESC',
                default    => 'p.CreatedAt DESC',
            };

            $countStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM dbo.Forum_PostVotes pov
                JOIN dbo.Forum_Posts p ON p.PostID = pov.PostID
                WHERE pov.User_ID = :uid AND pov.VoteValue = 1 AND p.IsDeleted = 0
            ");
            $countStmt->execute([':uid' => $profileUserId]);
            $totalPosts = (int)$countStmt->fetchColumn();

            $totalPages = (int)ceil($totalPosts / $limit);
            $page = ($page > $totalPages && $totalPages > 0) ? $totalPages : $page;
            $offset = ($page - 1) * $limit;

            $stmt = $pdo->prepare("
                SELECT p.PostID, p.Title, p.CreatedAt, p.CategoryID, p.TotalScore,
                       (SELECT COUNT(*) FROM dbo.Forum_Comments cm WHERE cm.PostID = p.PostID AND cm.IsDeleted = 0) AS commentCount,
                       u.FirstName, u.LastName, u.Avatar, u.User_ID,
                       r.Name AS RoleName, c.Name AS CategoryName,
                       ISNULL(pv.VoteValue, 0) AS myVote,
                       CASE WHEN pin.PostID IS NOT NULL THEN 1 ELSE 0 END AS isPinned
                FROM dbo.Forum_PostVotes pov
                JOIN dbo.Forum_Posts p ON p.PostID = pov.PostID
                LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.User_ID
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
                LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.User_ID = :viewerId
                LEFT JOIN dbo.Forum_Pinned pin ON p.PostID = pin.PostID
                WHERE pov.User_ID = :profileId AND pov.VoteValue = 1 AND p.IsDeleted = 0
                ORDER BY $orderBy
                OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
            ");
            $stmt->bindValue(':viewerId', $viewerUserId, PDO::PARAM_INT);
            $stmt->bindValue(':profileId', $profileUserId, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return json($res, [
                    'ok' => true,
                    'posts' => [],
                    'meta' => [
                        'limit'      => $limit,
                        'sort'       => $sort,
                        'page'       => $page,
                        'totalPosts' => $totalPosts,
                        'totalPages' => $totalPages,
                    ],
                ]);
            }

            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            $tagsByPostId = fetchTagNamesByPostIds($pdo, $postIds);
            $likeCounts = fetchCounts($pdo, 'dbo.PostLikes', $placeholders, $postIds, 'LikeCount');

            $posts = [];
            foreach ($rows as $row) {
                $pid = (int)$row['PostID'];
                $posts[] = [
                    'postId'       => $pid,
                    'categoryId'   => (int)($row['CategoryID'] ?? 0),
                    'title'        => $row['Title'],
                    'createdAt'    => $row['CreatedAt'],
                    'authorId'     => (int)($row['User_ID'] ?? 0),
                    'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole'   => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags'         => $tagsByPostId[$pid] ?? [],
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'likeCount'    => $likeCounts[$pid] ?? 0,
                    'totalScore'   => (int)($row['TotalScore'] ?? 0),
                    'myVote'       => (int)($row['myVote'] ?? 0),
                    'isPinned'     => (bool)($row['isPinned'] ?? false),
                ];
            }

            return json($res, [
                'ok' => true,
                'posts' => $posts,
                'meta' => [
                    'limit'      => $limit,
                    'sort'       => $sort,
                    'page'       => $page,
                    'totalPosts' => $totalPosts,
                    'totalPages' => $totalPages,
                ],
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
