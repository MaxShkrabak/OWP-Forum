<?php

declare(strict_types=1);

namespace Forum\Controllers;

use PDO;
use Throwable;
use Closure;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\{json, setSessionCookie, clearSessionCookie};

final class AuthController extends BaseController
{

    private Closure $sendOtpEmail;

    public function __construct(Closure $makePdo, ?Closure $sendOtpEmail = null)
    {
        parent::__construct($makePdo);
        $this->sendOtpEmail = $sendOtpEmail ?? fn(array $message) => $this->dispatchOtpEmail($message);
    }

    private function buildOtpRequestMessage(string $email, string $name, string $otp): array
    {
        $fromEmail = $_ENV['EMAIL_FROM_ADDRESS'] ?? '';
        $fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'OWP Forum';

        $safeName = htmlspecialchars($name !== '' ? $name : $email, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        return [
            'sender' => [
                'email' => $fromEmail,
                'name' => $fromName
            ],
            'to' => [[
                'email' => $email,
                'name' => $name !== '' ? $name : $email
            ]],
            'subject' => "Your OTP for OWP Forum",
            'htmlContent' => "<p>Hi {$safeName},</p><p>Your One-Time Password (OTP) is: <h3><strong>{$safeOtp}</strong></h3></p><p>Please use this code to complete your authentication.</p><p>Best,<br/>OWP Forum Team</p>"
        ];
    }

    private function dispatchOtpEmail(array $payload): bool
    {
        $apiKey = $_ENV['EMAIL_API_KEY'] ?? '';
        $fromEmail = $_ENV['EMAIL_FROM_ADDRESS'] ?? '';
        $useSandbox = filter_var($_ENV['EMAIL_SANDBOX'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        if ($apiKey === '' || $fromEmail === '') {
            error_log("Email API key or from address not configured. Cannot send notification.");
            return false;
        }

        $headers = [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json'
        ];

        if ($useSandbox) {
            $headers[] = 'X-Sib-Sandbox: drop';
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            error_log("cURL error while sending email: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            error_log("Failed to send email notification. Status: {$status}, Response: {$response}");
            return false;
        }

        return true;
    }

    public function me(Request $req, Response $res): Response
    {
        try {
            $userId = $req->getAttribute('user_id');

            if ($userId === null) {
                return json($res, ['ok' => true, 'user' => null], 200);
            }

            $pdo = ($this->makePdo)();

            $sql = "
                SELECT u.UserID, u.Email, u.FirstName, u.LastName, u.Avatar,
                       r.Name as RoleName, r.RoleID,
                       ISNULL(u.IsBanned, 0) as IsBanned, u.BanType, u.BannedUntil,
                       ISNULL(u.TermsAccepted, 0) as termsAccepted
                FROM dbo.Forum_Users u
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                WHERE u.UserID = :uid
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return json($res, ['ok' => false, 'error' => 'User not found'], 404);
            }

            $isBanned = (int)($user['IsBanned'] ?? 0);
            $banType = isset($user['BanType']) && $user['BanType'] ? trim((string)$user['BanType']) : null;
            $bannedUntil = isset($user['BannedUntil']) && $user['BannedUntil'] ? $user['BannedUntil'] : null;

            if ($isBanned && $banType === 'temporary' && $bannedUntil) {
                $until = $bannedUntil instanceof \DateTimeInterface
                    ? $bannedUntil : new \DateTimeImmutable($bannedUntil, new \DateTimeZone('UTC'));
                if ($until <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                    $isBanned = 0;
                    $banType = null;
                    $bannedUntil = null;
                }
            }

            return json($res, ['ok' => true, 'user' => [
                'userId'                     => (int)$user['UserID'],
                'email'                      => $user['Email'],
                'firstName'                  => $user['FirstName'],
                'lastName'                   => $user['LastName'],
                'avatar'                     => $user['Avatar'],
                'roleName'                   => $user['RoleName'],
                'roleId'                     => (int)$user['RoleID'],
                'isBanned'                   => $isBanned,
                'banType'                    => $banType,
                'bannedUntil'                => $bannedUntil,
                'termsAccepted'              => (int)($user['termsAccepted'] ?? 0),
            ]]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }

    // TODO: Add rate limiting to login and requestOtp endpoints before production.
    // Currently no limit on OTP requests or login attempts per IP/email.
    public function login(Request $req, Response $res): Response
    {
        $data = $req->getParsedBody() ?? [];
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $otp = trim((string)($data['otp'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json($res, ['ok' => false, 'error' => 'Valid email required'], 400);
        }
        if ($otp === '') {
            return json($res, ['ok' => false, 'error' => 'One-Time Passcode required'], 400);
        }

        $pdo = ($this->makePdo)();

        $stmt = $pdo->prepare("
            SELECT UserID, EmailVerified
            FROM dbo.Forum_Users
            WHERE Email = :email
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $otp = hash_hmac('sha256', $otp, $_ENV['HMAC_KEY']);

        if (isset($_ENV['GLOBAL_OTP']) && $_ENV['GLOBAL_OTP'] !== '' && strlen($_ENV['GLOBAL_OTP']) === 6) {
            $expectedHash = hash_hmac('sha256', $_ENV['GLOBAL_OTP'], $_ENV['HMAC_KEY']);
            if (!hash_equals($expectedHash, $otp)) {
                return json($res, ['ok' => false, 'error' => 'Invalid credentials']);
            }
        } else {
            $expectedOtp = $pdo->prepare("SELECT TOP 1 CodeHash FROM dbo.Forum_OTP_Codes WHERE Email = :email AND isUsed = 0 AND ExpiresAt > SysUTCDATETIME() ORDER BY CodeHash DESC");
            $expectedOtp->execute([':email' => $email]);
            $otpMatch = $expectedOtp->fetch(PDO::FETCH_ASSOC);

            if (!$otpMatch) {
                return json($res, ['ok' => false, 'error' => 'No valid OTP found. Please request a new one.'], 401);
            }

            if (!$user || !hash_equals($otpMatch['CodeHash'], $otp)) {
                return json($res, ['ok' => false, 'error' => 'Invalid credentials']);
            }

            $pdo->prepare("UPDATE dbo.Forum_OTP_Codes SET isUsed = 1 WHERE Email = :email")
                ->execute([':email' => $email]);
        }

        $isVerified = (int)($user['EmailVerified'] ?? 0);
        $updateSql = ($isVerified === 0)
            ? "UPDATE dbo.Forum_Users SET EmailVerified = 1, LastLogin = SYSUTCDATETIME() WHERE UserID = :uid"
            : "UPDATE dbo.Forum_Users SET LastLogin = SYSUTCDATETIME() WHERE UserID = :uid";

        $pdo->prepare($updateSql)->execute([':uid' => $user['UserID']]);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, $_ENV['HMAC_KEY']);

        $pdo->prepare("
            INSERT INTO dbo.Forum_Sessions (UserID, TokenHash, ExpiresAt)
            VALUES (:uid, :hash, DATEADD(hour, 24, SYSUTCDATETIME()))
        ")->execute([
            ':uid' => $user['UserID'],
            ':hash' => $tokenHash,
        ]);

        setSessionCookie($rawToken);

        return json($res, ['ok' => true]);
    }

    public function requestOtp(Request $req, Response $res): Response
    {
        if (isset($_ENV['GLOBAL_OTP']) && $_ENV['GLOBAL_OTP'] !== '' && strlen($_ENV['GLOBAL_OTP']) === 6) {
            return json($res, ['ok' => true, 'message' => 'GlobalOTP code used, OTP request bypassed']);
        }

        try {
            $data = $req->getParsedBody() ?? [];
            $email = strtolower(trim((string)($data['email'] ?? '')));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return json($res, ['ok' => false, 'error' => 'Valid email required'], 400);
            }

            $pdo = ($this->makePdo)();

            $stmt = $pdo->prepare("SELECT FirstName, LastName FROM dbo.Forum_Users WHERE Email = :email");
            $stmt->execute([':email' => $email]);

            if (!$stmt->fetchColumn()) {
                return json($res, ['ok' => false, 'error' => 'Email not found. Please register first.'], 404);
            }

            $nameStmt = $stmt->fetch(PDO::FETCH_ASSOC);
            $otpCode = (string)random_int(100000, 999999);
            $otpHash = hash_hmac('sha256', $otpCode, $_ENV['HMAC_KEY']);

            try {
                $fullName = trim(($nameStmt['FirstName'] ?? '') . ' ' . ($nameStmt['LastName'] ?? ''));
                $message = $this->buildOtpRequestMessage($email, $fullName, $otpCode);
                ($this->sendOtpEmail)($message);
            } catch (Throwable $e) {
                error_log('Failed to send OTP email: ' . $e->getMessage());
                return json($res, ['ok' => false, 'error' => 'Failed to send verification email. Please try again.'], 500);
            }

            $pdo->prepare("
                INSERT INTO dbo.Forum_OTP_Codes (Email, CodeHash, ExpiresAt, isUsed, CreatedAt)
                VALUES (:email, :codeHash, DATEADD(minute, 15, SYSUTCDATETIME()), 0, SYSUTCDATETIME())
            ")->execute([
                ':email' => $email,
                ':codeHash' => $otpHash,
            ]);

            return json($res, ['ok' => true, 'message' => 'OTP code sent successfully']);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }

    public function register(Request $req, Response $res): Response
    {
        try {
            $data = $req->getParsedBody() ?? [];
            $first = trim((string)($data['first'] ?? ''));
            $last = trim((string)($data['last'] ?? ''));
            $email = strtolower(trim((string)($data['email'] ?? '')));

            if ($first === '' || $last === '') {
                return json($res, ['ok' => false, 'error' => 'First and last name are required.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return json($res, ['ok' => false, 'error' => 'Valid email required.'], 400);
            }

            $pdo = ($this->makePdo)();

            $check = $pdo->prepare("SELECT 1 FROM dbo.Forum_Users WHERE Email = :email");
            $check->execute([':email' => $email]);

            if ($check->fetchColumn()) {
                return json($res, ['ok' => false, 'message' => 'The provided email already exists. Try logging-in instead.'], 400);
            }

            $pdo->prepare("
                INSERT INTO dbo.Forum_Users (Email, FirstName, LastName, RoleID)
                VALUES (:email, :first, :last, 1)
            ")->execute([
                ':email' => $email,
                ':first' => $first,
                ':last' => $last,
            ]);

            return json($res, ['ok' => true, 'message' => 'User registered successfully']);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }

    public function verifyEmail(Request $req, Response $res): Response
    {
        try {
            $data = $req->getParsedBody() ?? [];
            $email = trim((string)($data['email'] ?? ''));

            if ($email === '') {
                return json($res, ['ok' => false, 'error' => 'Email required'], 400);
            }

            $pdo = ($this->makePdo)();

            $check = $pdo->prepare("SELECT 1 FROM dbo.Forum_Users WHERE Email = :email");
            $check->execute([':email' => $email]);

            $emailExists = (bool)$check->fetchColumn();

            return json($res, ['ok' => true, 'emailExists' => $emailExists]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return json($res, ['ok' => false, 'error' => 'Internal server error.'], 500);
        }
    }

    public function logout(Request $req, Response $res): Response
    {
        $cookies = $req->getCookieParams();
        $rawToken = $cookies['session'] ?? '';

        if ($rawToken) {
            $tokenHash = hash_hmac('sha256', $rawToken, $_ENV['HMAC_KEY']);
            $pdo = ($this->makePdo)();
            $pdo->prepare("DELETE FROM dbo.Forum_Sessions WHERE TokenHash = :hash")
                ->execute([':hash' => $tokenHash]);
        }

        clearSessionCookie();

        return json($res, ['ok' => true]);
    }
}
