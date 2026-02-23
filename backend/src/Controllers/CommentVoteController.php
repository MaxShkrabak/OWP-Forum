<?php
namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use PDO;

use function Forum\Helpers\json;

final class CommentVoteController
{
    private $makePdo;

    public function __construct(callable $makePdo)
    {
        $this->makePdo = $makePdo;
    }

    // matches route: /api/comments/{id}/vote
    public function vote(Request $req, Response $res, array $args): Response
    {
        $pdo = null;

        try {
            $userId = $req->getAttribute("user_id");
            if (!$userId) {
                return json($res, ['ok' => false, 'error' => 'Not authenticated'], 401);
            }

            $commentId = (int)($args['id'] ?? 0);
            if (!$commentId) {
                return json($res, ['ok' => false, 'error' => 'Invalid comment ID'], 400);
            }

            $data = $req->getParsedBody() ?? [];
            $voteValue = $this->parseVoteValue($data);

            if ($voteValue === null) {
                return json($res, ['ok' => false, 'error' => 'Invalid vote value'], 400);
            }

            $pdo = ($this->makePdo)();

            $checkCommentSql = $pdo->prepare("SELECT 1 FROM dbo.Comments WHERE CommentID = :commentId AND IsDeleted = 0");
            $checkCommentSql->execute([':commentId' => $commentId]);

            if (!$checkCommentSql->fetchColumn()) {
                return json($res, ['ok' => false, 'error' => 'Comment not found'], 404);
            }

            $pdo->beginTransaction();

            $checkVoteSql = $pdo->prepare("SELECT VoteValue FROM dbo.CommentVotes WITH (UPDLOCK, HOLDLOCK) WHERE CommentID = :commentId AND UserID = :userId");
            $checkVoteSql->execute([
                ':commentId' => $commentId,
                ':userId' => (int)$userId
            ]);

            $existingVote = $checkVoteSql->fetch(PDO::FETCH_ASSOC);

            if (!$existingVote) {
                $ins = $pdo->prepare("INSERT INTO dbo.CommentVotes (CommentID, UserID, VoteValue) VALUES (:commentId, :userId, :voteValue)");
                $ins->execute([
                    ':commentId' => $commentId,
                    ':userId' => (int)$userId,
                    ':voteValue' => $voteValue
                ]);

                if ($voteValue === 1) {
                    $pdo->prepare("UPDATE dbo.Comments SET UpVotes = UpVotes + 1 WHERE CommentID = :commentId")
                        ->execute([':commentId' => $commentId]);
                } else {
                    $pdo->prepare("UPDATE dbo.Comments SET DownVotes = DownVotes + 1 WHERE CommentID = :commentId")
                        ->execute([':commentId' => $commentId]);
                }
            } else {
                $currentValue = (int)$existingVote['VoteValue'];

                if ($currentValue !== $voteValue) {
                    $upd = $pdo->prepare("UPDATE dbo.CommentVotes SET VoteValue = :voteValue, UpdatedAt = SYSUTCDATETIME() WHERE CommentID = :commentId AND UserID = :userId");
                    $upd->execute([
                        ':voteValue' => $voteValue,
                        ':commentId' => $commentId,
                        ':userId' => (int)$userId
                    ]);

                    if ($currentValue === 1 && $voteValue === -1) {
                        $pdo->prepare("UPDATE dbo.Comments SET UpVotes = CASE WHEN UpVotes > 0 THEN UpVotes - 1 ELSE 0 END, DownVotes = DownVotes + 1 WHERE CommentID = :commentId")
                            ->execute([':commentId' => $commentId]);
                    } else if ($currentValue === -1 && $voteValue === 1) {
                        $pdo->prepare("UPDATE dbo.Comments SET DownVotes = CASE WHEN DownVotes > 0 THEN DownVotes - 1 ELSE 0 END, UpVotes = UpVotes + 1 WHERE CommentID = :commentId")
                            ->execute([':commentId' => $commentId]);
                    }
                }
            }

            $totalVotesSql = $pdo->prepare("SELECT UpVotes, DownVotes FROM dbo.Comments WHERE CommentID = :commentId");
            $totalVotesSql->execute([':commentId' => $commentId]);
            $votes = $totalVotesSql->fetch(PDO::FETCH_ASSOC);

            $pdo->commit();

            return json($res, [
                'ok' => true,
                'commentId' => $commentId,
                'userId' => (int)$userId,
                'voteValue' => $voteValue,
                'upvotes' => (int)$votes['UpVotes'],
                'downvotes' => (int)$votes['DownVotes'],
                'score' => (int)$votes['UpVotes'] - (int)$votes['DownVotes']
            ]);
        } catch (Throwable $e) {
            try {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable $rollbackException) {
                // ignore
            }
            return json($res, ['ok' => false, 'error' => 'Server error'], 500);
        }
    }

    private function parseVoteValue(array $data): ?int
    {
        if (isset($data['voteValue'])) {
            $value = (int)$data['voteValue'];
            return ($value === 1 || $value === -1) ? $value : null;
        }

        $dir = strtolower((string)($data['dir'] ?? $data['type'] ?? ''));

        if ($dir === 'upvote') return 1;
        if ($dir === 'downvote') return -1;

        return null;
    }
}