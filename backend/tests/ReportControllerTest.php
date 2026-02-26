<?php
use PHPUnit\Framework\TestCase;
use Forum\Controllers\ReportController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class ReportControllerTest extends TestCase {
    private $pdo;
    private $stmt;
    private $controller;

    protected function setUp(): void {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);
        
        $this->controller = new ReportController(fn() => $this->pdo);
    }

    public function testSubmitReportFailsWhenNotAuthenticated() {
        $req = $this->createMock(Request::class);
        $req->method('getAttribute')->with('user_id')->willReturn(null);

        $response = $this->controller->submitReport($req, new Response());

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertEquals('Not Authenticated', $body['error']);
    }

    public function testSubmitReportSuccess() {
        $req = $this->createMock(Request::class);
        $req->method('getAttribute')->with('user_id')->willReturn(1);
        $req->method('getParsedBody')->willReturn([
            'id' => 10,
            'type' => 'post',
            'tagID' => 2
        ]);

        $this->pdo->expects($this->exactly(2))
                  ->method('prepare')
                  ->willReturn($this->stmt);

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(false); // not a duplicate report

        $response = $this->controller->submitReport($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertEquals('Report submitted successfully', $body['message']);
    }

    public function testSubmitReportFailsOnDuplicate() {
        $req = $this->createMock(Request::class);
        $req->method('getAttribute')->with('user_id')->willReturn(1);
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

    public function testGetReportTagsSuccess() {
        $req = $this->createMock(Request::class);
        
        $req->method('getAttribute')->with('user_id')->willReturn(1);

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

        $this->stmt->method('fetchAll')
                   ->with(PDO::FETCH_ASSOC)
                   ->willReturn($fakeTags);

        $response = $this->controller->getReportTags($req, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertCount(5, $body['tags']);
        $this->assertEquals('Spam', $body['tags'][0]['TagName']);
        $this->assertEquals('Inappropriate', $body['tags'][2]['TagName']);
        $this->assertEquals('Other', $body['tags'][4]['TagName']);
    }
}