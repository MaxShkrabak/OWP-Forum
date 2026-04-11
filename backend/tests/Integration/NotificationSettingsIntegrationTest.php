<?php
declare(strict_types=1);

namespace Forum\Tests\Integration;

use Dotenv\Dotenv;
use Forum\Controllers\UserController;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class NotificationSettingsIntegrationTest extends TestCase
{
    private PDO $pdo;
    private App $app;
    private array $userIds = [];
    private array $sessionHashes = [];

    protected function setUp(): void
    {
        $backendDir = dirname(__DIR__, 2);

        if (!file_exists($backendDir . '/.env')) {
            $this->markTestSkipped('backend/.env is required for integration tests.');
        }

        Dotenv::createImmutable($backendDir)->load();

        if (empty($_ENV['HMAC_KEY'])) {
            $this->markTestSkipped('HMAC_KEY must be set in backend/.env.');
        }

        $this->pdo = (require $backendDir . '/src/Database.php')()();
        $makePdo = fn(): PDO => $this->pdo;
        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();
        $this->app->add(require $backendDir . '/middleware/SessionMiddleware.php');

        $userController = new UserController($makePdo);
        $this->app->get('/api/user/notification-settings', [$userController, 'getNotificationSettings']);
        $this->app->post('/api/user/notification-settings', [$userController, 'updateNotificationSettings']);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        foreach (array_unique($this->sessionHashes) as $sessionHash) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Sessions WHERE Token_Hash = :hash')
                ->execute([':hash' => $sessionHash]);
        }

        foreach (array_reverse(array_unique($this->userIds)) as $userId) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Users WHERE User_ID = :id')
                ->execute([':id' => $userId]);
        }
    }

    public function testGetAndUpdateNotificationSettings(): void
    {
        $userId = $this->createTestUser(true);
        $sessionHash = $this->createSessionForUser($userId);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/user/notification-settings')
            ->withCookieParams(['session' => $sessionHash]);
        
        $response = $this->app->handle($request);
        $Json = $this->getJsonResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($Json['ok']);
        self::assertTrue($Json['settings']['emailNotifications']);

        $updateRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/user/notification-settings')
            ->withCookieParams(['session' => $sessionHash])
            ->withParsedBody(['emailNotifications' => false]);

        $updateResponse = $this->app->handle($updateRequest);
        $updateJson = $this->getJsonResponse($updateResponse);

        self::assertSame(200, $updateResponse->getStatusCode());
        self::assertTrue($updateJson['ok']);
        self::assertFalse($updateJson['settings']['emailNotifications']);

        $finalRequest = (new ServerRequestFactory())->createServerRequest('GET', '/api/user/notification-settings')
            ->withCookieParams(['session' => $sessionHash]);

        $finalResponse = $this->app->handle($finalRequest);
        $finalJson = $this->getJsonResponse($finalResponse);

        self::assertSame(200, $finalResponse->getStatusCode());
        self::assertTrue($finalJson['ok']);
        self::assertFalse($finalJson['settings']['emailNotifications']);

        $stmt = $this->pdo->prepare("SELECT emailNotificationsEnabled FROM dbo.Forum_Users WHERE User_ID = :id");
        $stmt->execute([':id' => $userId]);

        self::assertSame(0, (int)$stmt->fetchColumn());
    }

    private function createTestUser(bool $emailNotificationsEnabled): int
    {
        $roleId = (int) $this->pdo->query("SELECT TOP 1 RoleID FROM dbo.Forum_Roles WHERE Name = 'user'")->fetchColumn();

        self::assertGreaterThan(0, $roleId, 'User role not found in database.');

        $stmt = $this->pdo->prepare("INSERT INTO dbo.Forum_Users (Email, FirstName, LastName, EmailVerified, termsAccepted, RoleID, emailNotificationsEnabled) 
        OUTPUT INSERTED.User_ID
        VALUES (:email, :firstName, :lastName, :emailVerified, :termsAccepted, :roleId, :emailNotifications)");
        
        $stmt->execute([
            ':email' => 'testuser' . bin2hex(random_bytes(5)) . '@example.com',
            ':firstName' => 'Test',
            ':lastName' => 'User',
            ':emailVerified' => 1,
            ':termsAccepted' => 1,
            ':roleId' => $roleId,
            ':emailNotifications' => $emailNotificationsEnabled ? 1 : 0,
        ]);

        $userId = (int)$stmt->fetchColumn();
        $this->userIds[] = $userId;
        return $userId;
    }

    private function createSessionForUser(int $userId): string
    {
        $sessionHash = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $sessionHash, $_ENV['HMAC_KEY']);

        $stmt = $this->pdo->prepare("INSERT INTO dbo.Forum_Sessions (User_ID, Token_Hash, Expires) VALUES (:userId, :hash, DATEADD(HOUR, 24, SYSUTCDATETIME()))");
        $stmt->execute([':userId' => $userId, ':hash' => $tokenHash]);
        $this->sessionHashes[] = $tokenHash;

        return $sessionHash;
    }

    private function getJsonResponse(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true);
        return is_array($body) ? $body : [];
    }
}