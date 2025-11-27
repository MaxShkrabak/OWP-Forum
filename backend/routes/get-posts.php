<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/posts', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();

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
                c.Name AS CategoryName
            FROM dbo.Posts p
            LEFT JOIN dbo.Users u
                ON p.AuthorID = u.User_ID
            LEFT JOIN dbo.Roles r
                ON u.RoleID = r.RoleID
            LEFT JOIN dbo.Categories c
                ON p.CategoryID = c.CategoryID
            ORDER BY p.CreatedAt DESC
        ";

        $postStmt = $pdo->prepare($sql);
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

        // Fetch comment counts for all posts
        $commentCountsByPostId = [];
        if (!empty($rows)) {
            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            // Check if Comments table exists by trying to query it
            try {
                $commentSql = "
                    SELECT PostID, COUNT(*) AS CommentCount
                    FROM dbo.Comments
                    WHERE PostID IN ($placeholders)
                    GROUP BY PostID
                ";
                $commentStmt = $pdo->prepare($commentSql);
                $commentStmt->execute($postIds);

                while ($commentRow = $commentStmt->fetch(PDO::FETCH_ASSOC)) {
                    $pid = (int)$commentRow['PostID'];
                    $commentCountsByPostId[$pid] = (int)$commentRow['CommentCount'];
                }
            } catch (Throwable $e) {
                // Comments table might not exist yet, use default 0
            }
        }

        // Fetch like counts for all posts
        $likeCountsByPostId = [];
        if (!empty($rows)) {
            $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            // Check if PostLikes table exists by trying to query it
            try {
                $likeSql = "
                    SELECT PostID, COUNT(*) AS LikeCount
                    FROM dbo.PostLikes
                    WHERE PostID IN ($placeholders)
                    GROUP BY PostID
                ";
                $likeStmt = $pdo->prepare($likeSql);
                $likeStmt->execute($postIds);

                while ($likeRow = $likeStmt->fetch(PDO::FETCH_ASSOC)) {
                    $pid = (int)$likeRow['PostID'];
                    $likeCountsByPostId[$pid] = (int)$likeRow['LikeCount'];
                }
            } catch (Throwable $e) {
                // PostLikes table might not exist yet, use default 0
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
                'commentCount' => $commentCountsByPostId[$pid] ?? 0,
                'likeCount'    => $likeCountsByPostId[$pid] ?? 0,
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

