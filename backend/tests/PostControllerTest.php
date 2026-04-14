<?php

namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use Forum\Controllers\PostController;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;
use PDO;
use PDOStatement;

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
        $termsStmt->method('fetch')->willReturn(['TermsAccepted' => 1]);

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
            'VisibleFromRoleID' => 0,
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

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use (
            $termsStmt,
            $banStmt,
            $roleStmt,
            $lockStmt,
            $recentPostsStmt,
            $lastPostStmt,
            $categoryStmt,
            $insertStmt
        ) {
            $sqlLower = strtolower($sql);

            if (str_contains($sqlLower, 'termsaccepted')) {
                return $termsStmt;
            }
            if (str_contains($sqlLower, 'select') && str_contains($sqlLower, 'isbanned')) {
                return $banStmt;
            }
            if (str_contains($sqlLower, 'select isnull(roleid, 1)')) {
                return $roleStmt;
            }
            if (str_contains($sqlLower, 'sp_getapplock')) {
                return $lockStmt;
            }
            if (str_contains($sqlLower, 'select count(*) from dbo.forum_posts')) {
                return $recentPostsStmt;
            }
            if (str_contains($sqlLower, 'select top 1')) {
                return $lastPostStmt;
            }
            if (str_contains($sqlLower, 'from dbo.forum_categories')) {
                return $categoryStmt;
            }
            if (str_contains($sqlLower, 'insert into dbo.forum_posts')) {
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

        $visibilityStmt = $this->createStub(PDOStatement::class);
        $visibilityStmt->method('execute')->willReturn(true);
        $visibilityStmt->method('fetchColumn')->willReturn(0);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($postStmt, $tagStmt, $dedupStmt, $visibilityStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'postviewdedup')) return $dedupStmt;
            if (str_contains($lower, 'posttags')) return $tagStmt;
            if (str_contains($lower, 'select isnull(visiblefromroleid, 0)')) return $visibilityStmt;
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
        $this->assertEquals([['tagId' => 3, 'name' => 'Official']], $json['post']['tags']);
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

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetch')->willReturn(false);

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchAll')->willReturn([]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($countStmt, $postStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'count(*) as postcount')) return $countStmt;
            return $postStmt;
        });

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
                'UserID' => 5, 'FirstName' => 'Alice', 'LastName' => 'Smith',
                'Avatar' => 'a.png', 'RoleName' => 'User', 'CategoryName' => 'Wastewater Collection',
                'VisibleFromRoleID' => 0, 'myVote' => 0,
            ],
            [
                'PostID' => 2, 'Title' => 'Post B', 'CreatedAt' => '2026-01-02',
                'CategoryID' => 10, 'TotalScore' => 1, 'commentCount' => 0,
                'UserID' => 6, 'FirstName' => 'Bob', 'LastName' => 'Jones',
                'Avatar' => 'b.png', 'RoleName' => 'User', 'CategoryName' => 'Wastewater Collection',
                'VisibleFromRoleID' => 0, 'myVote' => 0,
            ],
        ];

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetch')->willReturnOnConsecutiveCalls(
            ['CategoryID' => 10, 'postCount' => 2],
            false
        );

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchAll')->willReturn($rows);

        $tagStmt = $this->createStub(PDOStatement::class);
        $tagStmt->method('execute')->willReturn(true);
        $tagStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($countStmt, $postStmt, $tagStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'posttags')) return $tagStmt;
            if (str_contains($lower, 'count(*) as postcount')) return $countStmt;
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
            'UserID' => 2, 'FirstName' => 'Admin', 'LastName' => 'User',
            'Avatar' => 'admin.png', 'RoleName' => 'Admin', 'CategoryName' => 'News',
            'VisibleFromRoleID' => 0, 'myVote' => 0,
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

        $visibilityStmt = $this->createStub(PDOStatement::class);
        $visibilityStmt->method('execute')->willReturn(true);
        $visibilityStmt->method('fetchColumn')->willReturn(0);

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(1);

        $postRows = [[
            'PostID' => 7, 'Title' => 'Help me', 'CreatedAt' => '2026-02-01',
            'TotalScore' => 0, 'commentCount' => 1,
            'UserID' => 3, 'FirstName' => 'Joe', 'LastName' => 'Blogs',
            'Avatar' => 'joe.png', 'RoleName' => 'User', 'myVote' => 0,
        ]];

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('bindValue')->willReturn(true);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetchAll')->willReturn($postRows);

        $tagStmt = $this->createStub(PDOStatement::class);
        $tagStmt->method('execute')->willReturn(true);
        $tagStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($catStmt, $visibilityStmt, $countStmt, $postStmt, $tagStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'select isnull(visiblefromroleid, 0)')) return $visibilityStmt;
            if (str_contains($lower, 'posttags')) return $tagStmt;
            if (str_contains($lower, 'offset')) return $postStmt;
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

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn(1);

        $categoriesStmt = $this->createStub(PDOStatement::class);
        $categoriesStmt->method('execute')->willReturn(true);
        $categoriesStmt->method('fetchAll')->willReturn([
            ['CategoryID' => 1, 'Name' => 'Wastewater Collection'],
            ['CategoryID' => 2, 'Name' => 'Support'],
        ]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $categoriesStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'select isnull(roleid, 0)') || str_contains($lower, 'select isnull(roleid, 1)')) {
                return $roleStmt;
            }
            return $categoriesStmt;
        });

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
        $termsStmt->method('fetch')->willReturn(['TermsAccepted' => 1]);

        $banStmt = $this->createMock(PDOStatement::class);
        $banStmt->expects($this->once())->method('execute')->with([':uid' => $userId]);
        $banStmt->method('fetch')->willReturn([
            'IsBanned' => 1,
            'BanType' => 'permanent',
            'BannedUntil' => null,
        ]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($termsStmt, $banStmt) {
            $sqlLower = strtolower($sql);

            if (str_contains($sqlLower, 'termsaccepted')) {
                return $termsStmt;
            }
            if (str_contains($sqlLower, 'select') && str_contains($sqlLower, 'isbanned')) {
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

    private function makeTermsStmt(bool $accepted = true): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['TermsAccepted' => $accepted ? 1 : 0]);
        return $stmt;
    }

    private function makeFallbackStmt(): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('rowCount')->willReturn(0);
        return $stmt;
    }

    public function testVoteOnPostReturns401WhenUnauthenticated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/posts/1/vote');

        $response = $this->controller->voteOnPost($request, new Response(), ['id' => 1]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testVoteOnPostUpvoteSuccess(): void
    {
        $userId = 5;
        $postId = 10;

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/$postId/vote")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['action' => 'up']);

        $termsStmt = $this->makeTermsStmt();

        $prevVoteStmt = $this->createStub(PDOStatement::class);
        $prevVoteStmt->method('execute')->willReturn(true);
        $prevVoteStmt->method('fetchColumn')->willReturn(false); // no previous vote

        $mergeStmt = $this->createStub(PDOStatement::class);
        $mergeStmt->method('execute')->willReturn(true);

        $authorStmt = $this->createStub(PDOStatement::class);
        $authorStmt->method('execute')->willReturn(true);
        $authorStmt->method('fetch')->willReturn(['AuthorID' => 99]);

        $scoreStmt = $this->createStub(PDOStatement::class);
        $scoreStmt->method('execute')->willReturn(true);
        $scoreStmt->method('fetchColumn')->willReturn(7);

        $fallback = $this->makeFallbackStmt();

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $prevVoteStmt, $mergeStmt, $authorStmt, $scoreStmt, $fallback) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted'))      return $termsStmt;
                if (str_contains($l, 'merge'))               return $mergeStmt;
                if (str_contains($l, 'votevalue'))           return $prevVoteStmt;
                if (str_contains($l, 'totalscore'))          return $scoreStmt;
                if (str_contains($l, 'authorid'))            return $authorStmt;
                return $fallback;
            }
        );

        $response = $this->controller->voteOnPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals(1, $json['myVote']);
        $this->assertEquals(7, $json['score']);
    }

    public function testVoteOnPostDownvoteSuccess(): void
    {
        $userId = 5;
        $postId = 10;

        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/$postId/vote")
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['action' => 'down']);

        $termsStmt = $this->makeTermsStmt();

        $prevVoteStmt = $this->createStub(PDOStatement::class);
        $prevVoteStmt->method('execute')->willReturn(true);
        $prevVoteStmt->method('fetchColumn')->willReturn(1); // was upvoted

        $mergeStmt = $this->createStub(PDOStatement::class);
        $mergeStmt->method('execute')->willReturn(true);

        $scoreStmt = $this->createStub(PDOStatement::class);
        $scoreStmt->method('execute')->willReturn(true);
        $scoreStmt->method('fetchColumn')->willReturn(-1);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $prevVoteStmt, $mergeStmt, $scoreStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted')) return $termsStmt;
                if (str_contains($l, 'merge'))          return $mergeStmt;
                if (str_contains($l, 'votevalue'))      return $prevVoteStmt;
                if (str_contains($l, 'totalscore'))     return $scoreStmt;
                return $mergeStmt; // fallback
            }
        );

        $response = $this->controller->voteOnPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals(-1, $json['myVote']);
        $this->assertEquals(-1, $json['score']);
    }

    public function testPinPostReturns401WhenUnauthenticated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/posts/1/pin');

        $response = $this->controller->pinPost($request, new Response(), ['id' => 1]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testPinPostReturns403ForNonModOrAdmin(): void
    {
        $userId = 5;
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/posts/1/pin')
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('Student');

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $roleStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted')) return $termsStmt;
                return $roleStmt;
            }
        );

        $response = $this->controller->pinPost($request, new Response(), ['id' => 1]);

        $this->assertEquals(403, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertEquals('Forbidden', $json['error']);
    }

    public function testPinPostReturns404WhenPostNotFound(): void
    {
        $userId = 5;
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/posts/999/pin')
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('Admin');

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $roleStmt, $postStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted')) return $termsStmt;
                if (str_contains($l, 'rolename'))      return $roleStmt;
                return $postStmt;
            }
        );

        $response = $this->controller->pinPost($request, new Response(), ['id' => 999]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPinPostPinsSuccessfully(): void
    {
        $userId = 5;
        $postId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/$postId/pin")
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('Admin');

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(['PostID' => $postId, 'CategoryID' => 1, 'IsDeleted' => 0]);

        $checkPinStmt = $this->createStub(PDOStatement::class);
        $checkPinStmt->method('execute')->willReturn(true);
        $checkPinStmt->method('fetchColumn')->willReturn(false); // not pinned yet

        $countPinStmt = $this->createStub(PDOStatement::class);
        $countPinStmt->method('execute')->willReturn(true);
        $countPinStmt->method('fetchColumn')->willReturn(1); // 1 pin already, under limit

        $insertPinStmt = $this->createStub(PDOStatement::class);
        $insertPinStmt->method('execute')->willReturn(true);

        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $roleStmt, $postStmt, $checkPinStmt, $countPinStmt, $insertPinStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted'))              return $termsStmt;
                if (str_contains($l, 'rolename'))                   return $roleStmt;
                if (str_contains($l, 'insert into dbo.forum_pinned')) return $insertPinStmt;
                if (str_contains($l, 'updlock'))                    return $checkPinStmt;
                if (str_contains($l, 'count(*)'))                   return $countPinStmt;
                return $postStmt;
            }
        );

        $response = $this->controller->pinPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertTrue($json['isPinned']);
    }

    public function testPinPostUnpinsAlreadyPinnedPost(): void
    {
        $userId = 5;
        $postId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/$postId/pin")
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('Admin');

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(['PostID' => $postId, 'CategoryID' => 1, 'IsDeleted' => 0]);

        $checkPinStmt = $this->createStub(PDOStatement::class);
        $checkPinStmt->method('execute')->willReturn(true);
        $checkPinStmt->method('fetchColumn')->willReturn(1); // already pinned

        $deletePinStmt = $this->createStub(PDOStatement::class);
        $deletePinStmt->method('execute')->willReturn(true);

        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $roleStmt, $postStmt, $checkPinStmt, $deletePinStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted'))                return $termsStmt;
                if (str_contains($l, 'rolename'))                     return $roleStmt;
                if (str_contains($l, 'delete from dbo.forum_pinned')) return $deletePinStmt;
                if (str_contains($l, 'updlock'))                      return $checkPinStmt;
                return $postStmt;
            }
        );

        $response = $this->controller->pinPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertFalse($json['isPinned']);
    }

    public function testPinPostReturns409WhenLimitReached(): void
    {
        $userId = 5;
        $postId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/posts/$postId/pin")
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('Admin');

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(['PostID' => $postId, 'CategoryID' => 1, 'IsDeleted' => 0]);

        $checkPinStmt = $this->createStub(PDOStatement::class);
        $checkPinStmt->method('execute')->willReturn(true);
        $checkPinStmt->method('fetchColumn')->willReturn(false); // not pinned

        $countPinStmt = $this->createStub(PDOStatement::class);
        $countPinStmt->method('execute')->willReturn(true);
        $countPinStmt->method('fetchColumn')->willReturn(2); // at limit

        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('rollBack')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $roleStmt, $postStmt, $checkPinStmt, $countPinStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted')) return $termsStmt;
                if (str_contains($l, 'rolename'))      return $roleStmt;
                if (str_contains($l, 'updlock'))        return $checkPinStmt;
                if (str_contains($l, 'count(*)'))       return $countPinStmt;
                return $postStmt;
            }
        );

        $response = $this->controller->pinPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(409, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertStringContainsString('Maximum of 2', $json['error']);
    }

    public function testDelPostReturns401WhenUnauthenticated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/api/posts/1');

        $response = $this->controller->delPost($request, new Response(), ['id' => 1]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDelPostReturns404WhenPostNotFound(): void
    {
        $userId = 5;
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/api/posts/999')
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(false); // not found

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $postStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted')) return $termsStmt;
                return $postStmt;
            }
        );

        $response = $this->controller->delPost($request, new Response(), ['id' => 999]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDelPostReturns403WhenNonAuthorNonMod(): void
    {
        $userId = 5;
        $postId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/posts/$postId")
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(['PostID' => $postId, 'AuthorID' => 99, 'IsDeleted' => 0]); // different author

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn(1); // regular user

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $postStmt, $roleStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted'))       return $termsStmt;
                if (str_contains($l, 'isnull(roleid'))       return $roleStmt;
                return $postStmt;
            }
        );

        $response = $this->controller->delPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelPostSuccessForAuthor(): void
    {
        $userId = 5;
        $postId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/posts/$postId")
            ->withAttribute('user_id', $userId);

        $termsStmt = $this->makeTermsStmt();

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(['PostID' => $postId, 'AuthorID' => $userId, 'IsDeleted' => 0]);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn(1);

        $delStmt = $this->createStub(PDOStatement::class);
        $delStmt->method('execute')->willReturn(true);
        $delStmt->method('rowCount')->willReturn(1);

        $outStmt = $this->createStub(PDOStatement::class);
        $outStmt->method('execute')->willReturn(true);
        $outStmt->method('fetch')->willReturn([
            'IsDeleted' => 1,
            'DeletedAt' => '2026-04-13 12:00:00',
            'UpdatedAt' => '2026-04-13 12:00:00',
        ]);

        $fallback = $this->makeFallbackStmt();

        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($termsStmt, $postStmt, $roleStmt, $delStmt, $outStmt, $fallback) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted'))              return $termsStmt;
                if (str_contains($l, 'isnull(roleid'))              return $roleStmt;
                if (str_contains($l, 'update') && str_contains($l, 'isdeleted = 1')) return $delStmt;
                if (str_contains($l, 'deletedat'))                  return $outStmt;
                if (str_contains($l, 'authorid'))                   return $postStmt;
                return $fallback;
            }
        );

        $response = $this->controller->delPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals($postId, $json['postId']);
        $this->assertTrue($json['isDeleted']);
        $this->assertNotNull($json['deletedAt']);
    }

    public function testEditPostReturns401WhenUnauthenticated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/posts/1');

        $response = $this->controller->editPost($request, new Response(), ['id' => 1]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testEditPostReturns400WhenMissingFields(): void
    {
        $userId = 5;
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/posts/10')
            ->withAttribute('user_id', $userId)
            ->withParsedBody(['title' => '', 'content' => 'Some text', 'category' => 1]);

        $response = $this->controller->editPost($request, new Response(), ['id' => 10]);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertStringContainsString('required', $json['error']);
    }

    public function testEditPostReturns400WhenContentTooLong(): void
    {
        $userId = 5;
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/posts/10')
            ->withAttribute('user_id', $userId)
            ->withParsedBody([
                'title'    => 'Valid title',
                'content'  => str_repeat('x', 50001),
                'category' => 1,
            ]);

        $response = $this->controller->editPost($request, new Response(), ['id' => 10]);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertStringContainsString('50,000', $json['error']);
    }

    public function testEditPostSuccessUpdatesPost(): void
    {
        $userId = 5;
        $postId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/posts/$postId")
            ->withAttribute('user_id', $userId)
            ->withParsedBody([
                'title'    => 'Updated Title',
                'content'  => '<p>Updated content</p>',
                'category' => 2,
                'tags'     => [1],
            ]);

        $termsStmt = $this->makeTermsStmt();

        $postStmt = $this->createStub(PDOStatement::class);
        $postStmt->method('execute')->willReturn(true);
        $postStmt->method('fetch')->willReturn(['PostID' => $postId, 'AuthorID' => $userId, 'IsDeleted' => 0]);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn(1);

        // category check
        $catStmt = $this->createStub(PDOStatement::class);
        $catStmt->method('execute')->willReturn(true);
        $catStmt->method('fetch')->willReturn([
            'CategoryID' => 2, 'UsableByRoleID' => 1, 'VisibleFromRoleID' => 0,
        ]);

        // update post
        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(1);

        $tagDeleteStmt = $this->createStub(PDOStatement::class);
        $tagDeleteStmt->method('execute')->willReturn(true);

        $tagCheckStmt = $this->createStub(PDOStatement::class);
        $tagCheckStmt->method('execute')->willReturn(true);
        $tagCheckStmt->method('fetchAll')->willReturn([1]);

        $tagInsertStmt = $this->createStub(PDOStatement::class);
        $tagInsertStmt->method('execute')->willReturn(true);

        $outStmt = $this->createStub(PDOStatement::class);
        $outStmt->method('execute')->willReturn(true);
        $outStmt->method('fetch')->willReturn([
            'PostID' => $postId, 'Title' => 'Updated Title', 'Content' => '<p>Updated content</p>',
            'CreatedAt' => '2026-01-01', 'CategoryID' => 2, 'UpdatedAt' => '2026-04-13',
            'CategoryName' => 'Help',
        ]);

        $tagOutStmt = $this->createStub(PDOStatement::class);
        $tagOutStmt->method('execute')->willReturn(true);
        $tagOutStmt->method('fetchAll')->willReturn([['Name' => 'Bug', 'TagID' => 1]]);

        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use (
                $termsStmt, $postStmt, $roleStmt, $catStmt, $updateStmt,
                $tagDeleteStmt, $tagCheckStmt, $tagInsertStmt, $outStmt, $tagOutStmt
            ) {
                $l = strtolower($sql);
                if (str_contains($l, 'termsaccepted'))                           return $termsStmt;
                if (str_contains($l, 'isnull(roleid'))                           return $roleStmt;
                if (str_contains($l, 'update dbo.forum_posts'))                  return $updateStmt;
                if (str_contains($l, 'delete from dbo.forum_posttags'))          return $tagDeleteStmt;
                if (str_contains($l, 'insert into dbo.forum_posttags'))          return $tagInsertStmt;
                if (str_contains($l, 'forum_tags') && str_contains($l, 'usablebyroleid')) return $tagCheckStmt;
                if (str_contains($l, 'usablebyroleid'))                          return $catStmt;
                if (str_contains($l, 'categoryname'))                            return $outStmt;
                if (str_contains($l, 'forum_posttags') && str_contains($l, 'join')) return $tagOutStmt;
                if (str_contains($l, 'authorid'))                                return $postStmt;
                return $this->makeFallbackStmt();
            }
        );

        $response = $this->controller->editPost($request, new Response(), ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals($postId, $json['post']['postId']);
        $this->assertEquals('Updated Title', $json['post']['title']);
        $this->assertEquals('Help', $json['post']['categoryName']);
        $this->assertContains('Bug', $json['post']['tags']);
    }

    public function testSearchPostsReturnsEmptyResults(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/posts/search')
            ->withQueryParams(['q' => 'nonexistent']);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn(0);

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(0);

        $dataStmt = $this->createStub(PDOStatement::class);
        $dataStmt->method('execute')->willReturn(true);
        $dataStmt->method('bindValue')->willReturn(true);
        $dataStmt->method('fetchAll')->willReturn([]);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($roleStmt, $countStmt, $dataStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'isnull(roleid'))  return $roleStmt;
                if (str_contains($l, 'select count(*)')) return $countStmt;
                return $dataStmt;
            }
        );

        $response = $this->controller->searchPosts($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEmpty($json['posts']);
        $this->assertEquals(0, $json['meta']['totalPosts']);
        $this->assertFalse($json['meta']['hasNextPage']);
    }

    public function testSearchPostsReturnsResultsWithMeta(): void
    {
        $userId = 5;
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/posts/search')
            ->withAttribute('user_id', $userId)
            ->withQueryParams(['q' => 'test', 'page' => '1', 'limit' => '10']);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn(1);

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('bindValue')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(1);

        $dataStmt = $this->createStub(PDOStatement::class);
        $dataStmt->method('execute')->willReturn(true);
        $dataStmt->method('bindValue')->willReturn(true);
        $dataStmt->method('fetchAll')->willReturn([[
            'PostID' => 42, 'Title' => 'Test post', 'CreatedAt' => '2026-04-01',
            'CategoryID' => 1, 'TotalScore' => 3, 'commentCount' => 2,
            'FirstName' => 'Jane', 'LastName' => 'Doe', 'Avatar' => 'j.png',
            'UserID' => 8, 'RoleName' => 'User', 'CategoryName' => 'General',
            'VisibleFromRoleID' => 0, 'myVote' => 0, 'IsPinned' => 0,
        ]]);

        $tagStmt = $this->createStub(PDOStatement::class);
        $tagStmt->method('execute')->willReturn(true);
        $tagStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($roleStmt, $countStmt, $dataStmt, $tagStmt) {
                $l = strtolower($sql);
                if (str_contains($l, 'isnull(roleid'))   return $roleStmt;
                if (str_contains($l, 'searchresults'))    return $dataStmt;
                if (str_contains($l, 'select count(*)'))  return $countStmt;
                if (str_contains($l, 'posttags'))         return $tagStmt;
                return $dataStmt;
            }
        );

        $response = $this->controller->searchPosts($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertCount(1, $json['posts']);
        $this->assertEquals(42, $json['posts'][0]['postId']);
        $this->assertEquals('Jane Doe', $json['posts'][0]['authorName']);
        $this->assertEquals(1, $json['meta']['totalPosts']);
        $this->assertEquals(1, $json['meta']['totalPages']);
        $this->assertFalse($json['meta']['hasNextPage']);
    }
}