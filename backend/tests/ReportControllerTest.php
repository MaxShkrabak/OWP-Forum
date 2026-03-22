<?php
namespace Forum\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Forum\Controllers\ReportController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;
use PDO;
use PDOStatement;

class ReportControllerTest extends TestCase
{
    private $pdo;
    private $stmt;
    private $controller;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createStub(PDOStatement::class);

        $this->controller = new ReportController(fn() => $this->pdo);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSubmitReportFailsWhenNotAuthenticated(): void
    {
        $req = $this->createStub(Request::class);

        $req->method('getAttribute')->willReturn(null);

        $response = $this->controller->submitReport($req, new Response());

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Not Authenticated', $body['error']);
    }

    public function testSubmitReportSuccess(): void
    {
        $req = $this->createStub(Request::class);

        $req->method('getAttribute')->willReturn(1);
        $req->method('getParsedBody')->willReturn([
            'id' => 10,
            'type' => 'post',
            'tagID' => 2
        ]);

        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(false);

        $response = $this->controller->submitReport($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertEquals('Report submitted successfully', $body['message']);
    }

    public function testSubmitReportFailsOnDuplicate(): void
    {
        $req = $this->createStub(Request::class);

        $req->method('getAttribute')->willReturn(1);
        $req->method('getParsedBody')->willReturn([
            'id' => 10,
            'type' => 'post',
            'tagID' => 2
        ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(['ReportID' => 2]);

        $response = $this->controller->submitReport($req, new Response());

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('You have already reported this post.', $body['error']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetReportsFailsWhenNotAuthenticated(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(null);

        $response = $this->controller->getReports($req, new Response());

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Not Authenticated', $body['error']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetReportsFailsWhenNotModOrAdmin(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('student');

        $this->pdo->method('prepare')->willReturn($roleStmt);

        $response = $this->controller->getReports($req, new Response());

        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Forbidden', $body['error']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetReportsSuccess(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('admin');

        $reportStmt = $this->createStub(PDOStatement::class);
        $reportStmt->method('execute')->willReturn(true);
        $reportStmt->method('fetchAll')->willReturn([
            [
                'ReportID' => 1,
                'PostID' => 10,
                'PostTitle' => 'Test Post',
                'PostAuthor' => 'John Doe',
                'CommentID' => null,
                'CommentText' => null,
                'CommentAuthor' => null,
                'CreatedAt' => '2026-03-14 12:00:00',
                'Reason' => 'Spam',
                'ReporterId' => 2,
                'ReporterName' => 'Jane Smith',
            ],
        ]);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($roleStmt, $reportStmt) {
                if (str_contains($sql, 'dbo.Roles')) return $roleStmt;
                return $reportStmt;
            }
        );

        $response = $this->controller->getReports($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertCount(1, $body['reports']);
        $this->assertEquals(1, $body['reports'][0]['reportId']);
        $this->assertEquals('Post', $body['reports'][0]['source']);
        $this->assertEquals('Spam', $body['reports'][0]['reason']);
        $this->assertEquals(2, $body['reports'][0]['reporter']['id']);
    }

    public function testGetReportTagsSuccess(): void
    {
        $req = $this->createStub(Request::class);

        $req->method('getAttribute')->willReturn(1);

        $fakeTags = [
            ['ReportTagID' => 1, 'TagName' => 'Spam'],
            ['ReportTagID' => 2, 'TagName' => 'Harassment'],
            ['ReportTagID' => 3, 'TagName' => 'Inappropriate'],
            ['ReportTagID' => 4, 'TagName' => 'Misinformation'],
            ['ReportTagID' => 5, 'TagName' => 'Other']
        ];

        $this->pdo->expects($this->once())
            ->method('query')
            ->willReturn($this->stmt);

        $this->stmt->method('fetchAll')->willReturn($fakeTags);

        $response = $this->controller->getReportTags($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertCount(5, $body['tags']);
        $this->assertEquals('Spam', $body['tags'][0]['TagName']);
        $this->assertEquals('Inappropriate', $body['tags'][2]['TagName']);
        $this->assertEquals('Other', $body['tags'][4]['TagName']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testResolveReportFailsWhenNotAuthenticated(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(null);

        $response = $this->controller->resolveReport($req, new Response(), ['id' => '1']);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testResolveReportFailsWhenNotModOrAdmin(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('student');

        $this->pdo->method('prepare')->willReturn($roleStmt);

        $response = $this->controller->resolveReport($req, new Response(), ['id' => '1']);

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testResolveReportSuccess(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('moderator');

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(1);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($roleStmt, $updateStmt) {
                if (str_contains($sql, 'dbo.Roles')) return $roleStmt;
                return $updateStmt;
            }
        );

        $response = $this->controller->resolveReport($req, new Response(), ['id' => '5']);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testResolveReportNotFoundOrAlreadyResolved(): void
    {
        $req = $this->createStub(Request::class);
        $req->method('getAttribute')->willReturn(1);

        $roleStmt = $this->createStub(PDOStatement::class);
        $roleStmt->method('execute')->willReturn(true);
        $roleStmt->method('fetchColumn')->willReturn('admin');

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(0);

        $this->pdo->method('prepare')->willReturnCallback(
            function (string $sql) use ($roleStmt, $updateStmt) {
                if (str_contains($sql, 'dbo.Roles')) return $roleStmt;
                return $updateStmt;
            }
        );

        $response = $this->controller->resolveReport($req, new Response(), ['id' => '999']);

        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Report not found or already resolved', $body['error']);
    }
}