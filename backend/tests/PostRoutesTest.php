<?php

namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use PDO;
use PDOStatement;

final class PostRoutesTest extends TestCase
{
    private function decode($response):array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function testCreatePostRateLimit(): void
    {
        $userId = 1;
        $pdo = $this->createMock(PDO::class);

        $termsStmt = $this->createMock(PDOStatement::class);
        $termsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $termsStmt->method('fetch')
            ->willReturn(['termsAccepted' => 1]);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $banStmt->method('fetch')
            ->willReturn([0 => 0, 1 => null, 2 => null]);

        $lockStmt = $this->createMock(PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_post_user_$userId"]);
        $lockStmt->method('fetchColumn')
            ->willReturn(0);

        $recentPostsStmt = $this->createMock(PDOStatement::class);
        $recentPostsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentPostsStmt->method('fetchColumn')
            ->willReturn(10);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        $pdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);
        $pdo->expects($this->never())
            ->method('commit');

        $pdo->method('prepare')
            ->willReturnCallback(
                function (string $sql) use ($termsStmt, $banStmt, $lockStmt, $recentPostsStmt) {
                    if (str_contains($sql, 'termsAccepted')) {
                        return $termsStmt;
                    } elseif (str_contains($sql, 'SELECT ISNULL(IsBanned, 0), BanType, BannedUntil')) {
                        return $banStmt;
                    } elseif (str_contains($sql, 'sp_getapplock')) {
                        return $lockStmt;
                    } elseif (str_contains($sql, 'SELECT COUNT(*) FROM dbo.Posts')) {
                        return $recentPostsStmt;
                    }

                    throw new \RuntimeException("Unexpected SQL: $sql");
                }
            );
        $app = AppFactory::create();
        $makePdo = fn() => $pdo;
        require __DIR__ . '/../routes/PostRoutes.php';

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/create-post')
            ->withAttribute('user_id', $userId)
            ->withParsedBody([
                'title' => 'Test Post Title',
                'content' => 'Test post content',
                'category' => 1,
                'tags' => [],
            ]);
        
        $response = $app->handle($request);
        $json = $this->decode($response);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertFalse($json['ok']);
        $this->assertEquals('You have reached the hourly post limit.', $json['error']);
    }

    public function testCreatePostRateLimitNotExceeded(): void
    {
        $userId = 1;
        $pdo = $this->createMock(PDO::class);

        $termsStmt = $this->createMock(PDOStatement::class);
        $termsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $termsStmt->method('fetch')
            ->willReturn(['termsAccepted' => 1]);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $banStmt->method('fetch')
            ->willReturn([0 => 0, 1 => null, 2 => null]);

        $lockStmt = $this->createMock(PDOStatement::class);
        $lockStmt->expects($this->once())
            ->method('execute')
            ->with([':res' => "create_post_user_$userId"]);
        $lockStmt->method('fetchColumn')
            ->willReturn(0);

        $recentPostsStmt = $this->createMock(PDOStatement::class);
        $recentPostsStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $recentPostsStmt->method('fetchColumn')
            ->willReturn(2);

        $lastPostStmt = $this->createMock(PDOStatement::class);
        $lastPostStmt->expects($this->once())
            ->method('execute')
            ->with([':uid' => $userId]);
        $lastPostStmt->method('fetch')
            ->willReturn(false);

        $categoryStmt = $this->createMock(PDOStatement::class);
        $categoryStmt->expects($this->once())
            ->method('execute')
            ->with([':catId' => 1, ':userId' => $userId]);
        $categoryStmt->method('fetch')
            ->willReturn(['CategoryID' => 1, 'UsableByRoleID' => 1, 'UserRole' => 1]);

        $insertPostStmt = $this->createMock(PDOStatement::class);
        $insertPostStmt->expects($this->once())
            ->method('execute')
            ->with([
                ':title' => 'Test Post Title',
                ':content' => 'Test post content',
                ':categoryId' => 1,
                ':authorId' => $userId,
            ]);
        $insertPostStmt->method('fetch')
            ->willReturn(['PostID' => 123, 'CreatedAt' => '2024-01-01 00:00:00']);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        $pdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        $pdo->expects($this->never())
            ->method('rollBack');

        $pdo->method('prepare')
            ->willReturnCallback(
                function (string $sql) use ($termsStmt, $banStmt, $lockStmt, $recentPostsStmt, $lastPostStmt, $categoryStmt, $insertPostStmt) {
                    if (str_contains($sql, 'termsAccepted')) {
                        return $termsStmt;
                    } elseif (str_contains($sql, 'SELECT ISNULL(IsBanned, 0), BanType, BannedUntil')) {
                        return $banStmt;
                    } elseif (str_contains($sql, 'sp_getapplock')) {
                        return $lockStmt;
                    } elseif (str_contains($sql, 'SELECT COUNT(*) FROM dbo.Posts')) {
                        return $recentPostsStmt;
                    } elseif (str_contains($sql, 'SELECT TOP 1 Title, CreatedAt')) {
                        return $lastPostStmt;
                    } elseif (str_contains($sql, 'SELECT CategoryID, UsableByRoleID')) {
                        return $categoryStmt;
                    } elseif (str_contains($sql, 'INSERT INTO dbo.Posts')) {
                        return $insertPostStmt;
                    }

                    throw new \RuntimeException("Unexpected SQL: $sql");
                }
            );
        $app = AppFactory::create();
        $makePdo = fn() => $pdo;
        require __DIR__ . '/../routes/PostRoutes.php';

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/create-post')
            ->withAttribute('user_id', $userId)
            ->withParsedBody([
                'title' => 'Test Post Title',
                'content' => 'Test post content',
                'category' => 1,
                'tags' => [],
            ]);
        
        $response = $app->handle($request);
        $json = $this->decode($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['ok']);
        $this->assertEquals(123, $json['postId']);
        $this->assertEquals('2024-01-01T00:00:00+00:00', $json['createdAt']);
    }
}