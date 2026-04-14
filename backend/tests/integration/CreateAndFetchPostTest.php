<?php
declare(strict_types=1);

namespace Forum\Tests\Integration;

use Dotenv\Dotenv;
use Forum\Controllers\PostController;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CreateAndFetchPostTest extends TestCase
{
    private PDO $pdo;
    private App $app;
    private array $userIds = [];
    private array $postIds = [];
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

        $postController = new PostController($makePdo);
        $this->app->post('/api/create-post', [$postController, 'createPost']);
        $this->app->get('/api/get-post/{id}', [$postController, 'getPost']);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        foreach (array_unique($this->sessionHashes) as $sessionHash) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Sessions WHERE TokenHash = :hash')
                ->execute([':hash' => $sessionHash]);
        }

        foreach (array_reverse(array_unique($this->postIds)) as $postId) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Posts WHERE PostID = :id')
                ->execute([':id' => $postId]);
        }

        foreach (array_reverse(array_unique($this->userIds)) as $userId) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Users WHERE UserID = :id')
                ->execute([':id' => $userId]);
        }
    }

    public function testCreatePostAndRetrieveById(): void
    {
        $userId = $this->seedUser();
        $session = $this->seedSession($userId);

        $categoryId = (int)$this->pdo->query('SELECT TOP 1 CategoryID FROM dbo.Forum_Categories WHERE UsableByRoleID <= 1 ORDER BY CategoryID')->fetchColumn();
        self::assertGreaterThan(0, $categoryId, 'A usable category is required for integration testing.');

        $title = 'Integration Post Title ' . bin2hex(random_bytes(4));
        $content = 'Integration post content ' . bin2hex(random_bytes(6));

        $createRequest = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/create-post')
            ->withCookieParams(['session' => $session])
            ->withParsedBody([
                'title' => $title,
                'content' => $content,
                'category' => $categoryId,
            ]);

        $createResponse = $this->app->handle($createRequest);
        $createJson = $this->decode($createResponse);

        self::assertContains($createResponse->getStatusCode(), [200, 201]);
        self::assertTrue($createJson['ok']);
        self::assertArrayHasKey('postId', $createJson);

        $postId = (int)$createJson['postId'];
        self::assertGreaterThan(0, $postId);
        $this->postIds[] = $postId;

        $getRequest = (new ServerRequestFactory())
            ->createServerRequest('GET', "/api/get-post/{$postId}");

        $getResponse = $this->app->handle($getRequest);
        $getJson = $this->decode($getResponse);

        self::assertSame(200, $getResponse->getStatusCode());
        self::assertTrue($getJson['ok']);
        self::assertSame($title, $getJson['post']['title']);
        self::assertSame($content, $getJson['post']['content']);
        self::assertSame($userId, $getJson['post']['authorId']);
        self::assertSame('John Doe', $getJson['post']['authorName']);
    }

    private function seedUser(): int
    {
        $roleId = (int)$this->pdo->query("SELECT TOP 1 RoleID FROM dbo.Forum_Roles WHERE Name = 'user'")->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Users (Email, FirstName, LastName, RoleID, EmailVerified, termsAccepted) 
        OUTPUT INSERTED.UserID 
        VALUES (:email, :firstName, :lastName, :roleId, 1, 1)');

        $stmt->execute([
            ':email' => 'create-fetch-' . bin2hex(random_bytes(6)) . '@example.com',
            ':firstName' => 'John',
            ':lastName' => 'Doe',
            ':roleId' => $roleId,
        ]);

        $userId = (int)$stmt->fetchColumn();
        $this->userIds[] = $userId;
        return $userId;
    }

    private function seedSession(int $userId): string
    {
        $sessionHash = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $sessionHash, $_ENV['HMAC_KEY']);

        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Sessions (UserID, TokenHash, ExpiresAt) VALUES (:userId, :tokenHash, DATEADD(hour, 24, SYSUTCDATETIME()))');
        $stmt->execute([
            ':userId' => $userId,
            ':tokenHash' => $tokenHash,
        ]);

        $this->sessionHashes[] = $tokenHash;
        return $sessionHash;
    }

    private function decode(ResponseInterface $response): array
    {
        $decode = json_decode((string)$response->getBody(), true);
        return is_array($decode) ? $decode : [];
    }
}