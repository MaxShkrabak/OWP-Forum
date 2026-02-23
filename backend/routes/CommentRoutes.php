<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;
//For public users
function _user_row(array $row): array {
    return [
        'userId' => (int)$row['UserID'],
        'firstName' => $row['FirstName'] ?? null,
        'lastName' => $row['LastName'] ?? null,
        'avatar' => $row['Avatar'] ?? null
    ];
}

//Parses the vote value from the request body
function _parse_vote_value(array $data): ?int {
    if (isset($data['voteValue'])) {
        $value = (int)$data['voteValue'];
        return ($value === 1 || $value === -1) ? $value : null;
    }
    $dir = strtolower((string)($data['dir'] ?? $data['type'] ?? ''));
    if ($dir === 'upvote') {
        return 1;
    } if ($dir === 'downvote') {
        return -1;
    }
    return null;
}

$createCommentHandler = function (Request $req, Response $res, array $args = []) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        
        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();
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
                    return json($res, ['ok' => false, 'error' => 'You are banned and cannot comment.'], 403);
                }
            }
        } catch (Throwable $e) {
            // Columns may not exist yet (migration 008/009 not run)
        }

        //Check what post the comment belongs to, and the content of the comment
        $data = $req->getParsedBody() ?? [];
        $postId = isset($args['postId']) ? (int)$args['postId'] : (int)($data['post_id'] ?? 0);
        $content = trim((string)($data['content'] ?? ''));
        $parentCommentId = $data['parentCommentId'] ?? $data['parent_comment_id'] ?? null;

        if (!$postId || trim($content) === '') {
            return json($res, ['ok' => false, 'error' => 'Missing post_id or content'], 400);
        }

        // Check if the post exists
        $getPostSql = "SELECT 1 FROM dbo.Posts WHERE PostID = :postId AND IsDeleted = 0";
        $poststmt = $pdo->prepare($getPostSql);
        $poststmt->execute([':postId' => $postId]);

        if (!$poststmt->fetchColumn()) {
            return json($res, ['ok' => false, 'error' => 'Post not found'], 404);
        }

        if ($parentCommentId === null || $parentCommentId === '' || $parentCommentId === 0 || $parentCommentId === '0') {
            $parentCommentId = null;
        } else {
            $parentCommentId = (int)$parentCommentId;
            if ($parentCommentId <= 0) $parentCommentId = null;
        }

        // If a parent comment ID is provided, check if it exists and belongs to the same post
        if ($parentCommentId !== null) {
            $getCommentSql = "SELECT 1 FROM dbo.Comments WHERE CommentID = :commentId AND PostID = :postId AND IsDeleted = 0";
            $commentStmt = $pdo->prepare($getCommentSql);
            $commentStmt->execute([
                ':commentId' => $parentCommentId,
                ':postId' => $postId
            ]);

            if (!$commentStmt->fetchColumn()) {
                return json($res, ['ok' => false, 'error' => 'Parent comment not found or does not belong to the same post'], 400);
            }
        }

        $insertSql = "
            INSERT INTO dbo.Comments (PostID, UserID, Content, ParentCommentID)
            OUTPUT INSERTED.CommentID, INSERTED.CreatedAt
            VALUES (:postId, :userId, :content, :parentCommentId)
        ";

        $stmt = $pdo->prepare($insertSql);
        $stmt->bindValue(':postId', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':userId', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);

        if ($parentCommentId === null) {
            $stmt->bindValue(':parentCommentId', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parentCommentId', $parentCommentId, PDO::PARAM_INT);
        }

        $stmt->execute();

        $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inserted) {
            return json($res, ['ok' => false, 'error' => 'Failed to create comment'], 500);
        }

        
        // Fetch the full comment details to return in the response
        $commentID = (int)$inserted['CommentID'];
        $timestamp = strtotime($inserted['CreatedAt']);

        $commentDetailsSql = $pdo->prepare("
            SELECT c.CommentID, c.PostID, c.ParentCommentID, c.Content, c.CreatedAt, c.UserID,
                   u.FirstName, u.LastName, u.Avatar,
                   (SELECT COUNT(*) FROM dbo.Comments r WHERE r.ParentCommentID = c.CommentID AND r.IsDeleted = 0) AS ReplyCount
            FROM dbo.Comments c
            JOIN dbo.Users u ON u.User_ID = c.UserID
            WHERE c.CommentID = :commentId
        ");
        $commentDetailsSql->execute([':commentId' => $commentID]);
        $row = $commentDetailsSql->fetch(PDO::FETCH_ASSOC);

        // In the unlikely event that the comment details query fails, return the basic comment info
        if (!$row) {
            return json($res, [
                'ok' => true,
                'comment' => [
                    'commentId' => $commentID,
                    'postId' => $postId,
                    'user' => ['userId' => (int)$userId],
                    'content' => $content,
                    'createdAt' => $timestamp,
                    'replyCount' => 0,
                    'parentCommentId' => $parentCommentId,
                    'isDeleted' => false
                ]
            ], 201);
        }

        // Return the full comment details if the query succeeded
        return json($res, [
            'ok' => true,
            'comment' => [
                'commentId' => (int)$row['CommentID'],
                'postId' => (int)$row['PostID'],
                'user' => _user_row([
                    'UserID' => $row['UserID'],
                    'FirstName' => $row['FirstName'],
                    'LastName' => $row['LastName'],
                    'Avatar' => $row['Avatar']
                ]),
                'content' => $row['Content'],
                'createdAt' => strtotime($row['CreatedAt']),
                'replyCount' => (int)$row['ReplyCount'],
                'parentCommentId' => $row['ParentCommentID'] !== null ? (int)$row['ParentCommentID'] : null,
                'isDeleted' => false
            ]
        ], 201);
    }
    catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error'], 500);
    }
};

$app->post("/api/posts/{postId}/comments", $createCommentHandler);
$app->post("/api/create-comment", $createCommentHandler);

$app->get("/api/posts/{postId}/comments", function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $postId = (int)$args['postId'];

        if (!$postId) {
            return json($res, ['ok' => false, 'error' => 'Invalid post ID'], 400);
        }

        $queryParams = $req->getQueryParams();
        $limit = (int)($queryParams['limit'] ?? 50);
        $page = (int)($queryParams['page'] ?? 1);

        if ($limit <= 1) {
            $limit = 50;
        }
        if ($limit > 100) {
            $limit = 100;
        }
        if ($page <= 1) {
            $page = 1;
        }

        $offset = ($page - 1) * $limit;    

        $pdo = $makePdo();

        //returns total undeleted comments for the post (used for pagination on frontend)
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM dbo.Comments
            WHERE PostID = :postId AND IsDeleted = 0
        ");
        
        $countStmt->execute([':postId' => $postId]);
        $total = (int)$countStmt->fetchColumn();

        $commentsDetailsSql = "
            SELECT c.CommentID, c.PostID, c.ParentCommentID, c.Content, c.CreatedAt, c.UserID,
                   u.FirstName, u.LastName, u.Avatar,
                   (SELECT COUNT(*) FROM dbo.Comments r WHERE r.ParentCommentID = c.CommentID AND IsDeleted = 0) AS ReplyCount
            FROM dbo.Comments c
            JOIN dbo.Users u ON u.User_ID = c.UserID
            WHERE c.PostID = :postId AND c.IsDeleted = 0
            ORDER BY c.CreatedAt ASC
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        ";

        $stmt = $pdo->prepare($commentsDetailsSql);
        $stmt->bindValue(':postId', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'commentId' => (int)$row['CommentID'],
                'postId' => (int)$row['PostID'],
                'user' => _user_row([
                    'UserID' => $row['UserID'],
                    'FirstName' => $row['FirstName'],
                    'LastName' => $row['LastName'],
                    'Avatar' => $row['Avatar']
                ]),
                'content' => $row['Content'],
                'createdAt' => strtotime($row['CreatedAt']),
                'replyCount' => (int)$row['ReplyCount'],
                'parentCommentId' => $row['ParentCommentID'] !== null ? (int)$row['ParentCommentID'] : null,
                'isDeleted' => false
            ];
        }, $rows);

        return json($res, [
            'ok' => true,
            'postId' => $postId,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'items' => $items
        ]);
    }
    catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error'], 500);
    }
});

$app->delete("/api/comments/{id}", function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Not authenticated'], 401);
        }

        $commentId = (int)$args['id'];

        if (!$commentId) {
            return json($res, ['ok' => false, 'error' => 'Invalid comment ID'], 400);
        }

        $pdo = $makePdo();

        $ownerCommentSql = $pdo->prepare("SELECT UserID, IsDeleted FROM dbo.Comments WHERE CommentID = :commentId");
        $ownerCommentSql->execute([':commentId' => $commentId]);
        $row = $ownerCommentSql->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return json($res, ['ok' => false, 'error' => 'Comment not found'], 404);
        }

        if ((int)$row['IsDeleted'] === 1) {
            return json($res, ['ok' => true, 'commentId' => $commentId, 'deleted' => true]);
        }
        if ((int)$row['UserID'] !== (int)$userId) {
            return json($res, ['ok' => false, 'error' => 'Forbidden'], 403);
        }

        // Soft delete the comment by setting IsDeleted to 1
        $deleteCommentSql = $pdo->prepare("UPDATE dbo.Comments SET IsDeleted = 1, DeletedAt = SYSUTCDATETIME() WHERE CommentID = :commentId");
        $deleteCommentSql->execute([':commentId' => $commentId]);

        return json($res, ['ok' => true, 'commentId' => $commentId, 'deleted' => true]);
    }
    catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error'], 500);
    }
});

$app->post("/api/comments/{id}/report", function (Request $req, Response $res, array $args) use ($makePdo) {
    try {
        $userId = $req->getAttribute("user_id");
        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Not authenticated'], 401);
        }

        $commentId = (int)$args['id'];

        if (!$commentId) {
            return json($res, ['ok' => false, 'error' => 'Invalid comment ID'], 400);
        }

        $data = $req->getParsedBody() ?? [];
       $reportTag = (int)($data['reportTagId'] ?? $data['reportTag'] ?? $data['report_tag_id'] ?? 0);

        if (!$reportTag) {
            return json($res, ['ok' => false, 'error' => 'Report tag is required'], 400);
        }

        $pdo = $makePdo();

        $checkCommentSql = $pdo->prepare("SELECT PostID FROM dbo.Comments WHERE CommentID = :commentId");
        $checkCommentSql->execute([':commentId' => $commentId]);
        $postID = $checkCommentSql->fetchColumn();

        if (!$postID) {
            return json($res, ['ok' => false, 'error' => 'Comment not found'], 404);
        }

        $checkTagSql = $pdo->prepare("SELECT 1 FROM dbo.ReportTags WHERE ReportTagID = :reportTagId");
        $checkTagSql->execute([':reportTagId' => $reportTag]);

        if (!$checkTagSql->fetchColumn()) {
            return json($res, ['ok' => false, 'error' => 'Report tag not found'], 400);
        }

        $insertReportSql = $pdo->prepare("
            INSERT INTO dbo.Reports (ReportUserID, PostID, CommentID, ReportTagID)
            VALUES (:reporterUserId, :postId, :commentId, :reportTagId)
        ");

        $insertReportSql->execute([
            ':reporterUserId' => $userId,
            ':postId' => $postID,
            ':commentId' => $commentId,
            ':reportTagId' => $reportTag
        ]); 

        return json($res, ['ok' => true, 'reported' => true]);
    }
    catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => 'Server error'], 500);
    }
});

use Forum\Controllers\CommentVoteController;
$commentVoteController = new CommentVoteController($makePdo);

$app->post("/api/comments/{id}/vote", [$commentVoteController, 'vote']);