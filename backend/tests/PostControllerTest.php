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
        $this->pdo = $this->createStub(PDO::class);
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

        $termsStmt = $this->createMock(PDOStatement::class);
        $termsStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $termsStmt->method('fetch')->willReturn(['termsAccepted' => 1]);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $banStmt->method('fetch')->willReturn([
            'IsBanned' => 0,
            'BanType' => null,
            'BannedUntil' => null,
        ]);

        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $roleStmt->method('fetchColumn')->willReturn(1);

        $lockStmt = $this->createMock(PDOStatement::class);
        $lockStmt->expects($this->once())->method('execute')->with([':res' => "create_post_user_$userId"]);
        $lockStmt->method('fetchColumn')->willReturn(0);

        $recentPostsStmt = $this->createMock(PDOStatement::class);
        $recentPostsStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $recentPostsStmt->method('fetchColumn')->willReturn(0);

        $lastPostStmt = $this->createMock(PDOStatement::class);
        $lastPostStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $lastPostStmt->method('fetch')->willReturn(false);

        $categoryStmt = $this->createMock(PDOStatement::class);
        $categoryStmt->expects($this->once())->method('execute')->with([':catId' => $categoryId]);
        $categoryStmt->method('fetch')->willReturn([
            'CategoryID' => $categoryId,
            'UsableByRoleID' => 1,
        ]);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute')->with([
            ':title' => $title,
            ':categoryId' => $categoryId,
            ':authorId' => $userId,
            ':content' => $content,
            ':isCommentsDisabled' => 0,
        ]);
        $insertStmt->method('fetch')->willReturn([
            'PostID' => 1001,
            'CreatedAt' => '2026-03-21 05:00:00',
        ]);

        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($termsStmt, $banStmt, $roleStmt, $lockStmt, $recentPostsStmt, $lastPostStmt, $categoryStmt, $insertStmt) {
            $sql_lower = strtolower($sql);

            if (str_contains($sql_lower, 'termsaccepted')) {
                return $termsStmt;
            }
            if (str_contains($sql_lower, 'select') && str_contains($sql_lower, 'isbanned')) {
                return $banStmt;
            }
            if (str_contains($sql_lower, 'select isnull(roleid, 1)')) {
                return $roleStmt;
            }
            if (str_contains($sql_lower, 'sp_getapplock')) {
                return $lockStmt;
            }
            if (str_contains($sql_lower, 'select count(*) from dbo.forum_posts')) {
                return $recentPostsStmt;
            }
            if (str_contains($sql_lower, 'select top 1')) {
                return $lastPostStmt;
            }
            if (str_contains($sql_lower, 'from dbo.forum_categories')) {
                return $categoryStmt;
            }
            if (str_contains($sql_lower, 'insert into dbo.forum_posts')) {
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
        $this->assertEquals(60, $json['cooldownSeconds']);
    }

    public function testGetPostReturnsPostWhenFound(): void
    {
        $postId = 42;
        $userId = 7;

        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/get-post/$postId")
            ->withAttribute('user_id', $userId);

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchColumn')->willReturn(1);
        $postStmt->method('fetch')->willReturn([
            'PostID'       => $postId,
            'Title'        => 'Hello World',
            'Content'      => 'Some content',
            'CreatedAt'    => '2026-01-01 00:00:00',
            'UpdatedAt'    => null,
            'CategoryID'   => 2,
            'AuthorID'     => $userId,
            'TotalScore'   => 5,
            'ViewCount'    => 12,
            'FirstName'    => 'Jane',
            'LastName'     => 'Doe',
            'Avatar'       => 'pfp-0.png',
            'RoleName'     => 'User',
            'CategoryName' => 'Wastewater Collection',
            'myVote'       => 1,
        ]);

        $tagStmt = $this->createStub(PDOStatement::class);
        $tagStmt->method('execute')->willReturn(true);
        $tagStmt->method('fetch')->willReturnOnConsecutiveCalls(
            ['PostID' => $postId, 'TagID' => 3, 'Name' => 'Official'],
            false
        );

        $dedupStmt = $this->createStub(PDOStatement::class);
        $dedupStmt->method('execute')->willReturn(true);
        $dedupStmt->method('fetch')->willReturn([
            'LastViewedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($postStmt, $tagStmt, $dedupStmt) {
            if (str_contains(strtolower($sql), 'postviewdedup')) return $dedupStmt;
            if (str_contains(strtolower($sql), 'posttags')) return $tagStmt;
            return $postStmt;
        });

        $response = $this->controller->getPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals($postId, $json['post']['postId']);
        $this->assertEquals('Hello World', $json['post']['title']);
        $this->assertEquals('Jane Doe', $json['post']['authorName']);
        $this->assertEquals(5, $json['post']['totalScore']);
        $this->assertEquals(12, $json['post']['viewCount']);
        $this->assertEquals(1, $json['post']['myVote']);
        $this->assertEquals([['TagID' => 3, 'Name' => 'Official']], $json['post']['tags']);
    }

    public function testGetPostReturns404WhenNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/get-post/999');

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($postStmt);

        $response = $this->controller->getPost($request, new Response(), ['id' => 999]);

        $this->assertEquals(404, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
    }

    public function testGetPostsReturnsEmptyWhenNoPosts(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/posts');

        $countQueryStmt = $this->createStub(PDOStatement::class);
        $countQueryStmt->method('fetch')->willReturn(false);

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchAll')->willReturn([]);

        $this->pdo->method('query')->willReturn($countQueryStmt);
        $this->pdo->method('prepare')->willReturn($postStmt);

        $response = $this->controller->getPosts($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertEmpty($json['postsByCategory']);
        $this->assertEquals(0, $json['totalPosts']);
    }

    public function testGetPostsReturnsPostsGroupedByCategory(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/posts');

        $rows = [
            [
                'PostID' => 1, 'Title' => 'Post A', 'CreatedAt' => '2026-01-01',
                'CategoryID' => 10, 'TotalScore' => 3, 'commentCount' => 2,
                'User_ID' => 5, 'FirstName' => 'Alice', 'LastName' => 'Smith',
                'Avatar' => 'a.png', 'RoleName' => 'User', 'CategoryName' => 'Wastewater Collection', 'myVote' => 0,
            ],
            [
                'PostID' => 2, 'Title' => 'Post B', 'CreatedAt' => '2026-01-02',
                'CategoryID' => 10, 'TotalScore' => 1, 'commentCount' => 0,
                'User_ID' => 6, 'FirstName' => 'Bob', 'LastName' => 'Jones',
                'Avatar' => 'b.png', 'RoleName' => 'User', 'CategoryName' => 'Wastewater Collection', 'myVote' => 0,
            ],
        ];

        $countQueryStmt = $this->createStub(PDOStatement::class);
        $countQueryStmt->method('fetch')->willReturnOnConsecutiveCalls(
            ['CategoryID' => 10, 'postCount' => 2],
            false
        );

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchAll')->willReturn($rows);

        $tagStmt = $this->createStub(PDOStatement::class);
        $tagStmt->method('execute')->willReturn(true);
        $tagStmt->method('fetch')->willReturn(false);

        $this->pdo->method('query')->willReturn($countQueryStmt);
        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($postStmt, $tagStmt) {
            if (str_contains(strtolower($sql), 'posttags')) return $tagStmt;
            return $postStmt;
        });

        $response = $this->controller->getPosts($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertEquals(2, $json['totalPosts']);
        $this->assertCount(1, $json['postsByCategory']);
        $this->assertEquals(10, $json['postsByCategory'][0]['categoryId']);
        $this->assertCount(2, $json['postsByCategory'][0]['posts']);
        $this->assertEquals(2, $json['postsByCategory'][0]['postCount']);
    }

    public function testGetPinnedPostsReturnsEmptyWhenNoPins(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/posts/pinned');

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $response = $this->controller->getPinnedPosts($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEmpty($json['posts']);
    }

    public function testGetPinnedPostsReturnsPinnedPosts(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/posts/pinned');

        $rows = [[
            'PostID' => 5, 'Title' => 'Pinned Post', 'CreatedAt' => '2026-01-01',
            'CategoryID' => 1, 'TotalScore' => 10, 'commentCount' => 4,
            'User_ID' => 2, 'FirstName' => 'Admin', 'LastName' => 'User',
            'Avatar' => 'admin.png', 'RoleName' => 'Admin', 'CategoryName' => 'News', 'myVote' => 0,
        ]];

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchAll')->willReturn($rows);

        $tagStmt = $this->createStub(PDOStatement::class);
        $tagStmt->method('execute')->willReturn(true);
        $tagStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($postStmt, $tagStmt) {
            if (str_contains(strtolower($sql), 'posttags')) return $tagStmt;
            return $postStmt;
        });

        $response = $this->controller->getPinnedPosts($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertCount(1, $json['posts']);
        $this->assertTrue($json['posts'][0]['isPinned']);
        $this->assertEquals(5, $json['posts'][0]['postId']);
        $this->assertEquals(10, $json['posts'][0]['totalScore']);
    }

    public function testGetCategoryPostsReturns404WhenCategoryNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/categories/99/posts');

        $catStmt = $this->createStub(PDOStatement::class);
        $catStmt->method('execute')->willReturn(true);
        $catStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($catStmt);

        $response = $this->controller->getCategoryPosts($request, new Response(), ['id' => 99]);

        $this->assertEquals(404, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertEquals('Category not found', $json['error']);
    }

    public function testGetCategoryPostsReturnsPosts(): void
    {
        $categoryId = 3;
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/categories/$categoryId/posts");

        $catStmt = $this->createStub(PDOStatement::class);
        $catStmt->method('execute')->willReturn(true);
        $catStmt->method('fetch')->willReturn(['CategoryID' => $categoryId, 'Name' => 'Support']);

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(1);

        $postRows = [[
            'PostID' => 7, 'Title' => 'Help me', 'CreatedAt' => '2026-02-01',
            'TotalScore' => 0, 'commentCount' => 1,
            'User_ID' => 3, 'FirstName' => 'Joe', 'LastName' => 'Blogs',
            'Avatar' => 'joe.png', 'RoleName' => 'User', 'myVote' => 0,
        ]];

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('bindValue')->willReturn(true);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchAll')->willReturn($postRows);

        $tagStmt = $this->createStub(PDOStatement::class);
        $tagStmt->method('execute')->willReturn(true);
        $tagStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($catStmt, $countStmt, $postStmt, $tagStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'posttags'))    return $tagStmt;
            if (str_contains($lower, 'offset'))      return $postStmt;
            if (str_contains($lower, 'select count')) return $countStmt;
            return $catStmt;
        });

        $response = $this->controller->getCategoryPosts($request, new Response(), ['id' => $categoryId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertEquals($categoryId, $json['categoryId']);
        $this->assertEquals('Support', $json['categoryName']);
        $this->assertCount(1, $json['posts']);
        $this->assertEquals(7, $json['posts'][0]['postId']);
        $this->assertEquals(1, $json['meta']['totalPosts']);
    }

    public function testGetVerifyCategoriesReturnsCategories(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/verify/categories')
            ->withAttribute('user_id', 1);

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['CategoryID' => 1, 'Name' => 'Wastewater Collection'],
            ['CategoryID' => 2, 'Name' => 'Support'],
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $response = $this->controller->getVerifyCategories($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertCount(2, $json['items']);
        $this->assertEquals('Wastewater Collection', $json['items'][0]['name']);
    }

    public function testGetTagsReturnsTags(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/tags')
            ->withAttribute('user_id', 1);

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['TagID' => 1, 'Name' => 'Bug'],
            ['TagID' => 2, 'Name' => 'Official'],
        ]);

        $this->pdo->method('prepare')->willReturn($stmt);

        $response = $this->controller->getTags($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertCount(2, $json['items']);
    }

    public function testGetTagsFilterReturnsAllTags(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/tags/filter');

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['TagID' => 1, 'Name' => 'Bug'],
            ['TagID' => 2, 'Name' => 'Feature'],
            ['TagID' => 3, 'Name' => 'Official'],
        ]);

        $this->pdo->method('query')->willReturn($stmt);

        $response = $this->controller->getTagsFilter($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertCount(3, $json['items']);
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
        $banStmt->method('fetch')->willReturn([
            'IsBanned' => 1,
            'BanType' => 'permanent',
            'BannedUntil' => null,
        ]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($termsStmt, $banStmt) {
            $sql_lower = strtolower($sql);

            if (str_contains($sql_lower, 'termsaccepted')) {
                return $termsStmt;
            }
            if (str_contains($sql_lower, 'select') && str_contains($sql_lower, 'isbanned')) {
                return $banStmt;
            }
            throw new \Exception("Unexpected SQL: $sql");
        });

        $response = $this->controller->createPost($request, new Response());

        $this->assertEquals(403, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('Your account is restricted from performing this action', $json['error']);
    }
}
