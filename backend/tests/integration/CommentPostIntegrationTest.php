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
            $this->pdo->prepare('DELETE FROM dbo.Forum_Sessions WHERE Token_Hash = :hash')
                ->execute([':hash' => $sessionHash]);
        }

        foreach (array_reverse(array_unique($this->postIds)) as $postId) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Comments WHERE PostId = :id')
                ->execute([':id' => $postId]);

            $this->pdo->prepare('DELETE FROM dbo.Forum_Posts WHERE PostID = :id')
                ->execute([':id' => $postId]);
        }

        foreach(array_reverse(array_unique($this->userIds)) as $userId) {
            $this->pdo->prepare('DELETE FROM dbo.Forum_Users WHERE User_ID = :id')
                ->execute([':id' => $userId]);
        }
    }
}