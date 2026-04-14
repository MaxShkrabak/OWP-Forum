<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Forum\Controllers\UserController;

final class TermsControllerTest extends TestCase
{
    public function test_acceptByUserId_executes_update_with_uid_param(): void
    {
        $executedParams = null;

        $stmt = new class($executedParams) {
            private mixed $executedParamsRef;

            public function __construct(& $executedParamsRef)
            {
                $this->executedParamsRef = & $executedParamsRef;
            }

            public function execute(array $params = []): bool
            {
                $this->executedParamsRef = $params;
                return true;
            }
        };

        $pdo = new class($stmt) extends PDO {
            private $stmt;

            public function __construct($stmt)
            {
                $this->stmt = $stmt;
            }

            #[\ReturnTypeWillChange]
            public function prepare($query, $options = null)
            {
                TestCase::assertStringContainsString('UPDATE dbo.Forum_Users', (string)$query);
                TestCase::assertStringContainsString('TermsAccepted = 1', (string)$query);
                TestCase::assertStringContainsString('WHERE UserID = :uid', (string)$query);

                return $this->stmt;
            }
        };

        $dummyMakePdo = fn() => $pdo;
        $controller = new UserController($dummyMakePdo);
        $controller->acceptTermsByUserId($pdo, 123);

        $this->assertSame([':uid' => 123], $executedParams);
    }
}
