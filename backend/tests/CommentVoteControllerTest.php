<?php
require_once __DIR__.'/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Forum\Controllers\CommentVoteController;
use Psr\Http\Message\ServerRequestInterface as Request;
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

        $checkComment = $this->createMock(PDOStatement::class);
        $checkComment->expects($this->once())
            ->method('execute')
            ->with([':commentId' => $commentId]);
        $checkComment->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(1); // Comment exists

        $checkVote = $this->createMock(PDOStatement::class);
        $checkVote->expects($this->once())
            ->method('execute')
            ->with([':commentId' => $commentId, ':userId' => $userId]);
        $checkVote->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false); // No existing vote

        $insertVote = $this->createMock(PDOStatement::class);
        $insertVote->expects($this->once())
            ->method('execute')
            ->with([':commentId' => $commentId, ':userId' => $userId, ':voteValue' => '1']);
        
        $incrementUpvotes = $this->createMock(PDOStatement::class);
        $incrementUpvotes->expects($this->once())
            ->method('execute')
            ->with([':commentId' => $commentId]);

        $totalVotes = $this->createMock(PDOStatement::class);
        $totalVotes->expects($this->once())
            ->method('execute')
            ->with([':commentId' => $commentId]);
        $totalVotes->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(['UpVotes' => 10, 'DownVotes' => 2]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(5))
            ->method('prepare')
            ->willReturnCallback(function(string $sql) use ($checkComment, $checkVote, $insertVote, $incrementUpvotes, $totalVotes) {
                if (strpos($sql, 'SELECT 1 FROM dbo.Comments') !== false) {
                    return $checkComment;
                } elseif (strpos($sql, 'SELECT VoteValue FROM dbo.CommentVotes') !== false) {
                    return $checkVote;
                } elseif (strpos($sql, 'INSERT INTO dbo.CommentVotes') !== false) {
                    return $insertVote;
                } elseif (strpos($sql, 'UPDATE dbo.Comments SET UpVotes = UpVotes + 1') !== false) {
                    return $incrementUpvotes;
                } elseif (strpos($sql, 'SELECT UpVotes, DownVotes FROM dbo.Comments') !== false) {
                    return $totalVotes;
                }
                throw new Exception("Unexpected SQL: $sql");
            });

        $controller = new CommentVoteController(fn () => $pdo);

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/comments/{$commentId}/vote")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['voteValue' => 1]);

        $response = $controller->vote($request, new Response(), ['id' => $commentId]);

        $this->assertEquals(200, $response->getStatusCode());

        $json = $this->decode($response);
        $this->assertEquals(true, $json['ok'] ?? null);
        $this->assertEquals(8, $json['score'] ?? null);
        $this->assertEquals($commentId, $json['commentId'] ?? null);
        $this->assertEquals($userId, $json['userId'] ?? null);
        $this->assertEquals(1, $json['voteValue'] ?? null);
        $this->assertEquals(10, $json['upvotes'] ?? null);
        $this->assertEquals(2, $json['downvotes'] ?? null);
    }
}