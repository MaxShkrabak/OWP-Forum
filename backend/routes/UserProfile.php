<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;

$app->get('/api/profile/{uid}/posts', function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        // Verify auth
        $userId = $req->getAttribute('user_id');

        if ($userId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }
        $authorId = (int)$args['uid'];

        $pdo = $makePdo();
        
        $params = $req->getQueryParams();
        
        $limit = min(max((int)($params['limit'] ?? 5), 1), 50);
        $page  = max((int)($params['page'] ?? 1), 1);
        
        // Sorting options
        $sort = strtolower($params['sort'] ?? 'latest');
        $orderBy = match($sort) {
            'oldest' => 'p.CreatedAt ASC',
            'title'  => 'p.Title ASC',
            default  => 'p.CreatedAt DESC',
        };

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dbo.Posts WHERE AuthorID = :uid");
        $countStmt->execute(['uid' => $authorId]);
        $totalPosts = (int)$countStmt->fetchColumn();

        $totalPages = (int)ceil($totalPosts / $limit);
        $page = ($page > $totalPages && $totalPages > 0) ? $totalPages : $page;
        $offset = ($page - 1) * $limit;

        $getPostsSql = "
            SELECT p.AuthorID, p.PostID, p.Title, p.CreatedAt, p.CategoryID,
                   u.FirstName, u.LastName, u.Avatar,
                   r.Name AS RoleName, c.Name AS CategoryName
            FROM dbo.Posts p
            LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
            LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
            LEFT JOIN dbo.Categories c ON p.CategoryID = c.CategoryID
            WHERE p.AuthorID = :uid
            ORDER BY $orderBy
            OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
        ";

        $rowstmt = $pdo->prepare($getPostsSql);
        $rowstmt->execute(['uid' => $authorId]);
        $rows = $rowstmt->fetchAll(PDO::FETCH_ASSOC);
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
            ORDER BY t.Name ASC
        ";
        $tagStmt = $pdo->prepare($getTagsSql);
        $tagStmt->execute($postIds);
        while ($tag = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
            $tagsByPostId[(int)$tag['PostID']][] = $tag['Name'];
        }

        $commentCounts = fetchCounts($pdo, 'dbo.Comments', $placeholders, $postIds, 'CommentCount');
        $likeCounts    = fetchCounts($pdo, 'dbo.PostLikes', $placeholders, $postIds, 'LikeCount');

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
                'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                'authorRole'   => $row['RoleName'] ?? 'User',
                'authorAvatar' => $row['Avatar'] ?? null,
                'tags'         => $tagsByPostId[$pid] ?? [],
                'commentCount' => $commentCounts[$pid] ?? 0,
                'likeCount'    => $likeCounts[$pid] ?? 0,
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
        usort($postsByCategory, fn($a, $b) => strcmp($a['categoryName'], $b['categoryName']));

        return json($res, [
            'posts'           => $posts,
            'postsByCategory' => $postsByCategory,
            'totalPosts'      => count($posts),
            'meta'         => [
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
});