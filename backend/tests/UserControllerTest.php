<?php
declare(strict_types=1);

namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Forum\Controllers\UserController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;
use PDO;
use PDOStatement;

class UserControllerTest extends TestCase
{
    private $pdo;
    private $stmt;
    private UserController $controller;

    protected function setUp(): void
    {
        $this->pdo = $this->createStub(PDO::class);
        $this->stmt = $this->createStub(PDOStatement::class);
        $this->controller = new UserController(fn() => $this->pdo);
    }

    public function test_getProfile_returns_camelCase_keys(): void
    {
        $req = $this->createStub(Request::class);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn([
            'UserID' => 5,
            'FirstName' => 'Alice',
            'LastName' => 'Smith',
            'Avatar' => 'pfp-2.png',
            'RoleName' => 'moderator',
        ]);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->getProfile($req, new Response(), ['uid' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);

        $user = $body['user'];
        $this->assertEquals(5, $user['userId']);
        $this->assertEquals('Alice', $user['firstName']);
        $this->assertEquals('Smith', $user['lastName']);
        $this->assertEquals('pfp-2.png', $user['avatar']);
        $this->assertEquals('moderator', $user['roleName']);

        // Verify old PascalCase keys are NOT present
        $this->assertArrayNotHasKey('User_ID', $user);
        $this->assertArrayNotHasKey('FirstName', $user);
        $this->assertArrayNotHasKey('RoleName', $user);
    }

    public function test_getProfile_returns_404_when_user_not_found(): void
    {
        $req = $this->createStub(Request::class);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->getProfile($req, new Response(), ['uid' => '999']);

        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('User not found', $body['error']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_acceptTerms_returns_401_when_unauthenticated(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(null);

        $response = $this->controller->acceptTerms($req, new Response());

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Not Authenticated', $body['error']);
    }

    public function test_acceptTerms_returns_ok_when_authenticated(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(10);

        $this->stmt->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->acceptTerms($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_updateAvatar_returns_400_when_avatar_empty(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);
        $req->method('getParsedBody')->willReturn(['avatar' => '']);

        $response = $this->controller->updateAvatar($req, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('No avatar provided', $body['error']);
    }

    public function test_updateAvatar_returns_ok_with_filename(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);
        $req->method('getParsedBody')->willReturn(['avatar' => '/uploads/pfp-3.png']);

        $this->stmt->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->updateAvatar($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertEquals('pfp-3.png', $body['newAvatar']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_getNotificationSettings_returns_401_when_unauthenticated(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(null);

        $response = $this->controller->getNotificationSettings($req, new Response());

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
    }

    public function test_getNotificationSettings_returns_settings(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn([
            'EmailNotificationsEnabled' => 1,
        ]);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->getNotificationSettings($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertTrue($body['settings']['emailNotifications']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_updateNotificationSettings_returns_400_for_missing_field(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);
        $req->method('getParsedBody')->willReturn([]);

        $response = $this->controller->updateNotificationSettings($req, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
    }

    public function test_getProfileStats_returns_stats(): void
    {
        $req = $this->createStub(Request::class);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn([
            'postCount' => 5,
            'voteScore' => 12,
            'commentCount' => 3,
        ]);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->getProfileStats($req, new Response(), ['uid' => '7']);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertEquals(5, $body['stats']['postCount']);
        $this->assertEquals(12, $body['stats']['voteScore']);
        $this->assertEquals(3, $body['stats']['commentCount']);
    }
}
