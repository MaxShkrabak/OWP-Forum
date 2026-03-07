<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;
use function Forum\Helpers\resolveReportsForPost;
use function Forum\Helpers\softDeleteCommentsForPost;

$app->post("/api/create-post", function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");

        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();

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
            // Columns may not exist yet (migration 008/009 not run)
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
});

$app->get('/api/posts', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id") ?? 0;
        $pdo = $makePdo();

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
});


// Helper function to fetch counts for comments
// TODO: Probably wont need anymore since I added total score to posts
// TODO: STILL NEEDS WORK
function fetchCounts($pdo, $table, $placeholders, $postIds, $countAlias)
{
    $counts = [];
    try {
        $sql = "
            SELECT PostID, COUNT(*) AS $countAlias 
            FROM $table 
            WHERE PostID IN ($placeholders) 
            GROUP BY PostID
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($postIds);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int)$row['PostID'];
            $counts[$pid] = (int)$row[$countAlias];
        }
    } catch (Throwable $e) {
        return [];
    }

    return $counts;
}

$app->get('/api/categories/{id}/posts', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $categoryId = (int)$args['id'];
        $userId = $req->getAttribute("user_id") ?? 0;
        $pdo = $makePdo();

        $catStmt = $pdo->prepare("SELECT CategoryID, Name FROM dbo.Categories WHERE CategoryID = :id");
        $catStmt->execute(['id' => $categoryId]);
        $cat = $catStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cat) {
            return json($res, ['error' => 'Category not found'], 404);
        }

        $params = $req->getQueryParams();
        $limit = min(max((int)($params['limit'] ?? 5), 1), 50);
        $page  = max((int)($params['page'] ?? 1), 1);

        $qRaw = trim((string)($params['q'] ?? ''));
        $mode = strtolower(trim((string)($params['mode'] ?? 'title')));
        if (!in_array($mode, ['title', 'tag', 'author'], true)) $mode = 'title';

        $hasSearch = $qRaw !== '';
        $qLike = '%' . $qRaw . '%';

        $sort = strtolower($params['sort'] ?? 'latest');
        $orderBy = match ($sort) {
            'oldest'   => 'p.CreatedAt ASC',
            'title'    => 'p.Title ASC',
            'upvotes'  => 'p.TotalScore DESC, p.CreatedAt DESC',
            'comments' => 'commentCount DESC, p.CreatedAt DESC',
            default    => 'p.CreatedAt DESC',
        };
$searchWhere = '';
        if ($hasSearch) {
            if ($mode === 'title') {
                $searchWhere = " AND p.Title LIKE :q ";
            } elseif ($mode === 'author') {
                $searchWhere = " AND (
                    u.FirstName LIKE :q OR u.LastName LIKE :q OR (u.FirstName + ' ' + u.LastName) LIKE :q
                ) ";
            } else { // tag
                $tagList = explode(',', $qRaw);
                $tagCount = count($tagList);
                $placeholders = [];
                foreach ($tagList as $i => $tagName) {
                    $pName = ":t$i";
                    $placeholders[] = $pName;
                    $tagBinds[$pName] = $tagName;
                }
                $placeholderStr = implode(',', $placeholders);

                $searchWhere = " AND p.PostID IN (
        SELECT pt.PostID FROM dbo.PostTags pt
        JOIN dbo.Tags t ON t.TagID = pt.TagID
        WHERE t.Name IN ($placeholderStr)
        GROUP BY pt.PostID
        HAVING COUNT(DISTINCT t.Name) = $tagCount
    ) ";
            }
        }

        // Count with search support
        $countSql = "
            SELECT COUNT(*)
            FROM dbo.Posts p
            LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
            WHERE p.CategoryID = :id AND p.IsDeleted = 0
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

        $totalPages = (int)ceil($totalPosts / $limit);
        $page = ($page > $totalPages && $totalPages > 0) ? $totalPages : $page;
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT p.PostID, p.Title, p.CreatedAt, p.TotalScore,
                   (SELECT COUNT(*) FROM dbo.Comments cm WHERE cm.PostID = p.PostID) AS commentCount,
                   u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                   ISNULL(pv.VoteValue, 0) AS myVote
            FROM dbo.Posts p
            LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
            LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
            LEFT JOIN dbo.PostVotes pv ON p.PostID = pv.PostID AND pv.User_ID = :userId
            WHERE p.CategoryID = :categoryId AND p.IsDeleted = 0
            $searchWhere
            ORDER BY $orderBy
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        ";

        $postStmt = $pdo->prepare($sql);
        $postStmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
        $postStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $postStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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
            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
$tagsByPostId = [];
            $tagSql = "SELECT pt.PostID, t.Name FROM dbo.PostTags pt
                       JOIN dbo.Tags t ON t.TagID = pt.TagID
                       WHERE pt.PostID IN ($placeholders)
                       ORDER BY CASE WHEN t.Name = 'Official' THEN 0 ELSE 1 END, t.Name ASC";
            $tagStmt = $pdo->prepare($tagSql);
            $tagStmt->execute($postIds);
            while ($t = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
                $tagsByPostId[(int)$t['PostID']][] = $t['Name'];
            }

            foreach ($rows as $row) {
                $pid = (int)$row['PostID'];
                $posts[] = [
                    'PostID'       => $pid,
                    'title'        => $row['Title'],
                    'createdAt'    => $row['CreatedAt'],
                    'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole'   => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags'         => $tagsByPostId[$pid] ?? [],
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'TotalScore'   => (int)($row['TotalScore'] ?? 0),
                    'myVote'       => (int)($row['myVote'] ?? 0),
                ];
            }
        }

        return json($res, [
            'categoryId'   => $categoryId,
            'categoryName' => $cat['Name'],
            'posts'        => $posts,
            'meta'         => [
                'limit'      => $limit,
                'sort'       => $sort,
                'page'       => $page,
                'totalPosts' => $totalPosts,
                'totalPages' => $totalPages,
                'q'          => $qRaw,
                'mode'       => $mode,
            ],
        ]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->get('/api/verify/categories', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        $pdo = $makePdo();

        $sql = "
            SELECT c.CategoryID, c.Name 
            FROM dbo.Categories c
            WHERE c.UsableByRoleID <= (
                SELECT COALESCE(MAX(RoleID), 1) 
                FROM dbo.Users 
                WHERE User_ID = :userId
            )
            ORDER BY c.Name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':userId' => $userId]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json($res, ['ok' => true, 'items' => $categories]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->get('/api/tags', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        $pdo = $makePdo();

        $getTagsSql = "
            SELECT TagID, Name
            FROM dbo.Tags
            WHERE UsableByRoleID <= ISNULL((SELECT RoleID FROM dbo.Users WHERE User_ID = :userId), 1)
            ORDER BY Name ASC
        ";

        $stmt = $pdo->prepare($getTagsSql);
        $stmt->execute([':userId' => $userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json($res, ['ok' => true, 'items' => $items]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->get('/api/tags/filter', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();

        $getTagsSql = "
            SELECT TagID, Name
            FROM dbo.Tags
            ORDER BY Name ASC
        ";

        $stmt = $pdo->query($getTagsSql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json($res, ['ok' => true, 'items' => $items]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
});

$app->post('/api/posts/{id}/vote', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $userId = (int)$req->getAttribute("user_id");
        if (!$userId) return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);

        $pdo = $makePdo();

        if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
            return $termsRes;
        }

        $postId = (int)$args['id'];

        $body = $req->getParsedBody();
        $action = $body['action'] ?? '';

        $val = ($action === 'up') ? 1 : (($action === 'down') ? -1 : 0);

        $upd = $pdo->prepare("UPDATE dbo.PostVotes SET VoteValue = ? WHERE PostID = ? AND User_ID = ?");
        $upd->execute([$val, $postId, $userId]);

        if ($val !== 0 && $upd->rowCount() === 0) {
            $ins = $pdo->prepare("INSERT INTO dbo.PostVotes (PostID, User_ID, VoteValue) VALUES (?, ?, ?)");
            $ins->execute([$postId, $userId, $val]);
        } elseif ($val === 0) {
            $pdo->prepare("DELETE FROM dbo.PostVotes WHERE PostID = ? AND User_ID = ?")->execute([$postId, $userId]);
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
});

$app->patch('/api/posts/{id}/soft-delete', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $userId = (int)$req->getAttribute("user_id");
        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();

        if ($termsRes = \Forum\Helpers\requireTermsAccepted($req, $res, $pdo)) {
            return $termsRes;
        }

        $postId = (int)$args['id'];

        // Verify ownership
        $ownerStmt = $pdo->prepare("SELECT AuthorID FROM dbo.Posts WHERE PostID = :pid AND IsDeleted = 0");
        $ownerStmt->execute([':pid' => $postId]);
        $authorId = (int)$ownerStmt->fetchColumn();

        if (!$authorId) {
            return json($res, ['ok' => false, 'error' => 'Post not found or already deleted'], 404);
        }

        if ($authorId !== $userId) {
            return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $pdo->beginTransaction();

        // Soft delete
        $stmt = $pdo->prepare("
            UPDATE dbo.Posts
            SET IsDeleted = 1, DeletedAt = SYSUTCDATETIME()
            WHERE PostID = :pid AND IsDeleted = 0
        ");
        $stmt->execute([':pid' => $postId]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => 'Post not found or already deleted'], 404);
        }

        // Soft delete comments under this post
        softDeleteCommentsForPost($pdo, $postId);

        // Resolve reports for this post + comments/replies under it
        resolveReportsForPost($pdo, $postId, (int)$userId);

        $pdo->commit();

        return json($res, ['ok' => true]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->get('/api/get-post/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $postID = (int)$args['id'];
        $userId = $req->getAttribute("user_id") ?? 0;

        $sql = "
            SELECT p.PostID, p.Title, p.Content, p.CreatedAt, p.UpdatedAt, p.CategoryID, p.AuthorID, p.TotalScore,
                   u.FirstName, u.LastName, u.Avatar,
                   r.Name AS RoleName, 
                   c.Name AS CategoryName,
                   ISNULL(pv.VoteValue, 0) AS myVote
            FROM dbo.Posts p
            LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
            LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
            LEFT JOIN dbo.Categories c ON p.CategoryID = c.CategoryID
            LEFT JOIN dbo.PostVotes pv ON p.PostID = pv.PostID AND pv.User_ID = :userId
            WHERE p.PostID = :id AND p.IsDeleted = 0
        ";
$stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $postID]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            return json($res, ['ok' => false, 'error' => "Post not found or has been deleted."], 404);
        }

        $tagStmt = $pdo->prepare("
            SELECT t.TagID, t.Name 
            FROM dbo.PostTags pt 
            JOIN dbo.Tags t ON t.TagID = pt.TagID 
            WHERE pt.PostID = :id
            ORDER BY CASE WHEN t.Name = 'Official' THEN 0 ELSE 1 END, t.Name ASC
        ");

        $tagStmt->execute(['id' => $postID]);
        $tags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

        // Map out the flat arrays for branch compatibility
        $tagNames = array_map(fn($t) => $t['Name'], $tags);
        $tagIds = array_map(fn($t) => (int)$t['TagID'], $tags);

        // TODO: This needs to be fixed this was copilots (MERGE Conflicts Resolve) too many duplicates
        $responseData = [
            'PostID'       => (int)$post['PostID'],
            'title'        => $post['Title'],
            'content'      => $post['Content'],
            'createdAt'    => $post['CreatedAt'],
            'updatedAt'    => $post['UpdatedAt'] ?? null,
            'category'     => (int)$post['CategoryID'],     // Raw ID for Edit mode (HEAD)
            'categoryId'   => (int)$post['CategoryID'],     // Branch compatibility
            'categoryName' => $post['CategoryName'],        // String for View mode
            'authorId'     => (int)$post['AuthorID'],
            'authorName'   => trim(($post['FirstName'] ?? '') . ' ' . ($post['LastName'] ?? '')),
            'authorAvatar' => $post['Avatar'],
            'authorRole'   => $post['RoleName'] ?? 'User',
            'tags'         => $tags,                        // Array of objects (TagID & Name)
            'tagNames'     => $tagNames,                    // Flat array of strings
            'tagIds'       => $tagIds,                      // Flat array of IDs
            'TotalScore'   => (int)($post['TotalScore'] ?? 0),
            'myVote'       => (int)($post['myVote'] ?? 0),
        ];

        // Ensure the response uses the wrapper standard from dev
        return json($res, ['ok' => true, 'post' => $responseData]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->put('/api/posts/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
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

        $pdo = $makePdo();

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
});

$app->delete('/api/posts/{id}', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $userId = (int)$req->getAttribute("user_id");
        if (!$userId) return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);

        $postId = (int)$args['id'];
        if ($postId <= 0) {
            return json($res, ['ok' => false, 'error' => 'Invalid post ID.'], 400);
        }

        $pdo = $makePdo();

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
});