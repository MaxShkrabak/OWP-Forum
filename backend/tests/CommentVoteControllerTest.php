<?php
require_once __DIR__.'/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Forum\Controllers\CommentVoteController;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CommentVoteControllerTest extends TestCase
{
    private function decode(Response $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function testUpvote(): void
    {
        $commentId = 1; // Assuming comment with ID 1 exists
        $userId = 1; // Assuming user with ID 1 exists

        $checkBanStmt = $this->createMock(PDOStatement::class);
        $checkBanStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $checkBanStmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(['IsBanned' => 0]);

        $deleteStmt = $this->createMock(PDOStatement::class);
        $deleteStmt->expects($this->once())
            ->method('execute')
            ->with([':cid' => $commentId, ':uid' => $userId]);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())
            ->method('execute')
            ->with([':cid' => $commentId, ':uid' => $userId, ':val' => 1]);

        $scoreStmt = $this->createMock(PDOStatement::class);
        $scoreStmt->expects($this->once())
            ->method('execute')
            ->with([':cid' => $commentId]);
        $scoreStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(8);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');

        $pdo->expects($this->exactly(4))
            ->method('prepare')
            ->willReturnCallback(function(string $sql) use ($checkBanStmt, $deleteStmt, $insertStmt, $scoreStmt) {
                if (str_contains($sql, 'dbo.Users')) {
                    return $checkBanStmt;
                } elseif (str_contains($sql, 'DELETE FROM dbo.CommentVotes')) {
                    return $deleteStmt;
                } elseif (str_contains($sql, 'INSERT INTO dbo.CommentVotes')) {
                    return $insertStmt;
                } elseif (str_contains($sql, 'SELECT TotalScore FROM dbo.Comments')) {
                    return $scoreStmt;
                }
                throw new Exception("Unexpected SQL: $sql");
            });

        $controller = new CommentVoteController(fn () => $pdo);

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/comments/{$commentId}/vote")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['dir' => 'upvote']);

        $response = $controller->vote($request, new Response(), ['id' => $commentId]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        
        $this->assertEquals(true, $json['ok'] ?? null);
        $this->assertEquals(8, $json['score'] ?? null);
        $this->assertEquals(1, $json['myVote'] ?? null);
    }
}