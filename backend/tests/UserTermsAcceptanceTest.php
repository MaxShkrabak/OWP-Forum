<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

use PHPUnit\Framework\TestCase;

final class UserTermsAcceptanceTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = "http://localhost:8080";
        if (empty($_ENV["GLOBAL_OTP"]) && !getenv("GLOBAL_OTP")) {
            $_ENV["GLOBAL_OTP"] = "123456";
            putenv("GLOBAL_OTP=123456");
        }
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        array &$cookieJar = [],
        array $extraHeaders = []
    ): array {
        $ch = curl_init($this->baseUrl . $path);

        $headers = array_merge([
            "Accept: application/json",
        ], $extraHeaders);

        if ($body !== null) {
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
        }

        if (!empty($cookieJar)) {
            $cookieStr = "";
            foreach ($cookieJar as $k => $v) {
                $cookieStr .= $k . "=" . $v . "; ";
            }
            $headers[] = "Cookie: " . rtrim($cookieStr, "; ");
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        }

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            $this->fail("Curl error: " . $err);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($resp, 0, $headerSize);
        $rawBody = substr($resp, $headerSize);

        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (stripos($line, "Set-Cookie:") === 0) {
                $cookiePart = trim(substr($line, strlen("Set-Cookie:")));
                $cookieKV = explode(";", $cookiePart, 2)[0];
                $parts = explode("=", $cookieKV, 2);
                if (count($parts) === 2) {
                    $cookieJar[$parts[0]] = $parts[1];
                }
            }
        }

        $json = json_decode($rawBody, true);

        return [
            "status" => $status,
            "headers_raw" => $rawHeaders,
            "body_raw" => $rawBody,
            "json" => $json,
        ];
    }

    private function login(array &$cookieJar): void
{
    $email = $_ENV["TEST_EMAIL"] ?? getenv("TEST_EMAIL") ?: "phpunit_terms_" . uniqid() . "@example.com";
    $otp   = $_ENV["GLOBAL_OTP"] ?? getenv("GLOBAL_OTP") ?: "";

    $this->assertNotSame("", $otp, "GLOBAL_OTP is empty. Set it in your backend .env for tests.");

    // Ensure user exists
    $reg = $this->request("POST", "/api/register-new-user", [
        "first" => "PHPUnit",
        "last"  => "Terms",
        "email" => $email,
    ], $cookieJar);

    // register endpoint
    $this->assertTrue(
        in_array($reg["status"], [200, 400], true),
        "Register returned unexpected status {$reg["status"]}: {$reg["body_raw"]}"
    );

    // Login
    $res = $this->request("POST", "/api/login", [
        "email" => $email,
        "otp"   => $otp,
    ], $cookieJar);

    $this->assertSame(200, $res["status"], "Login failed: {$res["body_raw"]}");
    $this->assertIsArray($res["json"], "Login did not return JSON: {$res["body_raw"]}");
    $this->assertTrue($res["json"]["ok"] ?? false, "Login ok=false: {$res["body_raw"]}");
    $this->assertArrayHasKey("session", $cookieJar, "No session cookie was set by /api/login.");
}

    public function test_terms_acceptance_flow_updates_me(): void
    {
        $cookieJar = [];

        // Login to get session cookie
        $this->login($cookieJar);

        // 2) /api/me should show termsAccepted (0 or 1). For a new/unaccepted user it should be 0
        $me1 = $this->request("GET", "/api/me", null, $cookieJar);

        $this->assertSame(200, $me1["status"], "GET /api/me failed: {$me1["body_raw"]}");
        $this->assertTrue($me1["json"]["ok"] ?? false, "me ok=false: {$me1["body_raw"]}");
        $this->assertNotNull($me1["json"]["user"] ?? null, "Expected user object from /api/me.");

        $terms1 = (int)($me1["json"]["user"]["termsAccepted"] ?? 0);

        // We allow either 0 or 1 depending on whether that test account already accepted before
        $this->assertTrue(in_array($terms1, [0, 1], true), "termsAccepted not 0/1: " . $me1["body_raw"]);

        // Accept terms
        $acc = $this->request("POST", "/api/accept-terms", null, $cookieJar);

        $this->assertSame(200, $acc["status"], "POST /api/accept-terms failed: {$acc["body_raw"]}");
        $this->assertTrue($acc["json"]["ok"] ?? false, "accept-terms ok=false: {$acc["body_raw"]}");

        // 4) /api/me should now be termsAccepted = 1
        $me2 = $this->request("GET", "/api/me", null, $cookieJar);

        $this->assertSame(200, $me2["status"], "GET /api/me (after accept) failed: {$me2["body_raw"]}");
        $this->assertTrue($me2["json"]["ok"] ?? false, "me ok=false (after accept): {$me2["body_raw"]}");

        $terms2 = (int)($me2["json"]["user"]["termsAccepted"] ?? 0);
        $this->assertSame(1, $terms2, "Expected termsAccepted=1 after /api/accept-terms. Body: {$me2["body_raw"]}");

        $this->assertNotEmpty($me2["json"]["user"]["termsAcceptedAt"] ?? null, "Expected termsAcceptedAt to be set after acceptance.");
    }
}