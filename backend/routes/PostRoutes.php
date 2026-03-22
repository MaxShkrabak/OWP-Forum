<?php
use Forum\Controllers\PostController;

$postController = new PostController($makePdo);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

// Helper function to fetch counts for comments
// TODO: Probably wont need anymore since I added total score to posts
// TODO: STILL NEEDS WORK
function fetchCounts($pdo, $table, $placeholders, $postIds, $countAlias){
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

// READ ENDPOINTS

$app->get('/api/posts',                [$postController, 'getPosts']);
$app->get('/api/posts/pinned',         [$postController, 'getPinnedPosts']);

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
        $stmt->execute(['id' => $postID, 'userId' => $userId]);
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
                   u.FirstName, u.LastName, u.Avatar, u.User_ID, r.Name AS RoleName,
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
                    'postId'       => $pid,
                    'title'        => $row['Title'],
                    'createdAt'    => $row['CreatedAt'],
                    'authorId'     => (int)($row['User_ID'] ?? 0),
                    'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                    'authorRole'   => $row['RoleName'] ?? 'User',
                    'authorAvatar' => $row['Avatar'] ?? null,
                    'tags'         => $tagsByPostId[$pid] ?? [],
                    'commentCount' => (int)($row['commentCount'] ?? 0),
                    'totalScore'   => (int)($row['TotalScore'] ?? 0),
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

// WRITE/MUTATION ENDPOINTS

$app->post('/api/create-post',      [$postController, 'createPost']);
$app->post('/api/posts/{id}/vote',  [$postController, 'voteOnPost']);
$app->post('/api/posts/{id}/pin',   [$postController, 'pinPost']);

$app->delete('/api/posts/{id}',     [$postController, 'delPost']);
$app->put('/api/posts/{id}',        [$postController, 'editPost']);
