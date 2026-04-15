<?php
declare(strict_types=1);

namespace Forum\Tests\Integration;

use Dotenv\Dotenv;
use Forum\Controllers\CommentController;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CommentPostIntegrationTest extends TestCase
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

        $commentController = new CommentController(
            $makePdo,
            fn(array $message): bool => true
        );

        $this->app->post('/api/posts/{postId}/comments', [$commentController, 'createComment']);
        $this->app->get('/api/posts/{postId}/comments', [$commentController, 'getPostComments']);
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
            $this->pdo->prepare('DELETE FROM dbo.Forum_Comments WHERE PostID = :id')
                ->execute([':id' => $postId]);

            $this->pdo->prepare('DELETE FROM dbo.Forum_Posts WHERE PostID = :id')
                ->execute([':id' => $postId]);
        }

        foreach(array_reverse(array_unique($this->userIds)) as $userId) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Users WHERE UserID = :id')
                ->execute([':id' => $userId]);
        }
    }

    public function testCreateAndRetrieveCommentInPost(): void
    {
        $userId = $this->seedUser();
        $postId = $this->seedPost($userId);
        $session = $this->seedSession($userId);
        $content = 'integration test comment -' . bin2hex(random_bytes(6));

        $createRequest = (new ServerRequestFactory())
            ->createServerRequest('POST', "/api/posts/{$postId}/comments")
            ->withCookieParams(['session' => $session])
            ->withParsedBody(['content' => $content]);

        $createResponse = $this->app->handle($createRequest);
        $json = $this->decode($createResponse);

        self::assertSame(201, $createResponse->getStatusCode());
        self::assertTrue($json['ok']);
        self::assertArrayHasKey('comment', $json);
        self::assertGreaterThan(0, $json['comment']['commentId']);
        self::assertSame($postId, $json['comment']['postId']);
        self::assertSame($content, $json['comment']['content']);

        $commentId = $json['comment']['commentId'];

        $getRequest = (new ServerRequestFactory())
            ->createServerRequest('GET', "/api/posts/{$postId}/comments")
            ->withQueryParams(['page' => 1, 'limit' => 10]);

        $getResponse = $this->app->handle($getRequest);
        $getJson = $this->decode($getResponse);

        self::assertSame(200, $getResponse->getStatusCode());
        self::assertTrue($getJson['ok']);
        self::assertSame(1, $getJson['total']);

        $foundComments = array_values(
            array_filter($getJson['items'], fn(array $comment): bool => $comment['commentId'] === $commentId)
        );

        self::assertCount(1, $foundComments);
        self::assertSame($content, $foundComments[0]['content']);
        self::assertSame($postId, $foundComments[0]['postId']);
        self::assertSame($userId, $foundComments[0]['user']['userId']);
    }

    private function seedUser(): int
    {
        $roleId = (int)$this->pdo->query("SELECT TOP 1 RoleID FROM dbo.Forum_Roles WHERE Name = 'user'")->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Users (Email, FirstName, LastName, RoleID, EmailVerified, termsAccepted) 
        OUTPUT INSERTED.UserID 
        VALUES (:email, :firstName, :lastName, :roleId, 1, 1)');
        
        $stmt->execute([
            ':email' => 'comment-id' . bin2hex(random_bytes(6)) . '@example.com',
            ':firstName' => 'John',
            ':lastName' => 'Doe',
            ':roleId' => $roleId
        ]);

        $userId = (int)$stmt->fetchColumn();
        $this->userIds[] = $userId;
        return $userId;
    }

    private function seedPost(int $userId): int
    {
        $categoryId = (int)$this->pdo->query('SELECT TOP 1 CategoryID FROM dbo.Forum_Categories WHERE UsableByRoleID <= 1 ORDER BY CategoryID')->fetchColumn();  
        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Posts (AuthorID, CategoryID, Title, Content, isCommentsDisabled) 
        OUTPUT INSERTED.PostID 
        VALUES (:authorId, :categoryId, :title, :content, 0)');
        
        $stmt->execute([
            ':authorId' => $userId,
            ':categoryId' => $categoryId,
            ':title' => 'Test Post - ' . bin2hex(random_bytes(6)),
            ':content' => 'This is a test post for comment integration testing.'
        ]);

        $postId = (int)$stmt->fetchColumn();
        $this->postIds[] = $postId;
        return $postId;
    }

    private function seedSession(int $userId): string
    {
        $sessionHash = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $sessionHash, $_ENV['HMAC_KEY']);
        $stmt = $this->pdo->prepare('INSERT INTO dbo.Forum_Sessions (UserID, TokenHash, ExpiresAt) VALUES (:userId, :tokenHash, DATEADD(hour, 24, SYSUTCDATETIME()))');
        $stmt->execute([
            ':userId' => $userId,
            ':tokenHash' => $tokenHash
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