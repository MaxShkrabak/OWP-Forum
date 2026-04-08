<?php

namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use Forum\Controllers\CommentController;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PDO;
use PDOStatement;
use Exception;

final class CommentControllerTest extends TestCase
{
    private $pdo;
    private $stmt;
    private $controller;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createStub(PDOStatement::class);

        $this->controller = new CommentController(fn() => $this->pdo);
    }

    private function decode(Response $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateCommentSuccess(): void
    {
        $postId = 101;
        $userId = 1;
        $sentNotifications = [];

        $this->controller = new CommentController(
            fn() => $this->pdo,
            function (array $message) use (&$sentNotifications) {
                $sentNotifications[] = $message;
                return true; // Simulate successful email sending
            }
        );

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => 'This is a brand new comment!']);

        $banStmt = $this->createMock(\PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $lockStmt = $this->createMock(\PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_comment_user_$userId"]);
        $lockStmt->method('fetchColumn')->willReturn(0);

        $recentCommentsStmt = $this->createMock(\PDOStatement::class);
        $recentCommentsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentCommentsStmt->method('fetchColumn')->willReturn(0);

        $lastCommentTimeStmt = $this->createMock(\PDOStatement::class);
        $lastCommentTimeStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $lastCommentTimeStmt->method('fetchColumn')->willReturn(false);

        $insertStmt = $this->createMock(\PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute');
        $insertStmt->method('fetch')->willReturn([
            'CommentId' => 55,
            'CreatedAt' => '2026-02-26 12:00:00'
        ]);

        $postOwnerStmt = $this->createMock(\PDOStatement::class);
        $postOwnerStmt->expects($this->once())->method('execute')->with([':postId' => $postId]);
        $postOwnerStmt->method('fetch')->willReturn([
            'PostID' => $postId,
            'Title' => 'Exciting Post Title',
            'AuthorID' => 99,
            'LastCommentNotificationSentAt' => null,
            'Email' => 'author@example.com',
            'FirstName' => 'Author',
            'LastName' => 'McAuthorface',
            'EmailNotificationsEnabled' => 1
        ]);

        $updateStmt = $this->createMock(\PDOStatement::class);
        $updateStmt->expects($this->once())->method('execute')->with([':postId' => $postId]);
        $selectStmt = $this->createMock(\PDOStatement::class);
        $selectStmt->expects($this->once())->method('execute')->with([':commentId' => 55]);
        $selectStmt->method('fetch')->willReturn([
            'CommentId' => 55,
            'PostId' => $postId,
            'ParentCommentId' => null,
            'Content' => 'This is a brand new comment!',
            'CreatedAt' => '2026-02-26 12:00:00',
            'UserId' => $userId,
            'TotalScore' => 0,
            'FirstName' => 'Joe',
            'LastName' => 'Rogers',
            'Avatar' => 'pfp-0.png',
            'RoleName' => 'student',
            'MyVote' => 0,
            'ReplyCount' => 0
        ]);

        $postAuthorStmt = $this->createStub(\PDOStatement::class);
        $postAuthorStmt->method('fetch')->willReturn(false);

        $postCheckStmt = $this->createStub(\PDOStatement::class);
        $postCheckStmt->method('fetch')->willReturn(['IsCommentsDisabled' => 0]);

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->once())->method('commit')->willReturn(true);
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use (
            $banStmt,
            $lockStmt,
            $recentCommentsStmt,
            $lastCommentTimeStmt,
            $insertStmt,
            $selectStmt,
            $postOwnerStmt,
            $updateStmt,
            $postAuthorStmt,
            $postCheckStmt
            ) {
            if (str_contains($sql, 'IsCommentsDisabled')) {
                return $postCheckStmt;
            }
            if (str_contains($sql, 'INSERT INTO dbo.Forum_Comments')) {
                return $insertStmt;
            }
            if (str_contains($sql, 'sp_getapplock')) {
                return $lockStmt;
            }
            if (str_contains($sql, 'DATEADD(HOUR, -1, SYSUTCDATETIME())')) {
                return $recentCommentsStmt;
            }
            if (str_contains($sql, 'SELECT TOP 1 CreatedAt')) {
                return $lastCommentTimeStmt;
            }
            if (str_contains($sql, 'SELECT AuthorID FROM dbo.Forum_Posts')) {
                return $postAuthorStmt;
            }
            if (str_contains($sql, 'SELECT p.PostID, p.Title, p.AuthorID')) {
                return $postOwnerStmt;
            }
            if (str_contains($sql, 'SELECT c.CommentId, c.PostId')) {
                return $selectStmt;
            }
            if (str_contains($sql, 'UPDATE dbo.Forum_Posts SET LastCommentNotificationSentAt')) {
                return $updateStmt;
            }
            if (str_contains($sql, 'dbo.Forum_Users')) {
                return $banStmt;
            }
            throw new \Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);

        $this->assertEquals(201, $response->getStatusCode());

        $json = $this->decode($response);
        $this->assertTrue($json['ok']);

        $this->assertEquals(55, $json['comment']['commentId']);
        $this->assertEquals('This is a brand new comment!', $json['comment']['content']);
        $this->assertEquals('Joe', $json['comment']['user']['firstName']);
        $this->assertEquals(0, $json['comment']['score']);
        $this->assertCount(1, $sentNotifications);
        $this->assertSame('New comment on your post: Exciting Post Title', $sentNotifications[0]['subject']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateCommentFailsOnEmptyContent(): void
    {
        $postId = 101;
        $userId = 1;

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => '   ']);

        $banStmt = $this->createMock(\PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($banStmt) {
            if (str_contains($sql, 'dbo.Forum_Users')) return $banStmt;
            throw new \Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);

        $json = $this->decode($response);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($json['ok']);
        $this->assertEquals('Missing post_id or content', $json['error']);
    }

    public function testGetPostCommentsSuccess(): void
    {
        $postId = 67;
        $userId = 1;

        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withQueryParams(['limit' => 10, 'page' => 1]);

        $fakeData = [
            [
                'CommentId' => 1,
                'PostId' => $postId,
                'ParentCommentId' => null,
                'Content' => 'What an amazing post!',
                'CreatedAt' => '2026-02-26 10:00:00',
                'UserId' => 5,
                'TotalScore' => 10,
                'FirstName' => 'Joe',
                'LastName' => 'Rogers',
                'Avatar' => 'pfp-0.png',
                'RoleName' => 'student',
                'MyVote' => 1,
                'ReplyCount' => 1,
                'IsDeleted' => 0
            ],
            [
                'CommentId' => 2,
                'PostId' => $postId,
                'ParentCommentId' => 1,
                'Content' => 'I disagree.',
                'CreatedAt' => '2026-02-26 11:00:00',
                'UserId' => 6,
                'TotalScore' => -3,
                'FirstName' => 'Jane',
                'LastName' => 'Smith',
                'Avatar' => 'pfp-1.png',
                'RoleName' => 'user',
                'MyVote' => -1,
                'ReplyCount' => 0,
                'IsDeleted' => 0
            ]
        ];

        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->method('fetchColumn')->willReturn(2);
        $this->stmt->method('fetchAll')->willReturn($fakeData);

        $response = $this->controller->getPostComments($request, new Response(), ['postId' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());

        $json = $this->decode($response);
        $this->assertTrue($json['ok']);

        $this->assertEquals(2, $json['total']);
        $this->assertCount(2, $json['items']);

        $this->assertEquals('What an amazing post!', $json['items'][0]['content']);
        $this->assertEquals('Joe', $json['items'][0]['user']['firstName']);
        $this->assertEquals(1, $json['items'][0]['replyCount']);
        $this->assertEquals(10, $json['items'][0]['score']);

        $this->assertEquals('I disagree.', $json['items'][1]['content']);
        $this->assertEquals(1, $json['items'][1]['parentCommentId']);
        $this->assertEquals(-3, $json['items'][1]['score']);

        $this->assertIsString($json['items'][0]['createdAt']);
        $this->assertIsString($json['items'][1]['createdAt']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testVoteSuccess(): void
    {
        $commentId = 1;
        $userId = 5;

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/comments/{$commentId}/vote")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['dir' => 'downvote']);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $deleteStmt = $this->createMock(PDOStatement::class);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())
            ->method('execute')
            ->with([':cid' => $commentId, ':uid' => $userId, ':val' => -1]);

        $scoreStmt = $this->createMock(PDOStatement::class);
        $scoreStmt->method('fetchColumn')->willReturn(42);

        $this->pdo->expects($this->exactly(4))
            ->method('prepare')
            ->willReturnCallback(function ($sql) use ($banStmt, $deleteStmt, $insertStmt, $scoreStmt) {
                if (str_contains($sql, 'dbo.Forum_Users')) return $banStmt;
                if (str_contains($sql, 'DELETE FROM')) return $deleteStmt;
                if (str_contains($sql, 'INSERT INTO')) return $insertStmt;
                if (str_contains($sql, 'SELECT TotalScore')) return $scoreStmt;
                throw new Exception("Unexpected SQL: $sql");
            });

        $response = $this->controller->vote($request, new Response(), ['id' => $commentId]);

        $json = $this->decode($response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(42, $json['score']);
        $this->assertEquals(-1, $json['myVote']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testVoteFailsForBannedUser(): void
    {   
        $commentId = 1;
        $userId = 99;
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/comments/{$commentId}/vote")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['dir' => 'upvote']);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn([
            'IsBanned' => 1,
            'BanType'  => 'permanent'
        ]);

        $this->pdo->expects($this->once())->method('prepare')->willReturn($banStmt);
        $this->pdo->expects($this->never())->method('beginTransaction');

        $response = $this->controller->vote($request, new Response(), ['id' => $commentId]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertFalse($this->decode($response)['ok']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDoesNotSendEmailWhenNotificationsAreDisabled(): void
    {
        $postId = 101;
        $userId = 1;
        $sentNotifications = [];

        $this->controller = new CommentController(
            fn() => $this->pdo,
            function (array $message) use (&$sentNotifications) {
                $sentNotifications[] = $message;
                return true;
            }
        );

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => 'This should not send an email.']);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $lockStmt = $this->createMock(\PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_comment_user_$userId"]);
        $lockStmt->method('fetchColumn')->willReturn(0);

        $recentCommentsStmt = $this->createMock(\PDOStatement::class);
        $recentCommentsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentCommentsStmt->method('fetchColumn')->willReturn(0);

        $lastCommentTimeStmt = $this->createMock(\PDOStatement::class);
        $lastCommentTimeStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $lastCommentTimeStmt->method('fetchColumn')->willReturn(false);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute');
        $insertStmt->method('fetch')->willReturn([
            'CommentId' => 56,
            'CreatedAt' => '2026-03-04 12:00:00'
        ]);

        $postOwnerStmt = $this->createMock(PDOStatement::class);
        $postOwnerStmt->expects($this->once())->method('execute')->with([':postId' => $postId]);
        $postOwnerStmt->method('fetch')->willReturn([
            'PostID' => $postId,
            'Title' => 'Disabled Notifications Post',
            'AuthorID' => 99,
            'LastCommentNotificationSentAt' => null,
            'Email' => 'author@example.com',
            'FirstName' => 'Author',
            'LastName' => 'Disabled',
            'EmailNotificationsEnabled' => 0
        ]);

        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->expects($this->once())->method('execute')->with([':commentId' => 56]);
        $selectStmt->method('fetch')->willReturn([
            'CommentId' => 56,
            'PostId' => $postId,
            'ParentCommentId' => null,
            'Content' => 'This should not send an email.',
            'CreatedAt' => '2026-03-04 12:00:00',
            'UserId' => $userId,
            'TotalScore' => 0,
            'FirstName' => 'Joe',
            'LastName' => 'Rogers',
            'Avatar' => 'pfp-0.png',
            'RoleName' => 'student',
            'MyVote' => 0,
            'ReplyCount' => 0
        ]);

        $postAuthorStmt = $this->createStub(\PDOStatement::class);
        $postAuthorStmt->method('fetch')->willReturn(false);

        $postCheckStmt = $this->createStub(\PDOStatement::class);
        $postCheckStmt->method('fetch')->willReturn(['IsCommentsDisabled' => 0]);

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->once())->method('commit')->willReturn(true);
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (
                $banStmt,
                $lockStmt,
                $recentCommentsStmt,
                $lastCommentTimeStmt,
                $insertStmt,
                $postOwnerStmt,
                $selectStmt,
                $postAuthorStmt,
                $postCheckStmt
                ) {
                if (str_contains($sql, 'IsCommentsDisabled')) {
                    return $postCheckStmt;
                }
                if (str_contains($sql, 'INSERT INTO dbo.Forum_Comments')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'sp_getapplock')) {
                    return $lockStmt;
                }
                if (str_contains($sql, 'DATEADD(HOUR, -1, SYSUTCDATETIME())')) {
                    return $recentCommentsStmt;
                }
                if (str_contains($sql, 'SELECT TOP 1 CreatedAt')) {
                    return $lastCommentTimeStmt;
                }
                if (str_contains($sql, 'SELECT AuthorID FROM dbo.Forum_Posts')) {
                    return $postAuthorStmt;
                }
                if (str_contains($sql, 'SELECT p.PostID, p.Title, p.AuthorID')) {
                    return $postOwnerStmt;
                }
                if (str_contains($sql, 'SELECT c.CommentId, c.PostId')) {
                    return $selectStmt;
                }
                if (str_contains($sql, 'dbo.Forum_Users')) {
                    return $banStmt;
                }

                throw new Exception("Unexpected SQL: $sql");
            }
        );

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);
        $json = $this->decode($response);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($json['ok']);
        $this->assertCount(0, $sentNotifications);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDoesNotSendEmailWithinCooldown(): void
    {
        $postId = 101;
        $userId = 1;
        $sentNotifications = [];

        $this->controller = new CommentController(
            fn() => $this->pdo,
            function (array $message) use (&$sentNotifications) {
                $sentNotifications[] = $message;
                return true;
            }
        );

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => 'This should be blocked by cooldown.']);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $lockStmt = $this->createMock(\PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_comment_user_$userId"]);
        $lockStmt->method('fetchColumn')->willReturn(0);

        $recentCommentsStmt = $this->createMock(\PDOStatement::class);
        $recentCommentsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentCommentsStmt->method('fetchColumn')->willReturn(0);

        $lastCommentTimeStmt = $this->createMock(\PDOStatement::class);
        $lastCommentTimeStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $lastCommentTimeStmt->method('fetchColumn')->willReturn(false);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute');
        $insertStmt->method('fetch')->willReturn([
            'CommentId' => 57,
            'CreatedAt' => '2026-03-04 12:00:00'
        ]);

        $postOwnerStmt = $this->createMock(PDOStatement::class);
        $postOwnerStmt->expects($this->once())->method('execute')->with([':postId' => $postId]);
        $postOwnerStmt->method('fetch')->willReturn([
            'PostID' => $postId,
            'Title' => 'Cooldown Post',
            'AuthorID' => 99,
            'LastCommentNotificationSentAt' => date('Y-m-d H:i:s'),
            'Email' => 'author@example.com',
            'FirstName' => 'Author',
            'LastName' => 'Cooldown',
            'EmailNotificationsEnabled' => 1
        ]);

        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->expects($this->once())->method('execute')->with([':commentId' => 57]);
        $selectStmt->method('fetch')->willReturn([
            'CommentId' => 57,
            'PostId' => $postId,
            'ParentCommentId' => null,
            'Content' => 'This should be blocked by cooldown.',
            'CreatedAt' => '2026-03-04 12:00:00',
            'UserId' => $userId,
            'TotalScore' => 0,
            'FirstName' => 'Joe',
            'LastName' => 'Rogers',
            'Avatar' => 'pfp-0.png',
            'RoleName' => 'student',
            'MyVote' => 0,
            'ReplyCount' => 0
        ]);

        $postAuthorStmt = $this->createStub(\PDOStatement::class);
        $postAuthorStmt->method('fetch')->willReturn(false);

        $postCheckStmt = $this->createStub(\PDOStatement::class);
        $postCheckStmt->method('fetch')->willReturn(['IsCommentsDisabled' => 0]);

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->once())->method('commit')->willReturn(true);
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($banStmt,
            $lockStmt,
            $recentCommentsStmt,
            $lastCommentTimeStmt,
            $insertStmt,
            $postOwnerStmt,
            $selectStmt,
            $postAuthorStmt,
            $postCheckStmt
            ) {
                if (str_contains($sql, 'IsCommentsDisabled')) {
                    return $postCheckStmt;
                }
                if (str_contains($sql, 'INSERT INTO dbo.Forum_Comments')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'sp_getapplock')) {
                    return $lockStmt;
                }
                if (str_contains($sql, 'DATEADD(HOUR, -1, SYSUTCDATETIME())')) {
                    return $recentCommentsStmt;
                }
                if (str_contains($sql, 'SELECT TOP 1 CreatedAt')) {
                    return $lastCommentTimeStmt;
                }
                if (str_contains($sql, 'SELECT AuthorID FROM dbo.Forum_Posts')) {
                    return $postAuthorStmt;
                }
                if (str_contains($sql, 'SELECT p.PostID, p.Title, p.AuthorID')) {
                    return $postOwnerStmt;
                }
                if (str_contains($sql, 'SELECT c.CommentId, c.PostId')) {
                    return $selectStmt;
                }
                if (str_contains($sql, 'dbo.Forum_Users')) {
                    return $banStmt;
                }

                throw new Exception("Unexpected SQL: $sql");
            }
        );

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);
        $json = $this->decode($response);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($json['ok']);
        $this->assertCount(0, $sentNotifications);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAuthorCommentDoesNotSendEmail(): void
    {
        $postId = 101;
        $userId = 1;
        $sentNotifications = [];

        $this->controller = new CommentController(
            fn() => $this->pdo,
            function (array $message) use (&$sentNotifications) {
                $sentNotifications[] = $message;
                return true;
            }
        );

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => 'Author is commenting on their own post.']);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $lockStmt = $this->createMock(\PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_comment_user_$userId"]);
        $lockStmt->method('fetchColumn')->willReturn(0);

        $recentCommentsStmt = $this->createMock(\PDOStatement::class);
        $recentCommentsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentCommentsStmt->method('fetchColumn')->willReturn(0);

        $lastCommentTimeStmt = $this->createMock(\PDOStatement::class);
        $lastCommentTimeStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $lastCommentTimeStmt->method('fetchColumn')->willReturn(false);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute');
        $insertStmt->method('fetch')->willReturn([
            'CommentId' => 58,
            'CreatedAt' => '2026-03-04 12:00:00'
        ]);

        $postOwnerStmt = $this->createMock(PDOStatement::class);
        $postOwnerStmt->expects($this->once())->method('execute')->with([':postId' => $postId]);
        $postOwnerStmt->method('fetch')->willReturn([
            'PostID' => $postId,
            'Title' => 'Author Owns This Post',
            'AuthorID' => $userId,
            'LastCommentNotificationSentAt' => null,
            'Email' => 'author@example.com',
            'FirstName' => 'Author',
            'LastName' => 'Owner',
            'EmailNotificationsEnabled' => 1
        ]);

        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->expects($this->once())->method('execute')->with([':commentId' => 58]);
        $selectStmt->method('fetch')->willReturn([
            'CommentId' => 58,
            'PostId' => $postId,
            'ParentCommentId' => null,
            'Content' => 'Author is commenting on their own post.',
            'CreatedAt' => '2026-03-04 12:00:00',
            'UserId' => $userId,
            'TotalScore' => 0,
            'FirstName' => 'Joe',
            'LastName' => 'Rogers',
            'Avatar' => 'pfp-0.png',
            'RoleName' => 'student',
            'MyVote' => 0,
            'ReplyCount' => 0
        ]);

        $postAuthorStmt = $this->createStub(\PDOStatement::class);
        $postAuthorStmt->method('fetch')->willReturn(false);

        $postCheckStmt = $this->createStub(\PDOStatement::class);
        $postCheckStmt->method('fetch')->willReturn(['IsCommentsDisabled' => 0]);

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->once())->method('commit')->willReturn(true);
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (
                $banStmt,
                $lockStmt,
                $recentCommentsStmt,
                $lastCommentTimeStmt,
                $insertStmt,
                $postOwnerStmt,
                $selectStmt,
                $postAuthorStmt,
                $postCheckStmt
                ) {
                if (str_contains($sql, 'IsCommentsDisabled')) {
                    return $postCheckStmt;
                }
                if (str_contains($sql, 'INSERT INTO dbo.Forum_Comments')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'sp_getapplock')) {
                    return $lockStmt;
                }
                if (str_contains($sql, 'DATEADD(HOUR, -1, SYSUTCDATETIME())')) {
                    return $recentCommentsStmt;
                }
                if (str_contains($sql, 'SELECT TOP 1 CreatedAt')) {
                    return $lastCommentTimeStmt;
                }
                if (str_contains($sql, 'SELECT AuthorID FROM dbo.Forum_Posts')) {
                    return $postAuthorStmt;
                }
                if (str_contains($sql, 'SELECT p.PostID, p.Title, p.AuthorID')) {
                    return $postOwnerStmt;
                }
                if (str_contains($sql, 'SELECT c.CommentId, c.PostId')) {
                    return $selectStmt;
                }
                if (str_contains($sql, 'dbo.Forum_Users')) {
                    return $banStmt;
                }

                throw new Exception("Unexpected SQL: $sql");
            }
        );

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);
        $json = $this->decode($response);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($json['ok']);
        $this->assertCount(0, $sentNotifications);
    }

    public static function staffRolesBypassCommentRateLimitProvider(): array
    {
        return [
            'moderator' => ['moderator'],
            'admin' => ['admin'],
        ];
    }

    #[AllowMockObjectsWithoutExpectations]
    #[\PHPUnit\Framework\Attributes\DataProvider('staffRolesBypassCommentRateLimitProvider')]
    public function testCreateCommentBypassesRateLimitForStaff(string $roleName): void
    {
        $postId = 101;
        $userId = 1;

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => "Staff {$roleName} comment"]);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $roleStmt->method('fetchColumn')->willReturn($roleName);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute');
        $insertStmt->method('fetch')->willReturn([
            'CommentId' => 59,
            'CreatedAt' => '2026-03-04 12:00:00',
        ]);

        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->expects($this->once())
            ->method('execute')
            ->with([':commentId' => 59]);
        $selectStmt->method('fetch')->willReturn([
            'CommentId' => 59,
            'PostId' => $postId,
            'ParentCommentId' => null,
            'Content' => "Staff {$roleName} comment",
            'CreatedAt' => '2026-03-04 12:00:00',
            'UserId' => $userId,
            'TotalScore' => 0,
            'FirstName' => 'Staff',
            'LastName' => 'User',
            'Avatar' => null,
            'RoleName' => $roleName,
            'MyVote' => 0,
            'ReplyCount' => 0,
        ]);

        $postOwnerStmt = $this->createMock(PDOStatement::class);
        $postOwnerStmt->expects($this->once())
            ->method('execute')
            ->with([':postId' => $postId]);
        $postOwnerStmt->method('fetch')->willReturn([
            'PostID' => $postId,
            'Title' => 'Staff Post',
            'AuthorID' => $userId,
            'LastCommentNotificationSentAt' => null,
            'Email' => 'staff@example.com',
            'FirstName' => 'Staff',
            'LastName' => 'User',
            'EmailNotificationsEnabled' => 1,
        ]);

        $postAuthorStmt = $this->createStub(\PDOStatement::class);
        $postAuthorStmt->method('fetch')->willReturn(false);

        $postCheckStmt = $this->createStub(\PDOStatement::class);
        $postCheckStmt->method('fetch')->willReturn(['IsCommentsDisabled' => 0]);

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->once())->method('commit')->willReturn(true);
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use (
            $banStmt,
            $roleStmt,
            $insertStmt,
            $selectStmt,
            $postOwnerStmt,
            $postAuthorStmt,
            $postCheckStmt,
            $roleName
        ) {
            if (str_contains($sql, 'IsCommentsDisabled')) {
                return $postCheckStmt;
            }
            if (str_contains($sql, 'SELECT LOWER(r.NAME)')) {
                return $roleStmt;
            }
            if (str_contains($sql, 'INSERT INTO dbo.Forum_Comments')) {
                return $insertStmt;
            }
            if (str_contains($sql, 'SELECT c.CommentId, c.PostId')) {
                return $selectStmt;
            }
            if (str_contains($sql, 'SELECT AuthorID FROM dbo.Forum_Posts')) {
                return $postAuthorStmt;
            }
            if (str_contains($sql, 'SELECT p.PostID, p.Title, p.AuthorID')) {
                return $postOwnerStmt;
            }
            if (str_contains($sql, 'ISNULL(IsBanned, 0) AS IsBanned')) {
                return $banStmt;
            }
            if (
                str_contains($sql, 'sp_getapplock')
                || str_contains($sql, 'DATEADD(HOUR, -1, SYSUTCDATETIME())')
                || str_contains($sql, 'SELECT TOP 1 CreatedAt')
            ) {
                throw new Exception("Rate-limit query should not run for role {$roleName}: $sql");
            }

            throw new Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);
        $json = $this->decode($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($json['ok']);
        $this->assertSame($roleName, $json['comment']['user']['role']);
    }


    #[AllowMockObjectsWithoutExpectations]
    public function testCreateCommentFailsWhenHourlyLimitExceeded(): void
    {
        $postId = 101;
        $userId = 1;

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => 'This comment should be blocked by hourly limit.']);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $roleStmt->method('fetchColumn')->willReturn('student');

        $lockStmt = $this->createMock(PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_comment_user_$userId"]);
        $lockStmt->method('fetchColumn')->willReturn(0);

        $recentCommentsStmt = $this->createMock(PDOStatement::class);
        $recentCommentsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentCommentsStmt->method('fetchColumn')->willReturn(50);

        $hourlyResetTimeStmt = $this->createMock(PDOStatement::class);
        $hourlyResetTimeStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $hourlyResetTimeStmt->method('fetchColumn')->willReturn(gmdate('Y-m-d H:i:s', time() - 3540));

        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $postCheckStmt = $this->createStub(\PDOStatement::class);
        $postCheckStmt->method('fetch')->willReturn(['IsCommentsDisabled' => 0]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use (
            $banStmt,
            $roleStmt,
            $lockStmt,
            $recentCommentsStmt,
            $hourlyResetTimeStmt,
            $postCheckStmt
        ) {
            if (str_contains($sql, 'IsCommentsDisabled')) {
                return $postCheckStmt;
            }
            if (str_contains($sql, 'SELECT LOWER(r.NAME)')) {
                return $roleStmt;
            }
            if (str_contains($sql, 'sp_getapplock')) {
                return $lockStmt;
            }
            if (str_contains($sql, 'SELECT COUNT(*)') && str_contains($sql, 'DATEADD(HOUR, -1, SYSUTCDATETIME())')) {
                return $recentCommentsStmt;
            }
            if (str_contains($sql, 'CreatedAt') && str_contains($sql, 'OFFSET')) {
                return $hourlyResetTimeStmt;
            }
            if (str_contains($sql, 'ISNULL(IsBanned, 0) AS IsBanned')) {
                return $banStmt;
            }

            throw new Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);
        $json = $this->decode($response);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertFalse($json['ok']);
        $this->assertSame('hourly_limit', $json['rateLimit']['type']);
        $this->assertSame(50, $json['rateLimit']['limit']);
        $this->assertGreaterThan(0, $json['rateLimit']['secondsLeft']);
        $this->assertStringContainsString("You've reached the 50 comments per hour limit.", $json['error']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateCommentFailsWhenCooldownNotPassed(): void
    {
        $postId = 101;
        $userId = 1;

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['content' => 'This comment should be blocked by cooldown.']);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->method('fetch')->willReturn(['IsBanned' => 0]);

        $lockStmt = $this->createMock(\PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_comment_user_$userId"]);
        $lockStmt->method('fetchColumn')->willReturn(0);

        $recentCommentsStmt = $this->createMock(\PDOStatement::class);
        $recentCommentsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentCommentsStmt->method('fetchColumn')->willReturn(0);

        $lastCommentTimeStmt = $this->createMock(\PDOStatement::class);
        $lastCommentTimeStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $lastCommentTimeStmt->method('fetchColumn')->willReturn(date('Y-m-d H:i:s', time() - 5));
        
        $this->pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->once())->method('rollBack')->willReturn(true);

        
        $postCheckStmt = $this->createStub(\PDOStatement::class);
        $postCheckStmt->method('fetch')->willReturn(['IsCommentsDisabled' => 0]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($banStmt, $lockStmt, $recentCommentsStmt, $lastCommentTimeStmt, $postCheckStmt) {
            if (str_contains($sql, 'IsCommentsDisabled')) {
                return $postCheckStmt;
            }
            if (str_contains($sql, 'sp_getapplock')) {
                return $lockStmt;
            }
            if (str_contains($sql, 'DATEADD(HOUR, -1, SYSUTCDATETIME())')) {
                return $recentCommentsStmt;
            }
            if (str_contains($sql, 'SELECT TOP 1 CreatedAt')) {
                return $lastCommentTimeStmt;
            }
            if (str_contains($sql, 'dbo.Forum_Users')) {
                return $banStmt;
            }

            throw new Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createComment($request, new Response(), ['postId' => $postId]);
        $json = $this->decode($response);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('Please wait', $json['error']);
        $this->assertStringContainsString('before commenting again', $json['error']);
    }
}