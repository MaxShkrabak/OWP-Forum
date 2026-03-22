<?php

namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use Forum\Controllers\PostController;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;
use PDO;
use PDOStatement;
use Throwable;

final class PostControllerTest extends TestCase
{
    private $pdo;
    private $controller;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->controller = new PostController(fn() => $this->pdo);
    }

    private function decode(Response $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function testCreatePostSucceedsWhenUserNotBanned(): void
    {
        $userId = 12;
        $categoryId = 4;
        $title = 'Test title';
        $content = 'Test content';

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/create-post')
            ->withAttribute('user_id', $userId)
            ->withParsedBody([
                'title' => $title,
                'content' => $content,
                'category' => $categoryId,
            ]);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $banStmt->method('fetch')->willReturn([0, null, null]);

        $termsStmt = $this->createMock(PDOStatement::class);
        $termsStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $termsStmt->method('fetch')->willReturn(['termsAccepted' => 1]);

        $lastPostStmt = $this->createMock(PDOStatement::class);
        $lastPostStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $lastPostStmt->method('fetch')->willReturn(false);

        $categoryStmt = $this->createMock(PDOStatement::class);
        $categoryStmt->expects($this->once())->method('execute')->with([':catId' => $categoryId, ':userId' => $userId]);
        $categoryStmt->method('fetch')->willReturn([
            'CategoryID' => $categoryId,
            'UsableByRoleID' => 1,
            'UserRole' => 1,
        ]);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute')->with([
            ':title' => $title,
            ':categoryId' => $categoryId,
            ':authorId' => $userId,
            ':content' => $content,
        ]);
        $insertStmt->method('fetch')->willReturn([
            'PostID' => 1001,
            'CreatedAt' => '2026-03-21 05:00:00',
        ]);

        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($banStmt, $termsStmt, $lastPostStmt, $categoryStmt, $insertStmt) {
            if (str_contains($sql, 'ISNULL(IsBanned')) {
                return $banStmt;
            }
            if (str_contains($sql, 'termsAccepted')) {
                return $termsStmt;
            }
            if (str_contains($sql, 'SELECT TOP 1 Title')) {
                return $lastPostStmt;
            }
            if (str_contains($sql, 'FROM dbo.Categories')) {
                return $categoryStmt;
            }
            if (str_contains($sql, 'INSERT INTO dbo.Posts')) {
                return $insertStmt;
            }

            throw new \Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createPost($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals(1001, $json['postId']);
        $this->assertArrayHasKey('createdAt', $json);
    }

    public function testCreatePostForbiddenWhenUserBanned(): void
    {
        $userId = 12;

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/create-post')
            ->withAttribute('user_id', $userId)
            ->withParsedBody([
                'title' => 'Will not matter',
                'content' => 'Will not matter',
                'category' => 1,
            ]);

        $termsStmt = $this->createMock(PDOStatement::class);
        $termsStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $termsStmt->method('fetch')->willReturn(['termsAccepted' => 1]);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $banStmt->method('fetch')->willReturn([1, 'permanent', null]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($termsStmt, $banStmt) {
            if (str_contains($sql, 'termsAccepted')) {
                return $termsStmt;
            }
            if (str_contains($sql, 'ISNULL(IsBanned')) {
                return $banStmt;
            }
            throw new \Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createPost($request, new Response());

        $this->assertEquals(403, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('You are banned and cannot create posts.', $json['error']);
    }
}
