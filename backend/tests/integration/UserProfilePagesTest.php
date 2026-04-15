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

final class UserProfilePagesTest extends TestCase
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

        $userController = new UserController($makePdo);
        $this->app->get('/api/profile/{uid}', [$userController, 'getProfile']);
        $this->app->get('/api/profile/{uid}/posts', [$userController, 'getProfilePosts']);
        $this->app->get('/api/profile/{uid}/liked-posts', [$userController, 'getProfileLikedPosts']);
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
            $this->pdo->prepare('DELETE FROM dbo.Forum_PostVotes WHERE PostID = :id')
                ->execute([':id' => $postId]);
            $this->pdo->prepare('DELETE FROM dbo.Forum_Posts WHERE PostID = :id')
                ->execute([':id' => $postId]);
        }

        foreach(array_reverse(array_unique($this->userIds)) as $userId) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_PostVotes WHERE UserID = :id')
                ->execute([':id' => $userId]);
            $this->pdo->prepare('DELETE FROM dbo.Forum_Users WHERE UserID = :id')
                ->execute([':id' => $userId]);
        }
    }

    public function testProfilePagesReturnExpectedShapeForSeededUser(): void
    {
        $userId = $this->seedUser();
        $session = $this->seedSession($userId);

        $postId = $this->seedPost($userId, 'Profile owner post title', 'Profile owner post content');
        $this->postIds[] = $postId;

        $otherUserId = $this->seedUser();
        $otherPostId = $this->seedPost($otherUserId, 'Liked post title', 'Liked post content');
        $this->postIds[] = $otherPostId;

        $this->seedLike($userId, $otherPostId);

        $profileRequest = (new ServerRequestFactory())
            ->createServerRequest('GET', "/api/profile/{$userId}")
            ->withCookieParams(['session' => $session]);

        $profileResponse = $this->app->handle($profileRequest);
        $profileJson = $this->decode($profileResponse);

        self::assertSame(200, $profileResponse->getStatusCode());
        self::assertTrue($profileJson['ok']);
        self::assertSame($userId, $profileJson['user']['userId']);
        self::assertSame('John', $profileJson['user']['firstName']);
        self::assertSame('Doe', $profileJson['user']['lastName']);
        self::assertArrayHasKey('roleName', $profileJson['user']);

        $postsRequest = (new ServerRequestFactory())
            ->createServerRequest('GET', "/api/profile/{$userId}/posts")
            ->withCookieParams(['session' => $session]);

        $postsResponse = $this->app->handle($postsRequest);
        $postsJson = $this->decode($postsResponse);

        self::assertSame(200, $postsResponse->getStatusCode());
        self::assertTrue($postsJson['ok']);
        self::assertCount(1, $postsJson['posts']);
        self::assertSame(1, $postsJson['totalPosts']);
        self::assertSame($postId, $postsJson['posts'][0]['postId']);
        self::assertSame('Profile owner post title', $postsJson['posts'][0]['title']);
        self::assertSame($userId, $postsJson['posts'][0]['authorId']);
        self::assertSame('John Doe', $postsJson['posts'][0]['authorName']);
        self::assertArrayHasKey('meta', $postsJson);

        $likedRequest = (new ServerRequestFactory())
            ->createServerRequest('GET', "/api/profile/{$userId}/liked-posts")
            ->withCookieParams(['session' => $session]);

        $likedResponse = $this->app->handle($likedRequest);
        $likedJson = $this->decode($likedResponse);

        self::assertSame(200, $likedResponse->getStatusCode());
        self::assertTrue($likedJson['ok']);
        self::assertCount(1, $likedJson['posts']);
        self::assertSame($otherPostId, $likedJson['posts'][0]['postId']);
        self::assertSame('Liked post title', $likedJson['posts'][0]['title']);
        self::assertSame($otherUserId, $likedJson['posts'][0]['authorId']);
        self::assertSame('John Doe', $profileJson['user']['firstName'] . ' ' . $profileJson['user']['lastName']);
        self::assertArrayHasKey('meta', $likedJson);
    }

    private function seedUser(): int
    {
        $roleId = (int)$this->pdo->query("SELECT TOP 1 RoleID FROM dbo.Forum_Roles WHERE Name = 'user'")->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Users (Email, FirstName, LastName, RoleID, EmailVerified, termsAccepted) 
        OUTPUT INSERTED.UserID 
        VALUES (:email, :firstName, :lastName, :roleId, 1, 1)');

        $stmt->execute([
            ':email' => 'profile-user-' . bin2hex(random_bytes(6)) . '@example.com',
            ':firstName' => 'John',
            ':lastName' => 'Doe',
            ':roleId' => $roleId,
        ]);

        $userId = (int)$stmt->fetchColumn();
        $this->userIds[] = $userId;
        return $userId;
    }

    private function seedPost(int $userId, string $title, string $content): int
    {
        $categoryId = (int)$this->pdo->query('SELECT TOP 1 CategoryID FROM dbo.Forum_Categories WHERE UsableByRoleID <= 1 ORDER BY CategoryID')->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Posts (AuthorID, CategoryID, Title, Content, isCommentsDisabled) 
        OUTPUT INSERTED.PostID 
        VALUES (:authorId, :categoryId, :title, :content, 0)');

        $stmt->execute([
            ':authorId' => $userId,
            ':categoryId' => $categoryId,
            ':title' => $title,
            ':content' => $content,
        ]);

        return (int)$stmt->fetchColumn();
    }

    private function seedLike(int $userId, int $postId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_PostVotes (PostID, UserID, VoteValue) VALUES (:postId, :userId, 1)');
        $stmt->execute([':postId' => $postId, ':userId' => $userId]);
    }

    private function seedSession(int $userId): string
    {
        $sessionHash = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $sessionHash, $_ENV['HMAC_KEY']);

        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Sessions (UserID, TokenHash, ExpiresAt) VALUES (:userId, :tokenHash, DATEADD(hour, 24, SYSUTCDATETIME()))');
        $stmt->execute([':userId' => $userId, ':tokenHash' => $tokenHash]);

        $this->sessionHashes[] = $tokenHash;
        return $sessionHash;
    }

    private function decode(ResponseInterface $response): array
    {
        $decoded = json_decode((string)$response->getBody(), true);
        return is_array($decoded) ? $decoded : [];
    }
}