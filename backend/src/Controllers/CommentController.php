<?php

namespace Forum\Controllers;

use PDO;
use Throwable;
use Closure;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\json;
use function Forum\Helpers\checkUserBan;

class CommentController
{
    private Closure $makePdo;

    public function __construct(Closure $makePdo)
    {
        $this->makePdo = $makePdo;
    }

    private function formatUserRow(array $row): array
    {
        return [
            'userId'    => (int)($row['UserId'] ?? $row['UserID'] ?? 0),
            'firstName' => $row['FirstName'] ?? null,
            'lastName'  => $row['LastName'] ?? null,
            'avatar'    => $row['Avatar'] ?? null,
            'role'      => $row['RoleName'] ?? 'user'
        ];
    }

    public function createComment(Request $req, Response $res, array $args): Response
    {
        try {
            $userId = $req->getAttribute("user_id");

            if (!$userId) {
                return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
            }

            $pdo = ($this->makePdo)();

            $banResponse = checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            $data = $req->getParsedBody() ?? [];
            $postId = isset($args['postId']) ? (int)$args['postId'] : (int)($data['post_id'] ?? 0);
            $content = trim((string)($data['content'] ?? ''));
            $parentCommentId = !empty($data['parentCommentId']) ? (int)$data['parentCommentId'] : null;

            if (!$postId || trim($content) === '') {
                return json($res, ['ok' => false, 'error' => 'Missing post_id or content'], 400);
            }

            $insertSql = "INSERT INTO dbo.Comments (PostID, UserId, Content, ParentCommentId) 
                          OUTPUT INSERTED.CommentId, INSERTED.CreatedAt 
                          VALUES (:postId, :userId, :content, :parentCommentId)";

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                ':postId' => $postId,
                ':userId' => $userId,
                ':content' => $content,
                ':parentCommentId' => $parentCommentId
            ]);
            $inserted = $stmt->fetch(PDO::FETCH_ASSOC);

            $commentDetailsSql = $pdo->prepare("
                SELECT c.CommentId, c.PostId, c.ParentCommentId, c.Content, c.CreatedAt, c.UserId, c.TotalScore,
                       u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                       0 AS MyVote,
                       (SELECT COUNT(*) FROM dbo.Comments r WHERE r.ParentCommentId = c.CommentId AND r.IsDeleted = 0) AS ReplyCount
                FROM dbo.Comments c
                JOIN dbo.Users u ON u.User_ID = c.UserId
                JOIN dbo.Roles r ON u.RoleID = r.RoleID
                WHERE c.CommentId = :commentId
            ");
            $commentDetailsSql->execute([':commentId' => (int)$inserted['CommentId']]);
            $row = $commentDetailsSql->fetch(PDO::FETCH_ASSOC);

            return json($res, [
                'ok' => true,
                'comment' => [
                    'commentId' => (int)$row['CommentId'],
                    'postId'    => (int)$row['PostId'],
                    'score'     => (int)$row['TotalScore'],
                    'myVote'    => 0,
                    'user'      => $this->formatUserRow($row),
                    'content'   => $row['Content'],
                    'createdAt' => strtotime($row['CreatedAt']),
                    'replyCount' => (int)$row['ReplyCount'],
                    'parentCommentId' => $row['ParentCommentId'] ? (int)$row['ParentCommentId'] : null,
                    'isDeleted' => false
                ]
            ], 201);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getPostComments(Request $req, Response $res, array $args): Response
    {
        try {
            $postId = (int)$args['postId'];
            $userId = $req->getAttribute("user_id") ?? 0;

            $queryParams = $req->getQueryParams();
            $limit = min(max((int)($queryParams['limit'] ?? 50), 1), 100);
            $page = max((int)($queryParams['page'] ?? 1), 1);
            $offset = ($page - 1) * $limit;

            $pdo = ($this->makePdo)();

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dbo.Comments WHERE PostId = :postId AND IsDeleted = 0");
            $countStmt->execute([':postId' => $postId]);
            $totalComments = (int)$countStmt->fetchColumn();

            $sql = "SELECT c.CommentId, c.PostId, c.ParentCommentId, c.Content, c.CreatedAt, c.UserId, c.TotalScore,
                           u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                           ISNULL(cv.VoteValue, 0) AS MyVote,
                           (SELECT COUNT(*) FROM dbo.Comments r WHERE r.ParentCommentId = c.CommentId AND IsDeleted = 0) AS ReplyCount
                    FROM dbo.Comments c
                    JOIN dbo.Users u ON u.User_ID = c.UserId
                    JOIN dbo.Roles r ON u.RoleID = r.RoleID
                    LEFT JOIN dbo.CommentVotes cv ON cv.CommentId = c.CommentId AND cv.UserId = :currentUserId
                    WHERE c.PostId = :postId AND c.IsDeleted = 0
                    ORDER BY c.CreatedAt ASC
                    OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':postId', $postId, PDO::PARAM_INT);
            $stmt->bindValue(':currentUserId', (int)$userId, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $items = array_map(function ($row) {
                return [
                    'commentId' => (int)$row['CommentId'],
                    'postId'    => (int)$row['PostId'],
                    'score'     => (int)$row['TotalScore'],
                    'myVote'    => (int)$row['MyVote'],
                    'user'      => $this->formatUserRow($row),
                    'content'   => $row['Content'],
                    'createdAt' => strtotime($row['CreatedAt']),
                    'replyCount' => (int)$row['ReplyCount'],
                    'parentCommentId' => $row['ParentCommentId'] ? (int)$row['ParentCommentId'] : null,
                    'isDeleted' => false
                ];
            }, $rows);

            return json($res, ['ok' => true, 'items' => $items, 'total' => $totalComments]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteComment(Request $req, Response $res, array $args): Response
    {
        try {
            if (!($userId = $req->getAttribute("user_id"))) {
                return json($res, ['ok' => false, 'error' => 'Not authenticated'], 401);
            }

            $pdo = ($this->makePdo)();
            $stmt = $pdo->prepare("UPDATE dbo.Comments SET IsDeleted = 1, DeletedAt = SYSUTCDATETIME() WHERE CommentId = :id AND UserId = :uid");
            $stmt->execute([':id' => (int)$args['id'], ':uid' => $userId]);

            return json($res, ['ok' => true]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getReplies(Request $req, Response $res, array $args): Response
    {
        try {
            $parentId = (int)$args['parentId'];
            $userId = $req->getAttribute("user_id") ?? 0;

            $pdo = ($this->makePdo)();

            $sql = "SELECT c.CommentId, c.PostId, c.ParentCommentId, c.Content, c.CreatedAt, c.UserId, c.TotalScore,
                           u.FirstName, u.LastName, u.Avatar, r.Name AS RoleName,
                           ISNULL(cv.VoteValue, 0) AS MyVote,
                           (SELECT COUNT(*) FROM dbo.Comments r WHERE r.ParentCommentId = c.CommentId AND IsDeleted = 0) AS ReplyCount
                    FROM dbo.Comments c
                    JOIN dbo.Users u ON u.User_ID = c.UserId
                    JOIN dbo.Roles r ON u.RoleID = r.RoleID
                    LEFT JOIN dbo.CommentVotes cv ON cv.CommentId = c.CommentId AND cv.UserId = :currentUserId
                    WHERE c.ParentCommentId = :parentId AND c.IsDeleted = 0
                    ORDER BY c.CreatedAt ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':parentId' => $parentId, ':currentUserId' => (int)$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $items = array_map(function ($row) {
                return [
                    'commentId' => (int)$row['CommentId'],
                    'postId'    => (int)$row['PostId'],
                    'score'     => (int)$row['TotalScore'],
                    'myVote'    => (int)$row['MyVote'],
                    'user'      => $this->formatUserRow($row),
                    'content'   => $row['Content'],
                    'createdAt' => strtotime($row['CreatedAt']),
                    'replyCount' => (int)$row['ReplyCount'],
                    'parentCommentId' => (int)$row['ParentCommentId']
                ];
            }, $rows);

            return json($res, ['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function vote(Request $req, Response $res, array $args): Response
    {
        try {
            if (($userId = $req->getAttribute('user_id')) === null) {
                return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
            }

            $commentId = (int)($args['id'] ?? 0);
            $data = $req->getParsedBody() ?? [];

            $action = strtolower((string)($data['dir'] ?? $data['action'] ?? ''));

            $pdo = ($this->makePdo)();

            $banResponse = checkUserBan($pdo, (int)$userId, $res);
            if ($banResponse) return $banResponse;

            $pdo->beginTransaction();

            $del = $pdo->prepare("DELETE FROM dbo.CommentVotes WHERE CommentId = :cid AND UserId = :uid");
            $del->execute([':cid' => $commentId, ':uid' => (int)$userId]);

            $newVoteValue = 0;
            if ($action === 'upvote') {
                $newVoteValue = 1;
            } elseif ($action === 'downvote') {
                $newVoteValue = -1;
            }

            if ($newVoteValue !== 0) {
                $ins = $pdo->prepare("INSERT INTO dbo.CommentVotes (CommentId, UserId, VoteValue) VALUES (:cid, :uid, :val)");
                $ins->execute([':cid' => $commentId, ':uid' => (int)$userId, ':val' => $newVoteValue]);
            }

            $pdo->commit();

            $scoreStmt = $pdo->prepare("SELECT TotalScore FROM dbo.Comments WHERE CommentId = :cid");
            $scoreStmt->execute([':cid' => $commentId]);
            $totalScore = (int)$scoreStmt->fetchColumn();

            return json($res, [
                'ok' => true,
                'score' => $totalScore,
                'myVote' => $newVoteValue
            ]);
        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
