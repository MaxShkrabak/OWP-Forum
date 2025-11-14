<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/categories/{id}/posts', function (Request $req, Response $res, array $args) use ($makePdo) {

    $categoryId = (int)$args['id'];
    $pdo = $makePdo();

    try {
        // Get category
        $catStmt = $pdo->prepare("
            SELECT CategoryID, Name
            FROM dbo.Categories
            WHERE CategoryID = :id
        ");
        $catStmt->execute(['id' => $categoryId]);
        $cat = $catStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cat) {
            $res->getBody()->write(json_encode(['error' => 'Category not found']));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Query params: limit, sort, page
        $queryParams = $req->getQueryParams();

        // limit (default 5, max 50)
        $limitRaw = $queryParams['limit'] ?? 5;
        $limit = (int)$limitRaw;
        if ($limit <= 0) $limit = 5;
        if ($limit > 50) $limit = 50;

        // page (default 1)
        $pageRaw = $queryParams['page'] ?? 1;
        $page = (int)$pageRaw;
        if ($page <= 0) $page = 1;

        // sort (latest | oldest | title)
        $sortRaw = strtolower($queryParams['sort'] ?? 'latest');
        switch ($sortRaw) {
            case 'oldest':
                $orderBy = 'p.CreatedAt ASC';
                break;
            case 'title':
                $orderBy = 'p.Title ASC';
                break;
            case 'latest':
            default:
                $orderBy = 'p.CreatedAt DESC';
                $sortRaw = 'latest';
        }

        // Count total posts for this category
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) AS Total
            FROM dbo.Posts
            WHERE CategoryID = :categoryId
        ");
        $countStmt->execute(['categoryId' => $categoryId]);
        $totalPosts = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);

        $totalPages = max(1, (int)ceil($totalPosts / $limit));
        if ($page > $totalPages) {
            $page = $totalPages; // clamp
        }

        $offset = ($page - 1) * $limit;

        // Get the posts for this page
        $sql = "
            SELECT
                p.PostID,
                p.Title,
                p.CreatedAt,
                u.FirstName,
                u.LastName
            FROM dbo.Posts p
            LEFT JOIN dbo.Users u
                ON p.AuthorID = u.User_ID
            WHERE p.CategoryID = :categoryId
            ORDER BY $orderBy
            OFFSET :offset ROWS
            FETCH NEXT :limit ROWS ONLY;
        ";

    $postStmt = $pdo->prepare($sql);
    $postStmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
    $postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $postStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $postStmt->execute();

    $rows = $postStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch tags for the posts in current page
    $tagsByPostId = [];
    if (!empty($rows)) {
        // collect PostIDs from current page
        $postIds = array_map(fn($r) => (int)$r['PostID'], $rows);

        // make ? placeholders
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        $tagSql = "
            SELECT pt.PostID, t.Name
            FROM dbo.PostTags pt
            JOIN dbo.Tags t ON t.TagID = pt.TagID
            WHERE pt.PostID IN ($placeholders)
            ORDER BY t.Name ASC;
        ";

        $tagStmt = $pdo->prepare($tagSql);
        $tagStmt->execute($postIds);

        while ($tagRow = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int)$tagRow['PostID'];
            $tagsByPostId[$pid][] = $tagRow['Name'];
        }
    }

    // finally build posts payload including tags[]
    $posts = array_map(function ($row) use ($tagsByPostId) {
        $pid = (int)$row['PostID'];

        return [
            'postId'     => $pid,
            'title'      => $row['Title'],
            'createdAt'  => $row['CreatedAt'],
            'authorName' => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
            'tags'       => $tagsByPostId[$pid] ?? [],
        ];
    }, $rows);

        $payload = [
            'categoryId'   => $categoryId,
            'categoryName' => $cat['Name'],
            'posts'        => $posts,
            'meta'         => [
                'limit'      => $limit,
                'sort'       => $sortRaw,
                'page'       => $page,
                'totalPosts' => $totalPosts,
                'totalPages' => $totalPages,
            ],
        ];

        $res->getBody()->write(json_encode($payload));
        return $res->withHeader('Content-Type', 'application/json');

    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
