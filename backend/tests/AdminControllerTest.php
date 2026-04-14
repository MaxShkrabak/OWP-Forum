<?php
declare(strict_types=1);

namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use Forum\Controllers\AdminController;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;
use PDO;
use PDOStatement;

final class AdminControllerTest extends TestCase
{
    private $pdo;
    private AdminController $controller;

    protected function setUp(): void
    {
        $this->pdo = $this->createStub(PDO::class);
        $this->controller = new AdminController(fn() => $this->pdo);
    }

    private function decode(Response $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function makeRoleStmt(int $roleId): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn($roleId);
        return $stmt;
    }

    public function testReturns401WhenUnauthenticated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/users/5');

        $response = $this->controller->getUserById($request, new Response(), ['id' => '5']);

        $this->assertEquals(401, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('Not Authenticated', $json['error']);
    }

    public function testReturns403WhenInsufficientRole(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/users/5')
            ->withAttribute('user_id', 1);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(1));

        $response = $this->controller->getUserById($request, new Response(), ['id' => '5']);

        $this->assertEquals(403, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('Forbidden', $json['error']);
    }

    public function testAdminOnlyEndpointReturns403ForModerator(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/categories')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['name' => 'Test']);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(3));

        $response = $this->controller->createCategory($request, new Response());

        $this->assertEquals(403, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertStringContainsString('Forbidden (admin only)', $json['error']);
    }

    public function testGetUserByIdReturnsUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/users/5')
            ->withAttribute('user_id', 1);

        $roleStmt = $this->makeRoleStmt(3);

        $userStmt = $this->createStub(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetch')->willReturn([
            'UserID' => 5,
            'FirstName' => 'Jane',
            'LastName' => 'Doe',
        ]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $userStmt) {
            if (str_contains($sql, 'FirstName')) return $userStmt;
            return $roleStmt;
        });

        $response = $this->controller->getUserById($request, new Response(), ['id' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals(5, $json['user']['UserID']);
        $this->assertEquals('Jane', $json['user']['FirstName']);
    }

    public function testGetUserByIdReturns404WhenNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/users/999')
            ->withAttribute('user_id', 1);

        $roleStmt = $this->makeRoleStmt(3);

        $userStmt = $this->createStub(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $userStmt) {
            if (str_contains($sql, 'FirstName')) return $userStmt;
            return $roleStmt;
        });

        $response = $this->controller->getUserById($request, new Response(), ['id' => '999']);

        $this->assertEquals(404, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('User not found.', $json['error']);
    }

    public function testUpdateRoleCannotChangeOwnRole(): void
    {
        $adminId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/admin/users/$adminId/role")
            ->withAttribute('user_id', $adminId)
            ->withParsedBody(['roleId' => 1]);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(4));

        $response = $this->controller->updateRole($request, new Response(), ['id' => (string)$adminId]);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('You cannot change your own role', $json['error']);
    }

    public function testUpdateRoleRejectsInvalidRoleId(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/5/role')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['roleId' => 99]);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(4));

        $response = $this->controller->updateRole($request, new Response(), ['id' => '5']);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('roleId must be between 1 and 4', $json['error']);
    }

    public function testUpdateRoleSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/5/role')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['roleId' => 3]);

        $roleStmt = $this->makeRoleStmt(4);

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $updateStmt) {
            if (str_contains(strtolower($sql), 'update')) return $updateStmt;
            return $roleStmt;
        });

        $response = $this->controller->updateRole($request, new Response(), ['id' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
    }

    public function testSetBanCannotBanSelf(): void
    {
        $adminId = 10;
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/admin/users/$adminId/ban")
            ->withAttribute('user_id', $adminId)
            ->withParsedBody(['banned' => true, 'banType' => 'permanent']);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(4));

        $response = $this->controller->setBan($request, new Response(), ['id' => (string)$adminId]);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('You cannot ban yourself', $json['error']);
    }

    public function testSetBanCannotBanAnotherAdmin(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/20/ban')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['banned' => true, 'banType' => 'permanent']);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(4));

        $response = $this->controller->setBan($request, new Response(), ['id' => '20']);

        $this->assertEquals(403, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('You cannot ban another administrator', $json['error']);
    }

    public function testSetBanPermanentSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/5/ban')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['banned' => true, 'banType' => 'permanent']);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturnOnConsecutiveCalls(4, 1);

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $updateStmt) {
            if (str_contains(strtolower($sql), 'update')) return $updateStmt;
            return $roleStmt;
        });

        $response = $this->controller->setBan($request, new Response(), ['id' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertTrue($json['banned']);
        $this->assertEquals('permanent', $json['banType']);
        $this->assertNull($json['bannedUntil']);
    }

    public function testSetBanTemporaryRequiresBannedUntilDate(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/5/ban')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['banned' => true, 'banType' => 'temporary']);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturnOnConsecutiveCalls(4, 1);

        $this->pdo->method('prepare')->willReturn($roleStmt);

        $response = $this->controller->setBan($request, new Response(), ['id' => '5']);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('bannedUntil is required', $json['error']);
    }

    public function testSetBanTemporaryRejectsPastDate(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/5/ban')
            ->withAttribute('user_id', 10)
            ->withParsedBody([
                'banned' => true,
                'banType' => 'temporary',
                'bannedUntil' => '2020-01-01',
            ]);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturnOnConsecutiveCalls(4, 1);

        $this->pdo->method('prepare')->willReturn($roleStmt);

        $response = $this->controller->setBan($request, new Response(), ['id' => '5']);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('future date', $json['error']);
    }

    public function testSetBanTemporarySuccess(): void
    {
        $futureDate = (new \DateTimeImmutable('+30 days', new \DateTimeZone('UTC')))->format('Y-m-d');

        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/5/ban')
            ->withAttribute('user_id', 10)
            ->withParsedBody([
                'banned' => true,
                'banType' => 'temporary',
                'bannedUntil' => $futureDate,
            ]);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturnOnConsecutiveCalls(4, 1);

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $updateStmt) {
            if (str_contains(strtolower($sql), 'update')) return $updateStmt;
            return $roleStmt;
        });

        $response = $this->controller->setBan($request, new Response(), ['id' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertTrue($json['banned']);
        $this->assertEquals('temporary', $json['banType']);
        $this->assertNotNull($json['bannedUntil']);
    }

    public function testUnbanSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/admin/users/5/ban')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['banned' => false]);

        $roleStmt = $this->makeRoleStmt(4);

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $updateStmt) {
            if (str_contains(strtolower($sql), 'update')) return $updateStmt;
            return $roleStmt;
        });

        $response = $this->controller->setBan($request, new Response(), ['id' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertFalse($json['banned']);
        $this->assertNull($json['banType']);
        $this->assertNull($json['bannedUntil']);
    }

    public function testCreateCategoryRejectsEmptyName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/categories')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['name' => '']);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(4));

        $response = $this->controller->createCategory($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('Category name is required', $json['error']);
    }

    public function testCreateCategoryRejectsDuplicateName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/categories')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['name' => 'General']);

        $roleStmt = $this->makeRoleStmt(4);

        $dupStmt = $this->createStub(PDOStatement::class);
        $dupStmt->method('execute')->willReturn(true);
        $dupStmt->method('fetch')->willReturn(['CategoryID' => 1]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $dupStmt) {
            if (str_contains(strtolower($sql), 'from dbo.forum_categories')) return $dupStmt;
            return $roleStmt;
        });

        $response = $this->controller->createCategory($request, new Response());

        $this->assertEquals(409, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('already exists', $json['error']);
    }

    public function testCreateCategorySuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/categories')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['name' => 'New Category', 'usableByRoleId' => 1]);

        $roleStmt = $this->makeRoleStmt(4);

        $dupStmt = $this->createStub(PDOStatement::class);
        $dupStmt->method('execute')->willReturn(true);
        $dupStmt->method('fetch')->willReturn(false);

        $insertStmt = $this->createStub(PDOStatement::class);
        $insertStmt->method('execute')->willReturn(true);

        $idStmt = $this->createStub(PDOStatement::class);
        $idStmt->method('fetch')->willReturn(['id' => 42]);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $dupStmt, $insertStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'insert into')) return $insertStmt;
            if (str_contains($lower, 'from dbo.forum_categories')) return $dupStmt;
            return $roleStmt;
        });
        $this->pdo->method('query')->willReturn($idStmt);

        $response = $this->controller->createCategory($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
        $this->assertEquals(42, $json['categoryId']);
        $this->assertEquals('New Category', $json['name']);
    }

    public function testDeleteCategoryCannotDeleteGeneral(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/api/admin/categories/1')
            ->withAttribute('user_id', 10);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(4));
        $this->pdo->method('beginTransaction')->willReturn(true);

        $generalStmt = $this->createStub(PDOStatement::class);
        $generalStmt->method('fetch')->willReturn(['CategoryID' => 1]);
        $this->pdo->method('query')->willReturn($generalStmt);

        $response = $this->controller->deleteCategory($request, new Response(), ['id' => '1']);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('Cannot delete the General category', $json['error']);
    }

    public function testDeleteCategoryReassignsPostsToGeneral(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/api/admin/categories/5')
            ->withAttribute('user_id', 10);

        $roleStmt = $this->makeRoleStmt(4);

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $deleteStmt = $this->createStub(PDOStatement::class);
        $deleteStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $updateStmt, $deleteStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'update')) return $updateStmt;
            if (str_contains($lower, 'delete')) return $deleteStmt;
            return $roleStmt;
        });
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $generalStmt = $this->createStub(PDOStatement::class);
        $generalStmt->method('fetch')->willReturn(['CategoryID' => 1]);
        $this->pdo->method('query')->willReturn($generalStmt);

        $response = $this->controller->deleteCategory($request, new Response(), ['id' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
    }

    public function testCreateTagRejectsEmptyName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/tags')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['name' => '']);

        $this->pdo->method('prepare')->willReturn($this->makeRoleStmt(4));

        $response = $this->controller->createTag($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('Name is required', $json['error']);
    }

    public function testCreateTagRejectsDuplicateName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/tags')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['name' => 'Official']);

        $roleStmt = $this->makeRoleStmt(4);

        $dupStmt = $this->createStub(PDOStatement::class);
        $dupStmt->method('execute')->willReturn(true);
        $dupStmt->method('fetchColumn')->willReturn(1);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $dupStmt) {
            if (str_contains(strtolower($sql), 'forum_tags')) return $dupStmt;
            return $roleStmt;
        });

        $response = $this->controller->createTag($request, new Response());

        $this->assertEquals(409, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('Duplicate tag name', $json['error']);
    }

    public function testCreateTagSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/tags')
            ->withAttribute('user_id', 10)
            ->withParsedBody(['name' => 'NewTag', 'usableByRoleId' => 1]);

        $roleStmt = $this->makeRoleStmt(4);

        $dupStmt = $this->createStub(PDOStatement::class);
        $dupStmt->method('execute')->willReturn(true);
        $dupStmt->method('fetchColumn')->willReturn(false);

        $insertStmt = $this->createStub(PDOStatement::class);
        $insertStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $dupStmt, $insertStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'insert into')) return $insertStmt;
            if (str_contains($lower, 'forum_tags')) return $dupStmt;
            return $roleStmt;
        });

        $response = $this->controller->createTag($request, new Response());

        $this->assertEquals(201, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertTrue($json['ok']);
    }

    public function testDeleteTagReturns404WhenNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/api/admin/tags/999')
            ->withAttribute('user_id', 10);

        $roleStmt = $this->makeRoleStmt(4);

        $deletePostTagsStmt = $this->createStub(PDOStatement::class);
        $deletePostTagsStmt->method('execute')->willReturn(true);

        $deleteTagStmt = $this->createStub(PDOStatement::class);
        $deleteTagStmt->method('execute')->willReturn(true);
        $deleteTagStmt->method('rowCount')->willReturn(0);

        $this->pdo->method('prepare')->willReturnCallback(function (string $sql) use ($roleStmt, $deletePostTagsStmt, $deleteTagStmt) {
            $lower = strtolower($sql);
            if (str_contains($lower, 'forum_posttags')) return $deletePostTagsStmt;
            if (str_contains($lower, 'forum_tags')) return $deleteTagStmt;
            return $roleStmt;
        });
        $this->pdo->method('beginTransaction')->willReturn(true);

        $response = $this->controller->deleteTag($request, new Response(), ['id' => '999']);

        $this->assertEquals(404, $response->getStatusCode());
        $json = $this->decode($response);
        $this->assertFalse($json['ok']);
        $this->assertEquals('Tag not found.', $json['error']);
    }
}
