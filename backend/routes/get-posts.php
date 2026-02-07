<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/posts', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();

$q = $req->getQueryParams();

        $sort = isset($q['sort']) ? strtolower(trim($q['sort'])) : 'latest';
        $orderBy = "p.CreatedAt DESC";
        if ($sort === 'oldest') $orderBy = "p.CreatedAt ASC";

        $page  = isset($q['page']) ? max(1, (int)$q['page']) : 1;
        $limit = isset($q['limit']) ? max(1, min(200, (int)$q['limit'])) : 10;
        $offset = ($page - 1) * $limit;

        // Get all posts with user info, ordered by latest first
        $sql = "
            SELECT
                p.PostID,
                p.Title,
                p.CreatedAt,
                p.CategoryID,

                u.FirstName,
                u.LastName,
                u.Avatar,
                r.Name AS RoleName,
                c.Name AS CategoryName,

                ISNULL(cc.CommentCount, 0) AS CommentCount,
                ISNULL(vc.LikeCount, 0)    AS LikeCount

            FROM dbo.Posts p
            LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
            LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
            LEFT JOIN dbo.Categories c ON p.CategoryID = c.CategoryID

            OUTER APPLY (
                SELECT COUNT(*) AS CommentCount
                FROM dbo.Comments cm
                WHERE cm.PostID = p.PostID
            ) cc

            OUTER APPLY (
                SELECT ISNULL(SUM(v.VoteValue), 0) AS LikeCount
                FROM dbo.PostLikes v
                WHERE v.PostID = p.PostID
            ) vc

            ORDER BY p.CreatedAt DESC
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
            ";

        $postStmt = $pdo->prepare($sql);
        $postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $postStmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $postStmt->execute();
        $rows = $postStmt->fetchAll(PDO::FETCH_ASSOC);


        // Fetch tags for all posts
        $tagsByPostId = [];
        if (!empty($rows)) {
            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            $tagSql = "
                SELECT pt.PostID, t.Name
                FROM dbo.PostTags pt
                JOIN dbo.Tags t ON t.TagID = pt.TagID
                WHERE pt.PostID IN ($placeholders)
                ORDER BY t.Name ASC
            ";

            $tagStmt = $pdo->prepare($tagSql);
            $tagStmt->execute($postIds);

            while ($tagRow = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)$tagRow['PostID'];
                $tagsByPostId[$pid][] = $tagRow['Name'];
            }
        }

        // Build posts array and group by category at the same time
        $posts = [];
        $categoriesMap = [];

        foreach ($rows as $row) {
            $pid = (int)$row['PostID'];
            $categoryId = (int)$row['CategoryID'];
            $categoryName = $row['CategoryName'] ?? 'Uncategorized';

            // Initialize category in map if needed
            if (!isset($categoriesMap[$categoryId])) {
                $categoriesMap[$categoryId] = [
                    'categoryId'   => $categoryId,
                    'categoryName' => $categoryName,
                    'posts'        => [],
                ];
            }

            // Build post object
            $post = [
                'postId'       => $pid,
                'categoryId'   => $categoryId,
                'title'        => $row['Title'],
                'createdAt'    => $row['CreatedAt'],
                'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                'authorRole'   => $row['RoleName'] ?? 'User',
                'authorAvatar' => $row['Avatar'] ?? null,
                'tags'         => $tagsByPostId[$pid] ?? [],
                'commentCount' => (int)($row['CommentCount'] ?? 0),
                'likeCount'    => (int)($row['LikeCount'] ?? 0),

            ];

            $posts[] = $post;
            $categoriesMap[$categoryId]['posts'][] = $post;
        }

        // Convert map to array and sort by category name
        $postsByCategory = array_values($categoriesMap);
        usort($postsByCategory, function ($a, $b) {
            return strcmp($a['categoryName'], $b['categoryName']);
        });

        // Build response payload
        $payload = [
            'posts'          => $posts,
            'postsByCategory' => $postsByCategory,
            'totalPosts'     => count($posts),
        ];

        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json');

    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

