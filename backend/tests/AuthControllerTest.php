<?php
declare(strict_types=1);

namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Forum\Controllers\AuthController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;
use PDO;
use PDOStatement;

class AuthControllerTest extends TestCase
{
    private $pdo;
    private $stmt;
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->pdo = $this->createStub(PDO::class);
        $this->stmt = $this->createStub(PDOStatement::class);
        $this->controller = new AuthController(fn() => $this->pdo);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_me_returns_null_user_when_unauthenticated(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(null);

        $response = $this->controller->me($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertNull($body['user']);
    }

    public function test_me_returns_camelCase_keys(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(42);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn([
            'UserID' => 42,
            'Email' => 'test@example.com',
            'FirstName' => 'Jane',
            'LastName' => 'Doe',
            'Avatar' => 'pfp-1.png',
            'RoleName' => 'student',
            'RoleID' => 2,
            'IsBanned' => 0,
            'BanType' => null,
            'BannedUntil' => null,
            'EmailNotificationsEnabled' => 1,
            'termsAccepted' => 1,
            'termsAcceptedAt' => '2026-01-01 00:00:00',
        ]);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->me($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);

        $user = $body['user'];
        $this->assertEquals(42, $user['userId']);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('Jane', $user['firstName']);
        $this->assertEquals('Doe', $user['lastName']);
        $this->assertEquals('pfp-1.png', $user['avatar']);
        $this->assertEquals('student', $user['roleName']);
        $this->assertEquals(2, $user['roleId']);
        $this->assertEquals(0, $user['isBanned']);
        $this->assertNull($user['banType']);
        $this->assertEquals(1, $user['termsAccepted']);

        // Verify old PascalCase keys are NOT present
        $this->assertArrayNotHasKey('User_ID', $user);
        $this->assertArrayNotHasKey('FirstName', $user);
        $this->assertArrayNotHasKey('RoleName', $user);
        $this->assertArrayNotHasKey('RoleID', $user);
        $this->assertArrayNotHasKey('IsBanned', $user);
    }

    public function test_me_treats_expired_temporary_ban_as_unbanned(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn([
            'UserID' => 1,
            'Email' => 'banned@example.com',
            'FirstName' => 'Ban',
            'LastName' => 'User',
            'Avatar' => null,
            'RoleName' => 'user',
            'RoleID' => 1,
            'IsBanned' => 1,
            'BanType' => 'temporary',
            'BannedUntil' => '2020-01-01 00:00:00', // expired
            'EmailNotificationsEnabled' => 1,
            'termsAccepted' => 0,
            'termsAcceptedAt' => null,
        ]);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->me($req, new Response());
        $body = json_decode((string)$response->getBody(), true);

        $this->assertEquals(0, $body['user']['isBanned']);
        $this->assertNull($body['user']['banType']);
        $this->assertNull($body['user']['bannedUntil']);
    }

    public function test_me_returns_404_when_user_not_found(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(999);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->me($req, new Response());

        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('User not found', $body['error']);
    }

    public function test_verifyEmail_returns_400_when_email_empty(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getParsedBody')->willReturn(['email' => '']);

        $response = $this->controller->verifyEmail($req, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Email required', $body['error']);
    }

    public function test_verifyEmail_returns_emailExists_true_when_found(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getParsedBody')->willReturn(['email' => 'exists@test.com']);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchColumn')->willReturn(1);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->verifyEmail($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertTrue($body['emailExists']);
    }

    public function test_verifyEmail_returns_emailExists_false_when_not_found(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getParsedBody')->willReturn(['email' => 'new@test.com']);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchColumn')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->verifyEmail($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertFalse($body['emailExists']);
    }

    public function test_register_returns_400_when_email_already_exists(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getParsedBody')->willReturn([
            'first' => 'Test',
            'last' => 'User',
            'email' => 'exists@test.com',
        ]);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchColumn')->willReturn(1);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $response = $this->controller->register($req, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertStringContainsString('already exists', $body['message']);
    }

    public function test_login_returns_400_for_invalid_email(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getParsedBody')->willReturn([
            'email' => 'not-an-email',
            'otp' => '123456',
        ]);

        $response = $this->controller->login($req, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Valid email required', $body['error']);
    }

    public function test_login_returns_400_for_empty_otp(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getParsedBody')->willReturn([
            'email' => 'user@test.com',
            'otp' => '',
        ]);

        $response = $this->controller->login($req, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('One-Time Passcode required', $body['error']);
    }
}
