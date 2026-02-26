<?php

namespace Forum\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

use function Forum\Helpers\json;
use function Forum\Helpers\checkUserBan;

final class CommentVoteController
{
    private $makePdo;

    public function __construct(callable $makePdo)
    {
        $this->makePdo = $makePdo;
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