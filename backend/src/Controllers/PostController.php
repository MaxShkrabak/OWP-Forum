<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Throwable;
use PDO;

use function Forum\Helpers\json;
use function Forum\Helpers\resolveReportsForPost;
use function Forum\Helpers\softDeleteCommentsForPost;
use function Forum\Helpers\createNotification;

class PostController extends BaseController
{
    private function resolvePostAccess(PDO $pdo, int $postId, int $userId): array
    {
        $postStmt = $pdo->prepare("SELECT PostID, AuthorID, IsDeleted FROM dbo.Forum_Posts WHERE PostID = :id");
        $postStmt->execute(['id' => $postId]);
        $post = $postStmt->fetch(PDO::FETCH_ASSOC);

        if (!$post || (int)$post['IsDeleted'] === 1) {
            return ['error' => 'Post not found.', 'status' => 404];
        }

        $roleStmt = $pdo->prepare("SELECT ISNULL(RoleID, 1) FROM dbo.Forum_Users WHERE UserID = :uid");
        $roleStmt->execute(['uid' => $userId]);
        $userRoleId = (int)($roleStmt->fetchColumn() ?? 1);
        if ($userRoleId <= 0) $userRoleId = 1;

        if ($userId !== (int)$post['AuthorID'] && $userRoleId < 3) {
            return ['error' => 'Permission denied.', 'status' => 403];
        }

        return ['post' => $post, 'userRoleId' => $userRoleId];
    }

    private function fetchTagsByPostIds(PDO $pdo, array $postIds): array
    {
        if (empty($postIds)) return [];

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $pdo->prepare("
            SELECT pt.PostID, t.TagID, t.Name
            FROM dbo.Forum_PostTags pt
            JOIN dbo.Forum_Tags t ON t.TagID = pt.TagID
            WHERE pt.PostID IN ($placeholders)
            ORDER BY CASE WHEN t.Name = 'Official' THEN 0 ELSE 1 END, t.Name ASC
        ");
        $stmt->execute($postIds);

        $tagsByPostId = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tagsByPostId[(int)$row['PostID']][] = ['tagId' => (int)$row['TagID'], 'name' => $row['Name']];
        }
        return $tagsByPostId;
    }

    // READ ENDPOINTS

    public function getPost(Request $req, Response $res, array $args): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $postID = (int)$args['id'];
            $userId = (int)($req->getAttribute("user_id") ?? 0);

            /* View counts: only signed-in users; same user cannot bump the same post within the cooldown window. */
            $viewCooldownHours = 12;

            if ($userId > 0) {
                $dedupStmt = $pdo->prepare("
                    SELECT LastViewedAt FROM dbo.Forum_PostViewDedup
                    WHERE PostID = :pid AND UserID = :uid
                ");
                $dedupStmt->execute([':pid' => $postID, ':uid' => $userId]);
                $lastRow = $dedupStmt->fetch(PDO::FETCH_ASSOC);

                $shouldIncrement = true;
                if ($lastRow) {
                    $last = new \DateTimeImmutable($lastRow['LastViewedAt'], new \DateTimeZone('UTC'));
                    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    $hoursSince = ($now->getTimestamp() - $last->getTimestamp()) / 3600.0;
                    if ($hoursSince < $viewCooldownHours) {
                        $shouldIncrement = false;
                    }
                }

                if ($shouldIncrement) {
                    $incStmt = $pdo->prepare("
                        UPDATE dbo.Forum_Posts
                        SET ViewCount = ViewCount + 1
                        WHERE PostID = :id AND IsDeleted = 0
                    ");
                    $incStmt->execute(['id' => $postID]);
                    if ($incStmt->rowCount() === 0) {
                        return json($res, ['ok' => false, 'error' => "Post not found or has been deleted."], 404);
                    }

                    $dupExists = $pdo->prepare("SELECT 1 FROM dbo.Forum_PostViewDedup WHERE PostID = :pid AND UserID = :uid");
                    $dupExists->execute([':pid' => $postID, ':uid' => $userId]);
                    if ($dupExists->fetchColumn()) {
                        $pdo->prepare("
                            UPDATE dbo.Forum_PostViewDedup
                            SET LastViewedAt = SYSUTCDATETIME()
                            WHERE PostID = :pid AND UserID = :uid
                        ")->execute([':pid' => $postID, ':uid' => $userId]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO dbo.Forum_PostViewDedup (PostID, UserID, LastViewedAt)
                            VALUES (:pid, :uid, SYSUTCDATETIME())
                        ")->execute([':pid' => $postID, ':uid' => $userId]);
                    }
                }
            }

            $sql = "
                SELECT p.PostID, p.Title, p.Content, p.CreatedAt, p.UpdatedAt, p.CategoryID, p.AuthorID, p.TotalScore,
                        p.ViewCount, p.IsCommentsDisabled,
                        u.FirstName, u.LastName, u.Avatar,
                        r.Name AS RoleName,
                        c.Name AS CategoryName,
                        ISNULL(pv.VoteValue, 0) AS myVote
                FROM dbo.Forum_Posts p
                LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
                LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.UserID = :userId
                WHERE p.PostID = :id AND p.IsDeleted = 0
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $postID, 'userId' => $userId]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                return json($res, ['ok' => false, 'error' => "Post not found or has been deleted."], 404);
            }
            $categoryId = (int)($post['CategoryID'] ?? 0);

            if (!$this->canViewCategory($pdo, $categoryId, $userId)) {
                return json($res, ['ok' => false, 'error' => 'Post not found or has been deleted.'], 404);
            }

            $tagsByPostId = $this->fetchTagsByPostIds($pdo, [$postID]);
            $tags = $tagsByPostId[$postID] ?? [];

            return json($res, ['ok' => true, 'post' => [
                'postId'       => (int)$post['PostID'],
                'title'        => $post['Title'],
                'content'      => $post['Content'],
                'createdAt'    => $post['CreatedAt'],
                'updatedAt'    => $post['UpdatedAt'] ?? null,
                'categoryId'   => (int)$post['CategoryID'],
                'categoryName' => $post['CategoryName'],
                'visibleFromRoleId' => $this->getCategoryVisibilityRoleId($pdo, $categoryId),
                'authorId'     => (int)$post['AuthorID'],
                'authorName'   => trim(($post['FirstName'] ?? '') . ' ' . ($post['LastName'] ?? '')),
                'authorAvatar' => $post['Avatar'],
                'authorRole'   => $post['RoleName'] ?? 'User',
                'tags'         => $tags,
                'totalScore'          => (int)($post['TotalScore'] ?? 0),
                'viewCount'           => (int)($post['ViewCount'] ?? 0),
                'myVote'              => (int)($post['myVote'] ?? 0),
                'isCommentsDisabled'  => (bool)($post['IsCommentsDisabled'] ?? false),
            ]]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to load post.'], 500);
        }
    }
public function searchPosts(Request $req, Response $res): Response
{
    try {
        $userId = (int)($req->getAttribute("user_id") ?? 0);
        $pdo = ($this->makePdo)();
        $userRoleId = $this->getUserRoleId($pdo, $userId);

        $params = $req->getQueryParams();

        $q = trim((string)($params['q'] ?? ''));
        $page = max((int)($params['page'] ?? 1), 1);
        $limit = min(max((int)($params['limit'] ?? 10), 1), 50);
        $sort = strtolower((string)($params['sort'] ?? 'latest'));

        $rawCategoryIds = trim((string)($params['categoryIds'] ?? ''));
        $categoryIds = array_values(array_filter(
            array_map('intval', explode(',', $rawCategoryIds)),
            fn($id) => $id > 0
        ));

        $where = "WHERE p.IsDeleted = 0";
        $bind = [];

        if ($userRoleId < 4) {
            $where .= " AND ISNULL(c.VisibleFromRoleID, 0) <= :roleIdVisible";
            $bind[':roleIdVisible'] = $userRoleId;
        }

        if (!empty($categoryIds)) {
            $catPlaceholders = [];
            foreach ($categoryIds as $i => $categoryId) {
                $param = ":cat$i";
                $catPlaceholders[] = $param;
                $bind[$param] = $categoryId;
            }
            $where .= " AND p.CategoryID IN (" . implode(',', $catPlaceholders) . ")";
        }

        if ($q !== '') {
            $qLike = '%' . $q . '%';

            $where .= "
                AND (
                    p.Title LIKE :qTitle
                    OR c.Name LIKE :qCategory
                    OR r.Name LIKE :qRole
                    OR u.FirstName LIKE :qFirstName
                    OR u.LastName LIKE :qLastName
                    OR EXISTS (
                        SELECT 1
                        FROM dbo.Forum_PostTags pt
                        JOIN dbo.Forum_Tags t ON t.TagID = pt.TagID
                        WHERE pt.PostID = p.PostID
                        AND t.Name LIKE :qTag
                    )
                )
            ";

            $bind[':qTitle'] = $qLike;
            $bind[':qCategory'] = $qLike;
            $bind[':qRole'] = $qLike;
            $bind[':qFirstName'] = $qLike;
            $bind[':qLastName'] = $qLike;
            $bind[':qTag'] = $qLike;
        }

        $countSql = "
            SELECT COUNT(*)
            FROM dbo.Forum_Posts p
            LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
            LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
            LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
            $where
        ";

        $countStmt = $pdo->prepare($countSql);
        foreach ($bind as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalPosts = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($totalPosts / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;

        $orderBy = match ($sort) {
            'oldest'   => 'CreatedAt ASC',
            'upvotes'  => 'TotalScore DESC, CreatedAt DESC',
            'comments' => 'commentCount DESC, CreatedAt DESC',
            default    => 'CreatedAt DESC',
        };

        $sql = "
            WITH SearchResults AS (
                SELECT
                    p.PostID,
                    p.Title,
                    p.CreatedAt,
                    p.CategoryID,
                    p.TotalScore,
                    (
                        SELECT COUNT(*)
                        FROM dbo.Forum_Comments cm
                        WHERE cm.PostID = p.PostID AND cm.IsDeleted = 0
                    ) AS commentCount,
                    u.FirstName,
                    u.LastName,
                    u.Avatar,
                    u.UserID,
                    r.Name AS RoleName,
                    c.Name AS CategoryName,
                    ISNULL(c.VisibleFromRoleID, 0) AS VisibleFromRoleID,
                    ISNULL(pv.VoteValue, 0) AS myVote,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM dbo.Forum_Pinned pin
                            WHERE pin.PostID = p.PostID
                        ) THEN 1
                        ELSE 0
                    END AS IsPinned
                FROM dbo.Forum_Posts p
                LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
                LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.UserID = :userId
                $where
            )
            SELECT *
            FROM SearchResults
            ORDER BY $orderBy
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);

        foreach ($bind as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return json($res, [
                'ok' => true,
                'posts' => [],
                'meta' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalPosts' => $totalPosts,
                    'totalPages' => $totalPages,
                    'hasNextPage' => false,
                    'hasPrevPage' => $page > 1,
                ],
            ]);
        }

        $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
        $tagsByPostId = $this->fetchTagsByPostIds($pdo, $postIds);

        $posts = [];
        foreach ($rows as $row) {
            $pid = (int)$row['PostID'];

            $posts[] = [
                'postId' => $pid,
                'categoryId' => (int)($row['CategoryID'] ?? 0),
                'categoryName' => $row['CategoryName'] ?? '',
                'visibleFromRoleId' => (int)($row['VisibleFromRoleID'] ?? 0),
                'title' => $row['Title'],
                'createdAt' => $row['CreatedAt'],
                'authorId' => (int)($row['UserID'] ?? 0),
                'authorName' => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                'authorRole' => $row['RoleName'] ?? 'User',
                'authorAvatar' => $row['Avatar'] ?? null,
                'tags' => array_column($tagsByPostId[$pid] ?? [], 'name'),
                'commentCount' => (int)($row['commentCount'] ?? 0),
                'totalScore' => (int)($row['TotalScore'] ?? 0),
                'myVote' => (int)($row['myVote'] ?? 0),
                'isPinned' => (int)($row['IsPinned'] ?? 0) === 1,
            ];
        }

        return json($res, [
            'ok' => true,
            'posts' => $posts,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'totalPosts' => $totalPosts,
                'totalPages' => $totalPages,
                'hasNextPage' => $page < $totalPages,
                'hasPrevPage' => $page > 1,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('searchPosts error: ' . $e->getMessage());

        return json($res, [
            'ok' => false,
            'error' => 'Failed to search posts.',
        ], 500);
    }
}

    public function getCategoryPosts(Request $req, Response $res, array $args): Response
    {
        try {
            $categoryId = (int)$args['id'];
            $userId = $req->getAttribute("user_id") ?? 0;
            $pdo = ($this->makePdo)();

            $catStmt = $pdo->prepare("SELECT CategoryID, Name FROM dbo.Forum_Categories WHERE CategoryID = :id");
            $catStmt->execute(['id' => $categoryId]);
            $cat = $catStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cat) {
                return json($res, ['error' => 'Category not found'], 404);
            }

            $params = $req->getQueryParams();
            $limit  = min(max((int)($params['limit'] ?? 5), 1), 50);
            $page   = max((int)($params['page'] ?? 1), 1);

            $qRaw = trim((string)($params['q'] ?? ''));
            $mode = strtolower(trim((string)($params['mode'] ?? 'title')));
            if (!in_array($mode, ['title', 'tag', 'author'], true)) $mode = 'title';

            $hasSearch = $qRaw !== '';
            $qLike     = '%' . $qRaw . '%';

            $sort    = strtolower($params['sort'] ?? 'latest');
            $orderBy = match ($sort) {
                'oldest'   => 'p.CreatedAt ASC',
                'title'    => 'p.Title ASC',
                'upvotes'  => 'p.TotalScore DESC, p.CreatedAt DESC',
                'comments' => 'commentCount DESC, p.CreatedAt DESC',
                default    => 'p.CreatedAt DESC',
            };

            $tagBinds    = [];
            $searchWhere = '';
            if ($hasSearch) {
                if ($mode === 'title') {
                    $searchWhere = " AND p.Title LIKE :q ";
                } elseif ($mode === 'author') {
                    $searchWhere = " AND (
                        u.FirstName LIKE :q OR u.LastName LIKE :q OR (u.FirstName + ' ' + u.LastName) LIKE :q
                    ) ";
                } else {
                    $tagList  = explode(',', $qRaw);
                    $tagCount = count($tagList);
                    $placeholders = [];
                    foreach ($tagList as $i => $tagName) {
                        $pName = ":t$i";
                        $placeholders[]   = $pName;
                        $tagBinds[$pName] = $tagName;
                    }
                    $placeholderStr = implode(',', $placeholders);
                    $searchWhere = " AND p.PostID IN (
                        SELECT pt.PostID FROM dbo.Forum_PostTags pt
                        JOIN dbo.Forum_Tags t ON t.TagID = pt.TagID
                        WHERE t.Name IN ($placeholderStr)
                        GROUP BY pt.PostID
                        HAVING COUNT(DISTINCT t.Name) = $tagCount
                    ) ";
                }
            }

            $totalAllStmt = $pdo->prepare("SELECT COUNT(*) FROM dbo.Forum_Posts WHERE CategoryID = :id AND IsDeleted = 0");
            $totalAllStmt->execute(['id' => $categoryId]);
            $totalAll = (int)$totalAllStmt->fetchColumn();

            $pinnedCountStmt = $pdo->prepare("
                SELECT COUNT(*) FROM dbo.Forum_Pinned pin
                JOIN dbo.Forum_Posts p ON p.PostID = pin.PostID
                WHERE p.CategoryID = :id AND p.IsDeleted = 0
            ");
            $pinnedCountStmt->execute(['id' => $categoryId]);
            $pinnedCount = (int)$pinnedCountStmt->fetchColumn();

            $countSql  = "
                SELECT COUNT(*)
                FROM dbo.Forum_Posts p
                LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
                WHERE p.CategoryID = :id AND p.IsDeleted = 0
                AND p.PostID NOT IN (SELECT PostID FROM dbo.Forum_Pinned)
                $searchWhere
            ";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
            if ($hasSearch) {
                if ($mode === 'tag') {
                    foreach ($tagBinds as $param => $val) {
                        $countStmt->bindValue($param, $val, PDO::PARAM_STR);
                    }
                } else {
                    $countStmt->bindValue(':q', $qLike, PDO::PARAM_STR);
                }
            }
            $countStmt->execute();
            $totalPosts = (int)$countStmt->fetchColumn();

            $firstPageCapacity = max(0, $limit - $pinnedCount);
            if ($totalPosts <= $firstPageCapacity) {
                $totalPages = 1;
            } else {
                $totalPages = 1 + (int)ceil(($totalPosts - $firstPageCapacity) / $limit);
            }

            $page = ($page > $totalPages && $totalPages > 0) ? $totalPages : $page;

            if ($page === 1) {
                $effectiveLimit = max(1, $firstPageCapacity);
                $offset = 0;
            } else {
                $effectiveLimit = $limit;
                $offset = $firstPageCapacity + ($page - 2) * $limit;
            }

            $sql = "
                SELECT p.PostID, p.Title, p.CreatedAt, p.TotalScore,
                       (SELECT COUNT(*) FROM dbo.Forum_Comments cm WHERE cm.PostID = p.PostID AND cm.IsDeleted = 0) AS commentCount,
                       u.FirstName, u.LastName, u.Avatar, u.UserID, r.Name AS RoleName,
                       ISNULL(pv.VoteValue, 0) AS myVote
                FROM dbo.Forum_Posts p
                LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.UserID = :userId
                WHERE p.CategoryID = :categoryId AND p.IsDeleted = 0
                AND p.PostID NOT IN (SELECT PostID FROM dbo.Forum_Pinned)
                $searchWhere
                ORDER BY $orderBy
                OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
            ";

            $postStmt = $pdo->prepare($sql);
            $postStmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
            $postStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $postStmt->bindValue(':limit', $effectiveLimit, PDO::PARAM_INT);
            if ($hasSearch) {
                if ($mode === 'tag') {
                    foreach ($tagBinds as $param => $val) {
                        $postStmt->bindValue($param, $val, PDO::PARAM_STR);
                    }
                } else {
                    $postStmt->bindValue(':q', $qLike, PDO::PARAM_STR);
                }
            }
            $postStmt->execute();
            $rows = $postStmt->fetchAll(PDO::FETCH_ASSOC);

            $posts = [];
            if (!empty($rows)) {
                $postIds      = array_map(fn($r) => (int)$r['PostID'], $rows);
                $tagsByPostId = $this->fetchTagsByPostIds($pdo, $postIds);

                foreach ($rows as $row) {
                    $pid     = (int)$row['PostID'];
                    $posts[] = [
                        'postId'       => $pid,
                        'title'        => $row['Title'],
                        'createdAt'    => $row['CreatedAt'],
                        'authorId'     => (int)($row['UserID'] ?? 0),
                        'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                        'authorRole'   => $row['RoleName'] ?? 'User',
                        'authorAvatar' => $row['Avatar'] ?? null,
                        'tags'         => array_column($tagsByPostId[$pid] ?? [], 'name'),
                        'commentCount' => (int)($row['commentCount'] ?? 0),
                        'totalScore'   => (int)($row['TotalScore'] ?? 0),
                        'myVote'       => (int)($row['myVote'] ?? 0),
                    ];
                }
            }


            return json($res, [
                'categoryId'        => $categoryId,
                'categoryName'      => $cat['Name'],
                'visibleFromRoleId' => $this->getCategoryVisibilityRoleId($pdo, $categoryId),
                'posts'             => $posts,
                'meta'              => [
                    'totalPosts' => $totalAll,
                    'totalPages' => $totalPages,
                ],
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to load posts.'], 500);
        }
    }

    public function getVerifyCategories(Request $req, Response $res): Response
    {
        try {
            $userId = (int)($req->getAttribute("user_id") ?? 0);
            $pdo = ($this->makePdo)();
            $userRoleId = $this->getUserRoleId($pdo, $userId);

            if ($userRoleId >= 4) {
                $sql = "
                SELECT c.CategoryID, c.Name
                FROM dbo.Forum_Categories c
                WHERE c.UsableByRoleID <= :usableRoleId
                ORDER BY c.Name ASC
            ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':usableRoleId' => $userRoleId,
                ]);
            } else {
                $sql = "
                SELECT c.CategoryID, c.Name
                FROM dbo.Forum_Categories c
                WHERE c.UsableByRoleID <= :usableRoleId
                  AND ISNULL(c.VisibleFromRoleID, 0) <= :visibleRoleId
                ORDER BY c.Name ASC
            ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':usableRoleId' => $userRoleId,
                    ':visibleRoleId' => $userRoleId,
                ]);
            }

            $categories = array_map(fn($c) => [
                'categoryId' => (int)$c['CategoryID'],
                'name'       => $c['Name'],
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));

            return json($res, ['ok' => true, 'items' => $categories]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to load categories.'], 500);
        }
    }

    public function getTags(Request $req, Response $res): Response
    {
        try {
            $userId = $req->getAttribute("user_id");
            $pdo    = ($this->makePdo)();

            $sql = "
                SELECT TagID, Name
                FROM dbo.Forum_Tags
                WHERE UsableByRoleID <= ISNULL((SELECT RoleID FROM dbo.Forum_Users WHERE UserID = :userId), 1)
                ORDER BY Name ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':userId' => $userId]);
            $items = array_map(fn($r) => ['tagId' => (int)$r['TagID'], 'name' => $r['Name']], $stmt->fetchAll(PDO::FETCH_ASSOC));

            return json($res, ['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to load tags.'], 500);
        }
    }

    public function getTagsFilter(Request $req, Response $res): Response
    {
        try {
            $pdo  = ($this->makePdo)();
            $stmt = $pdo->query("SELECT TagID, Name FROM dbo.Forum_Tags ORDER BY Name ASC");
            $items = array_map(fn($r) => ['tagId' => (int)$r['TagID'], 'name' => $r['Name']], $stmt->fetchAll(PDO::FETCH_ASSOC));

            return json($res, ['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to load tags.'], 500);
        }
    }

    public function getPosts(Request $req, Response $res): Response
    {
        try {
            $userId = (int)($req->getAttribute("user_id") ?? 0);
            $pdo = ($this->makePdo)();
            $userRoleId = $this->getUserRoleId($pdo, $userId);

            $params = $req->getQueryParams();
            $sort = strtolower($params['sort'] ?? 'latest');
            $limit = min(max((int)($params['limit'] ?? 5), 1), 50);

            $orderBy = match ($sort) {
                'oldest'   => 'CreatedAt ASC',
                'upvotes'  => 'TotalScore DESC, CreatedAt DESC',
                'comments' => 'commentCount DESC, CreatedAt DESC',
                default    => 'CreatedAt DESC',
            };

            if ($userRoleId >= 4) {
                $countSql = "
                SELECT p.CategoryID, COUNT(*) AS postCount
                FROM dbo.Forum_Posts p
                INNER JOIN dbo.Forum_Categories c ON c.CategoryID = p.CategoryID
                WHERE p.IsDeleted = 0
                GROUP BY p.CategoryID
            ";
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute();
            } else {
                $countSql = "
                SELECT p.CategoryID, COUNT(*) AS postCount
                FROM dbo.Forum_Posts p
                INNER JOIN dbo.Forum_Categories c ON c.CategoryID = p.CategoryID
                WHERE p.IsDeleted = 0
                  AND ISNULL(c.VisibleFromRoleID, 0) <= :roleIdVisible
                GROUP BY p.CategoryID
            ";
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute([
                    ':roleIdVisible' => $userRoleId,
                ]);
            }

            $categoryCounts = [];
            while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
                $categoryCounts[(int)$row['CategoryID']] = (int)$row['postCount'];
            }

            if ($userRoleId >= 4) {
                $getPostsSql = "
                WITH PostsWithCounts AS (
                    SELECT
                        p.PostID,
                        p.Title,
                        p.CreatedAt,
                        p.CategoryID,
                        p.TotalScore,
                        (SELECT COUNT(*) FROM dbo.Forum_Comments cm WHERE cm.PostID = p.PostID AND cm.IsDeleted = 0) AS commentCount,
                        u.FirstName,
                        u.LastName,
                        u.Avatar,
                        u.UserID,
                        r.Name AS RoleName,
                        c.Name AS CategoryName,
                        ISNULL(c.VisibleFromRoleID, 0) AS VisibleFromRoleID,
                        ISNULL(pv.VoteValue, 0) AS myVote
                    FROM dbo.Forum_Posts p
                    LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
                    LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                    LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
                    LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.UserID = :userId
                    WHERE p.IsDeleted = 0
                ),
                RankedPosts AS (
                    SELECT *, ROW_NUMBER() OVER (PARTITION BY CategoryID ORDER BY $orderBy) AS rn
                    FROM PostsWithCounts
                )
                SELECT * FROM RankedPosts WHERE rn <= :limitRows
                ORDER BY CategoryID, $orderBy
            ";

                $stmt = $pdo->prepare($getPostsSql);
                $stmt->execute([
                    ':userId' => $userId,
                    ':limitRows' => $limit,
                ]);
            } else {
                $getPostsSql = "
                WITH PostsWithCounts AS (
                    SELECT
                        p.PostID,
                        p.Title,
                        p.CreatedAt,
                        p.CategoryID,
                        p.TotalScore,
                        (SELECT COUNT(*) FROM dbo.Forum_Comments cm WHERE cm.PostID = p.PostID AND cm.IsDeleted = 0) AS commentCount,
                        u.FirstName,
                        u.LastName,
                        u.Avatar,
                        u.UserID,
                        r.Name AS RoleName,
                        c.Name AS CategoryName,
                        ISNULL(c.VisibleFromRoleID, 0) AS VisibleFromRoleID,
                        ISNULL(pv.VoteValue, 0) AS myVote
                    FROM dbo.Forum_Posts p
                    LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
                    LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                    LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
                    LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.UserID = :userId
                    WHERE p.IsDeleted = 0
                      AND ISNULL(c.VisibleFromRoleID, 0) <= :roleIdVisible
                ),
                RankedPosts AS (
                    SELECT *, ROW_NUMBER() OVER (PARTITION BY CategoryID ORDER BY $orderBy) AS rn
                    FROM PostsWithCounts
                )
                SELECT * FROM RankedPosts WHERE rn <= :limitRows
                ORDER BY CategoryID, $orderBy
            ";

                $stmt = $pdo->prepare($getPostsSql);
                $stmt->execute([
                    ':userId' => $userId,
                    ':roleIdVisible' => $userRoleId,
                    ':limitRows' => $limit,
                ]);
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return json($res, ['postsByCategory' => [], 'totalPosts' => 0]);
            }

            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $tagsByPostId = $this->fetchTagsByPostIds($pdo, $postIds);

            $categoriesMap = [];

            foreach ($rows as $row) {
                $pid = (int)$row['PostID'];
                $catId = (int)$row['CategoryID'];

                $post = [
                    'postId' => $pid,
                    'categoryId' => $catId,
                    'title' => $row['Title'],
                    'createdAt' => $row['CreatedAt'],
                    'authorId' => (int)($row['UserID'] ?? 0),
                    'authorName' => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole' => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags' => array_column($tagsByPostId[$pid] ?? [], 'name'),
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'totalScore' => (int)($row['TotalScore'] ?? 0),
                    'myVote' => (int)($row['myVote'] ?? 0),
                ];

                if (!isset($categoriesMap[$catId])) {
                    $categoriesMap[$catId] = [
                        'categoryId' => $catId,
                        'categoryName' => $row['CategoryName'] ?? 'Uncategorized',
                        'visibleFromRoleId' => (int)($row['VisibleFromRoleID'] ?? 0),
                        'posts' => []
                    ];
                }

                $categoriesMap[$catId]['posts'][] = $post;
            }

            $postsByCategory = array_values($categoriesMap);

            foreach ($postsByCategory as &$cat) {
                $cat['postCount'] = $categoryCounts[$cat['categoryId']] ?? count($cat['posts']);
            }
            unset($cat);

            usort($postsByCategory, fn($a, $b) => strcmp($a['categoryName'], $b['categoryName']));

            return json($res, [
                'postsByCategory' => $postsByCategory,
                'totalPosts' => array_sum($categoryCounts),
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to load posts.'], 500);
        }
    }

    public function getPinnedPosts(Request $req, Response $res): Response
    {
        try {
            $pdo = ($this->makePdo)();
            $userId = (int)($req->getAttribute("user_id") ?? 0);
            $userRoleId = $this->getUserRoleId($pdo, $userId);

            $visibilityClause = $userRoleId >= 4
                ? ''
                : 'AND ISNULL(c.VisibleFromRoleID, 0) <= :roleIdVisible';

            $sql = "
                SELECT
                    p.PostID,
                    p.Title,
                    p.CreatedAt,
                    p.CategoryID,
                    p.TotalScore,
                    (SELECT COUNT(*) FROM dbo.Forum_Comments cm WHERE cm.PostID = p.PostID AND cm.IsDeleted = 0) AS commentCount,
                    u.FirstName,
                    u.LastName,
                    u.Avatar,
                    u.UserID,
                    r.Name AS RoleName,
                    c.Name AS CategoryName,
                    ISNULL(c.VisibleFromRoleID, 0) AS VisibleFromRoleID,
                    ISNULL(pv.VoteValue, 0) AS myVote
                FROM dbo.Forum_Pinned pin
                INNER JOIN dbo.Forum_Posts p ON pin.PostID = p.PostID
                LEFT JOIN dbo.Forum_Users u ON p.AuthorID = u.UserID
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                LEFT JOIN dbo.Forum_Categories c ON p.CategoryID = c.CategoryID
                LEFT JOIN dbo.Forum_PostVotes pv ON p.PostID = pv.PostID AND pv.UserID = :userId
                WHERE p.IsDeleted = 0
                  {$visibilityClause}
                ORDER BY pin.CreatedAt DESC, p.CreatedAt DESC
            ";

            $params = [':userId' => $userId];
            if ($userRoleId < 4) {
                $params[':roleIdVisible'] = $userRoleId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return json($res, ['ok' => true, 'posts' => []]);
            }

            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $tagsByPostId = $this->fetchTagsByPostIds($pdo, $postIds);

            $posts = [];
            foreach ($rows as $row) {
                $pid = (int)$row['PostID'];

                $posts[] = [
                    'postId' => $pid,
                    'categoryId' => (int)($row['CategoryID'] ?? 0),
                    'categoryName' => $row['CategoryName'] ?? '',
                    'visibleFromRoleId' => (int)($row['VisibleFromRoleID'] ?? 0),
                    'title' => $row['Title'],
                    'createdAt' => $row['CreatedAt'],
                    'authorId' => (int)($row['UserID'] ?? 0),
                    'authorName' => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole' => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags' => array_column($tagsByPostId[$pid] ?? [], 'name'),
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'totalScore' => (int)($row['TotalScore'] ?? 0),
                    'myVote' => (int)($row['myVote'] ?? 0),
                    'isPinned' => true,
                ];
            }

            return json($res, [
                'ok' => true,
                'posts' => $posts,
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to load pinned posts.'], 500);
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

            $banResponse = \Forum\Helpers\checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            // Tag limit: 5 tags per post
            $data = $req->getParsedBody() ?? [];
            $title = trim((string)($data['title'] ?? ''));

            $categoryIdIn = (int)($data['category'] ?? 0);
            $content = (string)($data['content'] ?? '');

            if ($title === '' || $content === '' || $categoryIdIn === 0) {
                return json($res, ['ok' => false, 'error' => 'Title, content, and category are required.'], 400);
            }

            if (mb_strlen($content) > 50000) {
                return json($res, ['ok' => false, 'error' => 'Content must be 50,000 characters or fewer.'], 400);
            }

            $content = \Forum\Helpers\sanitizeHtml($content);

            $tagsIn = (array)($data['tags'] ?? []);
            $tagsIn = array_values(array_unique(array_map('intval', $tagsIn)));
            $tagsIn = array_slice(array_filter($tagsIn, fn($v) => $v > 0), 0, 5);

            $disableCommentsIn = !empty($data['disableComments']);

            // Simple spam protection: cooldown + duplicate check + hourly rate limit
            $postCooldownSeconds = 60;
            $postPerHourLimit = 10;

            $roleStmt = $pdo->prepare("
                SELECT ISNULL(RoleID, 1)
                FROM dbo.Forum_Users
                WHERE UserID = :uid
            ");
            $roleStmt->execute([':uid' => $userId]);
            $currentRoleId = (int)($roleStmt->fetchColumn() ?? 1);
            if ($currentRoleId <= 0) {
                $currentRoleId = 1;
            }

            $isCooldownExempt = $currentRoleId >= 3;

            $pdo->beginTransaction();

            $lockStmt = $pdo->prepare("
                DECLARE @result INT;
                EXEC @result = sp_getapplock @lockOwner = 'Transaction', @Resource = :res, @LockMode = 'Exclusive', @LockTimeout = 10000;
                SELECT @result;
            ");
            $lockStmt->execute([':res' => "create_post_user_$userId"]);
            $lockResult = (int)($lockStmt->fetchColumn() ?? -999);

            if ($lockResult < 0) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Could not acquire lock, please try again.'], 503);
            }

            $recentPostsStmt = $pdo->prepare("
                SELECT COUNT(*) FROM dbo.Forum_Posts
                WHERE AuthorID = :uid AND CreatedAt >= DATEADD(HOUR, -1, SYSUTCDATETIME())
            ");
            $recentPostsStmt->execute([':uid' => $userId]);
            $recentPostCount = (int)$recentPostsStmt->fetchColumn();

            if ($recentPostCount >= $postPerHourLimit) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'You have reached the hourly post limit.'], 429);
            }

            $lastPostStmt = $pdo->prepare("
                SELECT TOP 1 Title, CreatedAt, CAST(Content AS NVARCHAR(MAX)) as Content
                FROM dbo.Forum_Posts 
                WHERE AuthorID = :uid AND IsDeleted = 0
                ORDER BY CreatedAt DESC
            ");
            $lastPostStmt->execute([':uid' => $userId]);
            $lastPost = $lastPostStmt->fetch(PDO::FETCH_ASSOC);

            if ($lastPost) {
                $lastTime = new \DateTimeImmutable($lastPost['CreatedAt'], new \DateTimeZone('UTC'));
                $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $secondsSinceLastPost = $now->getTimestamp() - $lastTime->getTimestamp();

                if (!$isCooldownExempt && $secondsSinceLastPost < $postCooldownSeconds) {
                    $secondsLeft = $postCooldownSeconds - $secondsSinceLastPost;
                    $pdo->rollBack();
                    return json($res, [
                        'ok' => false,
                        'error' => "Please wait {$secondsLeft}s before posting again.",
                        'cooldownSeconds' => $secondsLeft,
                    ], 429);
                }

                if ($lastPost['Title'] === $title && $lastPost['Content'] === $content) {
                    $pdo->rollBack();
                    return json($res, [
                        'ok' => false,
                        'error' => 'You already created an identical post!'
                    ], 409);
                }
            }

            // Category section
            $catStmt = $pdo->prepare("
                SELECT
                    c.CategoryID,
                    c.UsableByRoleID,
                    ISNULL(c.VisibleFromRoleID, 0) AS VisibleFromRoleID
                FROM dbo.Forum_Categories c
                WHERE c.CategoryID = :catId
            ");
            $catStmt->execute([':catId' => $categoryIdIn]);
            $categoryData = $catStmt->fetch(PDO::FETCH_ASSOC);

            if (!$categoryData) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Invalid category.'], 400);
            }

            // Check if user has permission to use category
            if ($currentRoleId < (int)$categoryData['UsableByRoleID']) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Permission denied for this category.'], 403);
            }
            if ($currentRoleId < 4 && $currentRoleId < (int)$categoryData['VisibleFromRoleID']) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Permission denied for this category.'], 403);
            }

            $categoryId = (int)$categoryData['CategoryID'];

            $isCommentsDisabled = ($isCooldownExempt && $disableCommentsIn) ? 1 : 0;

            // Store post information section
            $storePost = "
                INSERT INTO dbo.Forum_Posts (Title, CategoryID, AuthorID, Content, IsCommentsDisabled)
                OUTPUT INSERTED.PostID, INSERTED.CreatedAt
                VALUES (:title, :categoryId, :authorId, :content, :isCommentsDisabled)
            ";

            $storeStmt = $pdo->prepare($storePost);
            $storeStmt->execute([
                ':title'               => $title,
                ':categoryId'          => $categoryId,
                ':authorId'            => $userId,
                ':content'             => $content,
                ':isCommentsDisabled'  => $isCommentsDisabled,
            ]);

            $newPost = $storeStmt->fetch(PDO::FETCH_ASSOC);
            $postId = (int)($newPost['PostID'] ?? 0);

            if (!empty($tagsIn) && $postId > 0) {
                $placeholders = implode(',', array_fill(0, count($tagsIn), '?'));

                $checkTagsSql = "
                    SELECT TagID FROM dbo.Forum_Tags 
                    WHERE TagID IN ($placeholders)
                    AND UsableByRoleID <= ISNULL((SELECT RoleID FROM dbo.Forum_Users WHERE UserID = ?), 1)
                ";

                $checkStmt = $pdo->prepare($checkTagsSql);
                $checkStmt->execute(array_merge($tagsIn, [$userId]));
                $validTagIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN, 0);

                if (!empty($validTagIds)) {
                    $insTagSql = "INSERT INTO dbo.Forum_PostTags (PostID, TagID) VALUES (:pid, :tid)";
                    $insTagStmt = $pdo->prepare($insTagSql);
                    foreach ($validTagIds as $tid) {
                        $insTagStmt->execute([':pid' => $postId, ':tid' => (int)$tid]);
                    }
                }
            }

            $pdo->commit();

            return json($res, [
                'ok'        => true,
                'postId'    => $postId,
                'createdAt' => $newPost['CreatedAt'],
                'cooldownSeconds' => $isCooldownExempt ? 0 : $postCooldownSeconds,
            ]);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return json($res, ['ok' => false, 'error' => 'Failed to create post.'], 500);
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

            $banResponse = \Forum\Helpers\checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            $postId = (int)$args['id'];

            $body = $req->getParsedBody();
            $action = $body['action'] ?? '';

            $val = ($action === 'up') ? 1 : (($action === 'down') ? -1 : 0);

            $prevStmt = $pdo->prepare("SELECT VoteValue FROM dbo.Forum_PostVotes WHERE PostID = ? AND UserID = ?");
            $prevStmt->execute([$postId, $userId]);
            $previousVote = $prevStmt->fetchColumn();
            $previousVote = ($previousVote === false) ? 0 : (int)$previousVote;

            $pdo->prepare("
                MERGE dbo.Forum_PostVotes AS target
                USING (SELECT ? AS PostID, ? AS UserID, ? AS VoteValue) AS source
                    ON target.PostID = source.PostID AND target.UserID = source.UserID
                WHEN MATCHED AND source.VoteValue = 0 THEN DELETE
                WHEN MATCHED THEN UPDATE SET VoteValue = source.VoteValue
                WHEN NOT MATCHED AND source.VoteValue != 0 THEN
                    INSERT (PostID, UserID, VoteValue) VALUES (source.PostID, source.UserID, source.VoteValue);
            ")->execute([$postId, $userId, $val]);

            if ($val === 1 && $previousVote !== 1) {
                $ownerStmt = $pdo->prepare("
                    SELECT AuthorID FROM dbo.Forum_Posts WHERE PostID = :postId
                ");
                $ownerStmt->execute([':postId' => $postId]);
                $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

                if ($owner) {
                    $postOwnerId = (int)($owner['AuthorID'] ?? 0);
                    if ($postOwnerId > 0 && $postOwnerId !== $userId) {
                        createNotification($pdo, $postOwnerId, $postId, 'postLike');
                    }
                }
            }

            $stmt = $pdo->prepare("SELECT TotalScore FROM dbo.Forum_Posts WHERE PostID = ?");
            $stmt->execute([$postId]);
            $score = (int)$stmt->fetchColumn();

            return json($res, [
                'ok'     => true,
                'myVote' => $val,
                'score'  => $score
            ]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => 'Failed to process vote.'], 500);
        }
    }

    public function pinPost(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = (int)$req->getAttribute("user_id");
            if (!$userId) {
                return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
            }

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
                FROM dbo.Forum_Users u
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                WHERE u.UserID = :uid
            ");
            $roleStmt->execute([':uid' => $userId]);
            $roleName = trim((string)$roleStmt->fetchColumn());

            if (strtolower($roleName) !== 'admin' && strtolower($roleName) !== 'moderator') {
                return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
            }

            $postStmt = $pdo->prepare("
                SELECT p.PostID, p.CategoryID, p.IsDeleted
                FROM dbo.Forum_Posts p
                WHERE p.PostID = :pid
            ");
            $postStmt->execute([':pid' => $postId]);
            $post = $postStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || (int)$post['IsDeleted'] === 1) {
                return json($res, ['ok' => false, 'error' => 'Post not found.'], 404);
            }

            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("SELECT 1 FROM dbo.Forum_Pinned WITH (UPDLOCK, ROWLOCK) WHERE PostID = :pid");
            $checkStmt->execute([':pid' => $postId]);
            $alreadyPinned = (bool)$checkStmt->fetchColumn();

            if ($alreadyPinned) {
                $deleteStmt = $pdo->prepare("DELETE FROM dbo.Forum_Pinned WHERE PostID = :pid");
                $deleteStmt->execute([':pid' => $postId]);
                $pdo->commit();

                return json($res, ['ok' => true, 'isPinned' => false]);
            }

            $limitStmt = $pdo->prepare("
                SELECT COUNT(*) FROM dbo.Forum_Pinned pin WITH (HOLDLOCK)
                JOIN dbo.Forum_Posts p ON p.PostID = pin.PostID
                WHERE p.CategoryID = :categoryId
            ");
            $limitStmt->execute([':categoryId' => $post['CategoryID']]);
            if ((int)$limitStmt->fetchColumn() >= 2) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Maximum of 2 pinned posts per category reached.'], 409);
            }

            $insertStmt = $pdo->prepare("INSERT INTO dbo.Forum_Pinned (PostID) VALUES (:pid)");
            $insertStmt->execute([':pid' => $postId]);
            $pdo->commit();

            return json($res, [
                'ok' => true,
                'isPinned' => true
            ]);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return json($res, ['ok' => false, 'error' => 'Failed to update pin.'], 500);
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

            $access = $this->resolvePostAccess($pdo, $postId, $userId);
            if (isset($access['error'])) {
                return json($res, ['ok' => false, 'error' => $access['error']], $access['status']);
            }

            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("UPDATE dbo.Forum_Posts SET IsDeleted = 1, UpdatedAt = SYSUTCDATETIME(), DeletedAt = SYSUTCDATETIME() WHERE PostID = :id AND IsDeleted = 0");
            $delStmt->execute(['id' => $postId]);

            if ($delStmt->rowCount() === 0) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Failed to delete post.'], 500);
            }

            softDeleteCommentsForPost($pdo, $postId);
            resolveReportsForPost($pdo, $postId, (int)$userId);

            $pdo->commit();

            $outStmt = $pdo->prepare("SELECT IsDeleted, DeletedAt, UpdatedAt FROM dbo.Forum_Posts WHERE PostID = :id");
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
            return json($res, ['ok' => false, 'error' => 'Failed to delete post.'], 500);
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

            if (mb_strlen($content) > 50000) {
                return json($res, ['ok' => false, 'error' => 'Content must be 50,000 characters or fewer.'], 400);
            }

            $content = \Forum\Helpers\sanitizeHtml($content);

            $tagsIn = (array)($data['tags'] ?? []);
            $tagsIn = array_values(array_unique(array_map('intval', $tagsIn)));
            $tagsIn = array_slice(array_filter($tagsIn, fn($v) => $v > 0), 0, 5);

            $disableCommentsIn = !empty($data['disableComments']);

            $pdo = ($this->makePdo)();

            if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
                return $termsRes;
            }

            $access = $this->resolvePostAccess($pdo, $postId, $userId);
            if (isset($access['error'])) {
                return json($res, ['ok' => false, 'error' => $access['error']], $access['status']);
            }
            $userRoleId = $access['userRoleId'];

            $catStmt = $pdo->prepare("
                SELECT
                    c.CategoryID,
                    c.UsableByRoleID,
                    ISNULL(c.VisibleFromRoleID, 0) AS VisibleFromRoleID
                FROM dbo.Forum_Categories c
                WHERE c.CategoryID = :catId
            ");
            $catStmt->execute(['catId' => $categoryIdIn]);
            $categoryData = $catStmt->fetch(PDO::FETCH_ASSOC);

            if (!$categoryData) {
                return json($res, ['ok' => false, 'error' => 'Invalid category.'], 400);
            }
            if ($userRoleId < (int)$categoryData['UsableByRoleID']) {
                return json($res, ['ok' => false, 'error' => 'Permission denied for this category.'], 403);
            }
            if ($userRoleId < 4 && $userRoleId < (int)$categoryData['VisibleFromRoleID']) {
                return json($res, ['ok' => false, 'error' => 'Permission denied for this category.'], 403);
            }
            // Only mods/admins (role >= 3) may toggle comment disabling
            $isCommentsDisabled = ($userRoleId >= 3 && $disableCommentsIn) ? 1 : 0;

            $pdo->beginTransaction();

            $updatePostSql = $pdo->prepare("
                UPDATE dbo.Forum_Posts
                SET Title = :title, Content = :content, CategoryID = :categoryId,
                    IsCommentsDisabled = :isCommentsDisabled, UpdatedAt = SYSUTCDATETIME()
                WHERE PostID = :postId AND IsDeleted = 0
            ");

            $updatePostSql->execute([
                ':title'               => $title,
                ':content'             => $content,
                ':categoryId'          => (int)$categoryData['CategoryID'],
                ':isCommentsDisabled'  => $isCommentsDisabled,
                ':postId'              => $postId
            ]);

            if ($updatePostSql->rowCount() === 0) {
                $pdo->rollBack();
                return json($res, ['ok' => false, 'error' => 'Failed to update post.'], 500);
            }

            $pdo->prepare("DELETE FROM dbo.Forum_PostTags WHERE PostID = :postId")->execute(['postId' => $postId]);

            if (!empty($tagsIn)) {
                $placeholders = implode(',', array_fill(0, count($tagsIn), '?'));

                $checkTagsSql = "
                    SELECT TagID FROM dbo.Forum_Tags 
                    WHERE TagID IN ($placeholders)
                    AND UsableByRoleID <= ?
                ";

                $checkStmt = $pdo->prepare($checkTagsSql);
                $checkStmt->execute(array_merge($tagsIn, [$userRoleId]));
                $validTagIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN, 0);

                if (!empty($validTagIds)) {
                    $insTagSql = "INSERT INTO dbo.Forum_PostTags (PostID, TagID) VALUES (:pid, :tid)";
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
                FROM dbo.Forum_Posts p
                LEFT JOIN dbo.Forum_Categories c ON c.CategoryID = p.CategoryID
                WHERE p.PostID = :id
            ");

            $outStmt->execute(['id' => $postId]);
            $updatedPost = $outStmt->fetch(PDO::FETCH_ASSOC);

            if (!$updatedPost) {
                return json($res, ['ok' => false, 'error' => 'Post not found after update.'], 404);
            }

            $tagOutStmt = $pdo->prepare("
                SELECT t.Name, t.TagID 
                FROM dbo.Forum_PostTags pt 
                JOIN dbo.Forum_Tags t ON t.TagID = pt.TagID 
                WHERE pt.PostID = :id
                ORDER BY t.Name ASC
            ");

            $tagOutStmt->execute(['id' => $postId]);
            $updatedTags = $tagOutStmt->fetchAll(PDO::FETCH_ASSOC);

            return json($res, [
                'ok' => true,
                'post' => [
                    'postId'       => (int)$updatedPost['PostID'],
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
            return json($res, ['ok' => false, 'error' => 'Failed to update post.'], 500);
        }
    }
}
