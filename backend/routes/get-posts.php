<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/posts', function (Request $req, Response $res) use ($makePdo) {
    try {
        $pdo = $makePdo();
        $q = $req->getQueryParams();

        // Optional sort (safe for now)
        $sort = isset($q['sort']) ? strtolower(trim($q['sort'])) : 'latest';
        $orderBy = "p.CreatedAt DESC";
        if ($sort === 'oldest') $orderBy = "p.CreatedAt ASC";

        // Optional paging (ONLY applied if both exist)
        $hasPaging = isset($q['page']) && isset($q['limit']);
        $page  = $hasPaging ? max(1, (int)$q['page']) : 1;
        $limit = $hasPaging ? max(1, min(100, (int)$q['limit'])) : 0;
        $offset = $hasPaging ? (($page - 1) * $limit) : 0;

        // Optional category filter (useful for category page later)
        $categoryId = (isset($q['categoryId']) && $q['categoryId'] !== '') ? (int)$q['categoryId'] : null;

        // Optional tag filter (AND semantics): tags=AI,Databases
        $tags = [];
        if (!empty($q['tags'])) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $q['tags']))));
        }
        $hasTags = count($tags) > 0;

        // WHERE + params
        $where = [];
        $params = [];

        if ($categoryId !== null) {
            $where[] = "p.CategoryID = ?";
            $params[] = $categoryId;
        }

        if ($hasTags) {
            $ph = implode(',', array_fill(0, count($tags), '?'));
            $where[] = "p.PostID IN (
                SELECT pt.PostID
                FROM dbo.PostTags pt
                INNER JOIN dbo.Tags t ON t.TagID = pt.TagID
                WHERE t.Name IN ($ph)
                GROUP BY pt.PostID
                HAVING COUNT(DISTINCT t.Name) = ?
            )";
            foreach ($tags as $t) $params[] = $t;
            $params[] = count($tags);
        }

        $whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

        // Main posts query (NO likes/comments yet)
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
            LEFT JOIN dbo.Users u ON p.AuthorID = u.User_ID
            LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
            LEFT JOIN dbo.Categories c ON p.CategoryID = c.CategoryID
            $whereSql
            ORDER BY $orderBy
        ";

        $sqlParams = $params;

        if ($hasPaging) {
            $sql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
            $sqlParams[] = $offset;
            $sqlParams[] = $limit;
        }

        $postStmt = $pdo->prepare($sql);
        $postStmt->execute($sqlParams);
        $rows = $postStmt->fetchAll(PDO::FETCH_ASSOC);

        // Tags: SAFE method.
        // - If paging: IN list is small (<=100), safe to use IN (?, ?, ...)
        // - If not paging: JOIN against Posts with same filters (NO huge IN list)
        $tagsByPostId = [];

        if (!empty($rows)) {
            if ($hasPaging) {
                $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);
                $ph = implode(',', array_fill(0, count($postIds), '?'));

                $tagSql = "
                    SELECT pt.PostID, t.Name
                    FROM dbo.PostTags pt
                    JOIN dbo.Tags t ON t.TagID = pt.TagID
                    WHERE pt.PostID IN ($ph)
                    ORDER BY t.Name ASC
                ";
                $tagStmt = $pdo->prepare($tagSql);
                $tagStmt->execute($postIds);

                while ($tagRow = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
                    $pid = (int)$tagRow['PostID'];
                    $tagsByPostId[$pid][] = $tagRow['Name'];
                }
            } else {
                // Unpaged: avoid parameter explosion by joining to Posts
                $tagSql = "
                    SELECT pt.PostID, t.Name
                    FROM dbo.Posts p
                    JOIN dbo.PostTags pt ON pt.PostID = p.PostID
                    JOIN dbo.Tags t ON t.TagID = pt.TagID
                    $whereSql
                    ORDER BY pt.PostID, t.Name
                ";
                $tagStmt = $pdo->prepare($tagSql);
                $tagStmt->execute($params);

                while ($tagRow = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
                    $pid = (int)$tagRow['PostID'];
                    $tagsByPostId[$pid][] = $tagRow['Name'];
                }
            }
        }

        // Build posts array and group by category
        $posts = [];
        $categoriesMap = [];

        foreach ($rows as $row) {
            $pid = (int)$row['PostID'];
            $catId = (int)$row['CategoryID'];
            $catName = $row['CategoryName'] ?? 'Uncategorized';

            if (!isset($categoriesMap[$catId])) {
                $categoriesMap[$catId] = [
                    'categoryId'   => $catId,
                    'categoryName' => $catName,
                    'posts'        => [],
                ];
            }

            $post = [
                'postId'       => $pid,
                'categoryId'   => $catId,
                'title'        => $row['Title'],
                'createdAt'    => $row['CreatedAt'],
                'authorName'   => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                'authorRole'   => $row['RoleName'] ?? 'User',
                'authorAvatar' => $row['Avatar'] ?? null,
                'tags'         => $tagsByPostId[$pid] ?? [],
                'commentCount' => 0,
                'likeCount'    => 0,
            ];

            $posts[] = $post;
            $categoriesMap[$catId]['posts'][] = $post;
        }

        $postsByCategory = array_values($categoriesMap);
        usort($postsByCategory, fn($a, $b) => strcmp($a['categoryName'], $b['categoryName']));

        $payload = [
            'posts'           => $posts,
            'postsByCategory' => $postsByCategory,
            'totalPosts'      => count($posts),
        ];

        // If you later want meta for paged calls, you can add it here.
        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json');

    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
