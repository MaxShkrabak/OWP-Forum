<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Throwable;
use PDO;
use Closure;

use function Forum\Helpers\json;
use function Forum\Helpers\resolveReportsForPost;
use function Forum\Helpers\softDeleteCommentsForPost;
use function Forum\Helpers\createNotification;

class PostController {
    private Closure $makePdo;

    public function __construct(Closure $makePdo)
    {
        $this->makePdo = $makePdo;
    }

    // READ ENDPOINTS

    public function getPosts(Request $req, Response $res): Response
    {
        try {
            $userId = $req->getAttribute("user_id") ?? 0;
            $pdo = ($this->makePdo)();

            $params = $req->getQueryParams();
            $sort = strtolower($params['sort'] ?? 'latest');
            $orderBy = match ($sort) {
                'oldest'   => 'p.CreatedAt ASC',
                'upvotes'  => 'p.TotalScore DESC, p.CreatedAt DESC',
                'comments' => 'commentCount DESC, p.CreatedAt DESC',
                default    => 'p.CreatedAt DESC',
            };

            $getPostsSql = "
                SELECT p.PostID, p.Title, p.CreatedAt, p.CategoryID, p.TotalScore,
                    (SELECT COUNT(*) FROM dbo.Comments cm WHERE cm.PostID = p.PostID) AS commentCount,
                    u.FirstName, u.LastName, u.Avatar, u.User_ID,
                    r.Name AS RoleName, c.Name AS CategoryName,
                    pv.VoteValue AS myVote
                FROM dbo.Posts p
                LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
                LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Categories c ON p.CategoryID = c.CategoryID
                LEFT JOIN dbo.PostVotes pv ON p.PostID = pv.PostID AND pv.User_ID = :userId
                WHERE p.IsDeleted = 0
                ORDER BY $orderBy
            ";

            $stmt = $pdo->prepare($getPostsSql);
            $stmt->execute([':userId' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return json($res, ['posts' => [], 'postsByCategory' => [], 'totalPosts' => 0]);
            }

            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            $tagsByPostId = [];
            $getTagsSql = "
                SELECT pt.PostID, t.Name
                FROM dbo.PostTags pt
                JOIN dbo.Tags t ON t.TagID = pt.TagID
                WHERE pt.PostID IN ($placeholders)
                ORDER BY CASE WHEN t.Name = 'Official' THEN 0 ELSE 1 END, t.Name ASC
            ";

            $tagStmt = $pdo->prepare($getTagsSql);
            $tagStmt->execute($postIds);
            while ($tag = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
                $tagsByPostId[(int)$tag['PostID']][] = $tag['Name'];
            }

            $posts = [];
            $categoriesMap = [];

            foreach ($rows as $row) {
                $pid = (int)$row['PostID'];
                $catId = (int)$row['CategoryID'];

                $post = [
                    'PostID'       => $pid,
                    'categoryId'   => $catId,
                    'title'        => $row['Title'],
                    'createdAt'    => $row['CreatedAt'],
                    'authorId'     => (int)($row['User_ID'] ?? 0),
                    'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole'   => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags'         => $tagsByPostId[$pid] ?? [],
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'TotalScore'   => (int)($row['TotalScore'] ?? 0),
                    'myVote'       => (int)($row['myVote'] ?? 0),
                ];

                $posts[] = $post;

                if (!isset($categoriesMap[$catId])) {
                    $categoriesMap[$catId] = [
                        'categoryId'   => $catId,
                        'categoryName' => $row['CategoryName'] ?? 'Uncategorized',
                        'posts'        => []
                    ];
                }
                $categoriesMap[$catId]['posts'][] = $post;
            }

            $postsByCategory = array_values($categoriesMap);
            foreach ($postsByCategory as &$cat) {
                $cat['postCount'] = count($cat['posts']);
            }
            unset($cat);
            usort($postsByCategory, fn($a, $b) => strcmp($a['categoryName'], $b['categoryName']));

            return json($res, [
                'posts'           => $posts,
                'postsByCategory' => $postsByCategory,
                'totalPosts'      => count($posts),
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getPinnedPosts(Request $req, Response $res): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $userId = (int)($req->getAttribute("user_id") ?? 0);

            $sql = "
                SELECT
                    p.PostID,
                    p.Title,
                    p.CreatedAt,
                    p.CategoryID,
                    p.TotalScore,
                    (SELECT COUNT(*) FROM dbo.Comments cm WHERE cm.PostID = p.PostID) AS commentCount,
                    u.FirstName,
                    u.LastName,
                    u.Avatar,
                    u.User_ID,
                    r.Name AS RoleName,
                    c.Name AS CategoryName,
                    ISNULL(pv.VoteValue, 0) AS myVote
                FROM dbo.Pinned pin
                INNER JOIN dbo.Posts p ON pin.PostID = p.PostID
                LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
                LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Categories c ON p.CategoryID = c.CategoryID
                LEFT JOIN dbo.PostVotes pv ON p.PostID = pv.PostID AND pv.User_ID = :userId
                WHERE p.IsDeleted = 0
                ORDER BY pin.CreatedAt DESC, p.CreatedAt DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return json($res, ['ok' => true, 'posts' => []]);
            }

            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            $tagsByPostId = [];
            $tagSql = "
                SELECT pt.PostID, t.Name
                FROM dbo.PostTags pt
                JOIN dbo.Tags t ON t.TagID = pt.TagID
                WHERE pt.PostID IN ($placeholders)
                ORDER BY CASE WHEN t.Name = 'Official' THEN 0 ELSE 1 END, t.Name ASC
            ";

            $tagStmt = $pdo->prepare($tagSql);
            $tagStmt->execute($postIds);

            while ($tag = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
                $tagsByPostId[(int)$tag['PostID']][] = $tag['Name'];
            }

            $posts = [];
            foreach ($rows as $row) {
                $pid = (int)$row['PostID'];

                $posts[] = [
                    'PostID'       => $pid,
                    'postId'       => $pid,
                    'categoryId'   => (int)($row['CategoryID'] ?? 0),
                    'categoryName' => $row['CategoryName'] ?? '',
                    'title'        => $row['Title'],
                    'createdAt'    => $row['CreatedAt'],
                    'authorId'     => (int)($row['User_ID'] ?? 0),
                    'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole'   => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags'         => $tagsByPostId[$pid] ?? [],
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'TotalScore'   => (int)($row['TotalScore'] ?? 0),
                    'myVote'       => (int)($row['myVote'] ?? 0),
                    'isPinned'     => true,
                ];
            }

            return json($res, [
                'ok' => true,
                'posts' => $posts,
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // WRITE/MUTATION ENDPOINTS

    public function createPost(Request $req, Response $res): Response
    {
        try {
            $userId = (int)$req->getAttribute("user_id");
            if (!$userId) return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);

            $pdo = ($this->makePdo)();

            if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
                return $termsRes;
            }

            try {
                $banStmt = $pdo->prepare("
                    SELECT ISNULL(IsBanned, 0), BanType, BannedUntil
                    FROM dbo.Users WHERE User_ID = :uid
                ");
                $banStmt->execute([':uid' => $userId]);
                $row = $banStmt->fetch(PDO::FETCH_NUM);
                if ($row && (int)$row[0] === 1) {
                    $banType = $row[1] ? trim((string)$row[1]) : null;
                    $bannedUntil = $row[2] ?? null;
                    $effective = ($banType !== 'temporary' || !$bannedUntil)
                        || (new \DateTimeImmutable($bannedUntil, new \DateTimeZone('UTC')) > new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                    if ($effective) {
                        return json($res, ['ok' => false, 'error' => 'You are banned and cannot create posts.'], 403);
                    }
                }
            } catch (Throwable $e) {
                // Columns may not exist yet
            }

            // Tag limit: 5 tags per post
            $data = $req->getParsedBody() ?? [];
            $title = trim((string)($data['title'] ?? ''));

            $categoryIdIn = (int)($data['category'] ?? 0);
            $content = (string)($data['content'] ?? '');

            if ($title === '' || $content === '' || $categoryIdIn === 0) {
                return json($res, ['ok' => false, 'error' => 'Title, content, and category are required.'], 400);
            }

            $tagsIn = (array)($data['tags'] ?? []);
            $tagsIn = array_values(array_unique(array_map('intval', $tagsIn)));
            $tagsIn = array_slice(array_filter($tagsIn, fn($v) => $v > 0), 0, 5);

            // Simple spam protection: cooldown + duplicate check
            $postCooldownSeconds = 60;

            $lastPostStmt = $pdo->prepare("
                SELECT TOP 1 Title, CreatedAt, CAST(Content AS NVARCHAR(MAX)) as Content
                FROM dbo.Posts 
                WHERE AuthorID = :uid AND IsDeleted = 0
                ORDER BY CreatedAt DESC
            ");
            $lastPostStmt->execute([':uid' => $userId]);
            $lastPost = $lastPostStmt->fetch(PDO::FETCH_ASSOC);

            if ($lastPost) {
                $lastTime = new \DateTimeImmutable($lastPost['CreatedAt'], new \DateTimeZone('UTC'));
                $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $secondsSinceLastPost = $now->getTimestamp() - $lastTime->getTimestamp();

                if ($secondsSinceLastPost < $postCooldownSeconds) {
                    $secondsLeft = $postCooldownSeconds - $secondsSinceLastPost;
                    return json($res, [
                        'ok' => false,
                        'error' => "Please wait {$secondsLeft}s before posting again."
                    ], 429);
                }

                if ($lastPost['Title'] === $title && $lastPost['Content'] === $content) {
                    return json($res, [
                        'ok' => false,
                        'error' => 'You already created an identical post!'
                    ], 409);
                }
            }

            $pdo->beginTransaction();

            // Category section
            $getCategorySql = "
                SELECT CategoryID, UsableByRoleID, 
                    (SELECT RoleID FROM dbo.Users WHERE User_ID = :userId) as UserRole
                FROM dbo.Categories 
                WHERE CategoryID = :catId
            ";

            $catStmt = $pdo->prepare($getCategorySql);
            $catStmt->execute([
                ':catId'  => $categoryIdIn,
                ':userId' => $userId
            ]);
            $categoryData = $catStmt->fetch(PDO::FETCH_ASSOC);

            if (!$categoryData) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Invalid category.'], 400);
            }

            // Check if user has permission to use category
            $userRole = (int)($categoryData['UserRole'] ?? 1);
            if ($userRole < (int)$categoryData['UsableByRoleID']) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Permission denied for this category.'], 403);
            }

            $categoryId = (int)$categoryData['CategoryID'];

            // Store post information section
            $storePost = "
                INSERT INTO dbo.Posts (Title, CategoryID, AuthorID, Content)
                OUTPUT INSERTED.PostID, INSERTED.CreatedAt
                VALUES (:title, :categoryId, :authorId, :content)
            ";

            $storeStmt = $pdo->prepare($storePost);
            $storeStmt->execute([
                ':title'      => $title,
                ':categoryId' => $categoryId,
                ':authorId'   => $userId,
                ':content'    => $content,
            ]);

            $newPost = $storeStmt->fetch(PDO::FETCH_ASSOC);
            $postId = (int)($newPost['PostID'] ?? 0);

            if (!empty($tagsIn) && $postId > 0) {
                $placeholders = implode(',', array_fill(0, count($tagsIn), '?'));

                $checkTagsSql = "
                    SELECT TagID FROM dbo.Tags 
                    WHERE TagID IN ($placeholders)
                    AND UsableByRoleID <= ISNULL((SELECT RoleID FROM dbo.Users WHERE User_ID = ?), 1)
                ";

                $checkStmt = $pdo->prepare($checkTagsSql);
                $checkStmt->execute(array_merge($tagsIn, [$userId]));
                $validTagIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN, 0);

                if (!empty($validTagIds)) {
                    $insTagSql = "INSERT INTO dbo.PostTags (PostID, TagID) VALUES (:pid, :tid)";
                    $insTagStmt = $pdo->prepare($insTagSql);
                    foreach ($validTagIds as $tid) {
                        $insTagStmt->execute([':pid' => $postId, ':tid' => (int)$tid]);
                    }
                }
            }

            $pdo->commit();

            // Format date
            $createdAtIso = (new \DateTimeImmutable($newPost['CreatedAt'], new \DateTimeZone('UTC')))
                ->format(\DateTime::ATOM);

            return json($res, [
                'ok'        => true,
                'postId'    => $postId,
                'createdAt' => $createdAtIso,
            ]);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function voteOnPost(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = (int)$req->getAttribute("user_id");
            if (!$userId) return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);

            $pdo = ($this->makePdo)();

            if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
                return $termsRes;
            }

            $postId = (int)$args['id'];

            $body = $req->getParsedBody();
            $action = $body['action'] ?? '';

            $val = ($action === 'up') ? 1 : (($action === 'down') ? -1 : 0);

            $prevStmt = $pdo->prepare("SELECT VoteValue FROM dbo.PostVotes WHERE PostID = ? AND User_ID = ?");
            $prevStmt->execute([$postId, $userId]);
            $previousVote = $prevStmt->fetchColumn();
            $previousVote = ($previousVote === false) ? 0 : (int)$previousVote;

            $upd = $pdo->prepare("UPDATE dbo.PostVotes SET VoteValue = ? WHERE PostID = ? AND User_ID = ?");
            $upd->execute([$val, $postId, $userId]);

            if ($val !== 0 && $upd->rowCount() === 0) {
                $ins = $pdo->prepare("INSERT INTO dbo.PostVotes (PostID, User_ID, VoteValue) VALUES (?, ?, ?)");
                $ins->execute([$postId, $userId, $val]);
            } elseif ($val === 0) {
                $pdo->prepare("DELETE FROM dbo.PostVotes WHERE PostID = ? AND User_ID = ?")->execute([$postId, $userId]);
            }

            if ($val === 1 && $previousVote !== 1) {
                $ownerStmt = $pdo->prepare("
                    SELECT p.AuthorID,
                        ISNULL(u.PushNotificationsEnabled, 1) AS PushNotificationsEnabled,
                        ISNULL(u.PostLikeNotificationsEnabled, 1) AS PostLikeNotificationsEnabled
                    FROM dbo.Posts p
                    JOIN dbo.Users u ON u.User_ID = p.AuthorID
                    WHERE p.PostID = :postId
                ");
                $ownerStmt->execute([':postId' => $postId]);
                $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

                if ($owner) {
                    $postOwnerId = (int)($owner['AuthorID'] ?? 0);
                    $pushEnabled = (int)($owner['PushNotificationsEnabled'] ?? 1) === 1;
                    $likesEnabled = (int)($owner['PostLikeNotificationsEnabled'] ?? 1) === 1;

                    if ($postOwnerId > 0 && $postOwnerId !== $userId && $pushEnabled && $likesEnabled) {
                        createNotification($pdo, $postOwnerId, $postId, 'postLike');
                    }
                }
            }

            $stmt = $pdo->prepare("SELECT TotalScore FROM dbo.Posts WHERE PostID = ?");
            $stmt->execute([$postId]);
            $score = (int)$stmt->fetchColumn();

            return json($res, [
                'ok'     => true,
                'myVote' => $val,
                'score'  => $score
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function pinPost(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = (int)$req->getAttribute("user_id");
            if (!$userId) return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);

            $pdo = ($this->makePdo)();

            if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
                return $termsRes;
            }

            $postId = (int)$args['id'];
            if ($postId <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid post ID.'], 400);
            }

            $roleStmt = $pdo->prepare("
                SELECT ISNULL(r.Name, '') AS RoleName
                FROM dbo.Users u
                LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                WHERE u.User_ID = :uid
            ");
            $roleStmt->execute([':uid' => $userId]);
            $roleName = trim((string)$roleStmt->fetchColumn());

            if (strtolower($roleName) !== 'admin') {
                return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
            }

            $postStmt = $pdo->prepare("
                SELECT p.PostID, p.IsDeleted
                FROM dbo.Posts p
                WHERE p.PostID = :pid
            ");
            $postStmt->execute([':pid' => $postId]);
            $post = $postStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || (int)$post['IsDeleted'] === 1) {
                return json($res, ['ok' => false, 'error' => 'Post not found.'], 404);
            }

            $checkStmt = $pdo->prepare("SELECT 1 FROM dbo.Pinned WHERE PostID = :pid");
            $checkStmt->execute([':pid' => $postId]);
            $alreadyPinned = (bool)$checkStmt->fetchColumn();

            if ($alreadyPinned) {
                $deleteStmt = $pdo->prepare("DELETE FROM dbo.Pinned WHERE PostID = :pid");
                $deleteStmt->execute([':pid' => $postId]);

                return json($res, [
                    'ok' => true,
                    'isPinned' => false
                ]);
            }

            $insertStmt = $pdo->prepare("INSERT INTO dbo.Pinned (PostID) VALUES (:pid)");
            $insertStmt->execute([':pid' => $postId]);

            return json($res, [
                'ok' => true,
                'isPinned' => true
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function delPost(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = (int)$req->getAttribute("user_id");
            if (!$userId) return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);

            $postId = (int)$args['id'];
            if ($postId <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid post ID.'], 400);
            }

            $pdo = ($this->makePdo)();

            if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
                return $termsRes;
            }

            $postStmt = $pdo->prepare("SELECT PostID, AuthorID, IsDeleted FROM dbo.Posts WHERE PostID = :id");
            $postStmt->execute(['id' => $postId]);
            $post = $postStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                return json($res, ['ok' => false, 'error' => 'Post not found.'], 404);
            }

            if ((int)$post['IsDeleted'] === 1) {
                return json($res, ['ok' => false, 'error' => 'Post already deleted.'], 404);
            }

            $authorId = (int)$post['AuthorID'];
            $roleStmt = $pdo->prepare("SELECT ISNULL(RoleID, 1) FROM dbo.Users WHERE User_ID = :uid");
            $roleStmt->execute(['uid' => $userId]);
            $userRoleId = (int)($roleStmt->fetchColumn() ?? 1);
            if ($userRoleId <= 0) $userRoleId = 1;

            if ($userId !== $authorId && $userRoleId < 3) {
                return json($res, ['ok' => false, 'error' => 'Permission denied.'], 403);
            }

            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("UPDATE dbo.Posts SET IsDeleted = 1, UpdatedAt = SYSUTCDATETIME(), DeletedAt = SYSUTCDATETIME() WHERE PostID = :id AND IsDeleted = 0");
            $delStmt->execute(['id' => $postId]);

            if ($delStmt->rowCount() === 0) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Failed to delete post.'], 500);
            }
            
            // Soft delete comments under this post
            softDeleteCommentsForPost($pdo, $postId);

            // Resolve reports for this post + comments/replies under it
            resolveReportsForPost($pdo, $postId, (int)$userId);

            $pdo->commit();

            $outStmt = $pdo->prepare("SELECT IsDeleted, DeletedAt, UpdatedAt FROM dbo.Posts WHERE PostID = :id");
            $outStmt->execute(['id' => $postId]);
            $result = $outStmt->fetch(PDO::FETCH_ASSOC);

            return json($res, [
                'ok' => true,
                'postId' => $postId,
                'isDeleted' => (bool)($result['IsDeleted'] ?? 1),
                'deletedAt' => $result['DeletedAt'] ?? null,
                'updatedAt' => $result['UpdatedAt'] ?? null,
            ]);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function editPost(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = (int)$req->getAttribute("user_id");
            if (!$userId) return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);

            $postId = (int)$args['id'];

            if ($postId <= 0) {
                return json($res, ['ok' => false, 'error' => 'Invalid post ID.'], 400);
            }

            $data = $req->getParsedBody() ?? [];
            $title = trim((string)($data['title'] ?? ''));
            $content = (string)($data['content'] ?? '');
            $categoryIdIn = (int)($data['category'] ?? 0);

            if ($title === '' || $content === '' || $categoryIdIn === 0) {
                return json($res, ['ok' => false, 'error' => 'Title, content, and category are required.'], 400);
            }

            $tagsIn = (array)($data['tags'] ?? []);
            $tagsIn = array_values(array_unique(array_map('intval', $tagsIn)));
            $tagsIn = array_slice(array_filter($tagsIn, fn($v) => $v > 0), 0, 5);

            $pdo = ($this->makePdo)();

            if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
                return $termsRes;
            }

            $postStmt = $pdo->prepare("SELECT PostID, AuthorID, IsDeleted FROM dbo.Posts WHERE PostID = :id");
            $postStmt->execute(['id' => $postId]);
            $post = $postStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || (int)$post['IsDeleted'] === 1) {
                return json($res, ['ok' => false, 'error' => 'Post not found.'], 404);
            }

            $authorId = (int)$post['AuthorID'];
            $roleStmt = $pdo->prepare("SELECT ISNULL(RoleID, 1) FROM dbo.Users WHERE User_ID = :uid");
            $roleStmt->execute(['uid' => $userId]);
            $userRoleId = (int)($roleStmt->fetchColumn() ?? 1);
            if ($userRoleId <= 0) $userRoleId = 1;

            if ($userId !== $authorId && $userRoleId < 3) {
                return json($res, ['ok' => false, 'error' => 'Permission denied.'], 403);
            }

            $catStmt = $pdo->prepare("SELECT CategoryID, UsableByRoleID FROM dbo.Categories WHERE CategoryID = :catId");
            $catStmt->execute(['catId' => $categoryIdIn]);
            $categoryData = $catStmt->fetch(PDO::FETCH_ASSOC);

            if (!$categoryData) {
                return json($res, ['ok' => false, 'error' => 'Invalid category.'], 400);
            }
            if ($userRoleId < (int)$categoryData['UsableByRoleID']) {
                return json($res, ['ok' => false, 'error' => 'Permission denied for this category.'], 403);
            }

            $pdo->beginTransaction();

            $updatePostSql = $pdo->prepare("
                UPDATE dbo.Posts 
                SET Title = :title, Content = :content, CategoryID = :categoryId, UpdatedAt = SYSUTCDATETIME()
                WHERE PostID = :postId AND IsDeleted = 0
            ");

            $updatePostSql->execute([
                ':title'      => $title,
                ':content'    => $content,
                ':categoryId' => (int)$categoryData['CategoryID'],
                ':postId'     => $postId
            ]);

            if ($updatePostSql->rowCount() === 0) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Failed to update post.'], 500);
            }

            $pdo->prepare("DELETE FROM dbo.PostTags WHERE PostID = :postId")->execute(['postId' => $postId]);

            if (!empty($tagsIn)) {
                $placeholders = implode(',', array_fill(0, count($tagsIn), '?'));

                $checkTagsSql = "
                    SELECT TagID FROM dbo.Tags 
                    WHERE TagID IN ($placeholders)
                    AND UsableByRoleID <= ?
                ";

                $checkStmt = $pdo->prepare($checkTagsSql);
                $checkStmt->execute(array_merge($tagsIn, [$userRoleId]));
                $validTagIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN, 0);

                if (!empty($validTagIds)) {
                    $insTagSql = "INSERT INTO dbo.PostTags (PostID, TagID) VALUES (:pid, :tid)";
                    $insTagStmt = $pdo->prepare($insTagSql);
                    foreach ($validTagIds as $tid) {
                        $insTagStmt->execute([':pid' => $postId, ':tid' => (int)$tid]);
                    }
                }
            }

            $pdo->commit();

            $outStmt = $pdo->prepare("
                SELECT p.PostID, p.Title, p.Content, p.CreatedAt, p.CategoryID, p.UpdatedAt,
                    c.Name AS CategoryName
                FROM dbo.Posts p
                LEFT JOIN dbo.Categories c ON c.CategoryID = p.CategoryID
                WHERE p.PostID = :id
            ");

            $outStmt->execute(['id' => $postId]);
            $updatedPost = $outStmt->fetch(PDO::FETCH_ASSOC);

            if (!$updatedPost) {
                return json($res, ['ok' => false, 'error' => 'Post not found after update.'], 404);
            }

            $tagOutStmt = $pdo->prepare("
                SELECT t.Name, t.TagID 
                FROM dbo.PostTags pt 
                JOIN dbo.Tags t ON t.TagID = pt.TagID 
                WHERE pt.PostID = :id
                ORDER BY t.Name ASC
            ");

            $tagOutStmt->execute(['id' => $postId]);
            $updatedTags = $tagOutStmt->fetchAll(PDO::FETCH_ASSOC);

            return json($res, [
                'ok' => true,
                'post' => [
                    'PostID'       => (int)$updatedPost['PostID'],
                    'title'        => $updatedPost['Title'],
                    'content'      => $updatedPost['Content'],
                    'createdAt'    => $updatedPost['CreatedAt'],
                    'categoryId'   => (int)$updatedPost['CategoryID'],
                    'categoryName' => $updatedPost['CategoryName'] ?? null,
                    'updatedAt'    => $updatedPost['UpdatedAt'],
                    'tags'         => array_map(fn($t) => $t['Name'], $updatedTags),
                    'tagIds'       => array_map(fn($t) => (int)$t['TagID'], $updatedTags),
                ]
            ]);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

}