<?php

declare(strict_types=1);

namespace Forum\Controllers;

use PDO;
use Throwable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\{json, setSessionCookie, clearSessionCookie};

final class AuthController extends BaseController
{
    public function me(Request $req, Response $res): Response
    {
        try {
            $userId = $req->getAttribute('user_id');

            if ($userId === null) {
                return json($res, ['ok' => true, 'user' => null], 200);
            }

            $pdo = ($this->makePdo)();

            $sql = "
                SELECT u.User_ID, u.Email, u.FirstName, u.LastName, u.Avatar,
                       r.Name as RoleName, r.RoleID,
                       ISNULL(u.IsBanned, 0) as IsBanned, u.BanType, u.BannedUntil,
                       ISNULL(u.EmailNotificationsEnabled, 1) as EmailNotificationsEnabled,
                       ISNULL(u.TermsAccepted, 0) as termsAccepted,
                       u.TermsAcceptedAt as termsAcceptedAt
                FROM dbo.Forum_Users u
                LEFT JOIN dbo.Forum_Roles r ON u.RoleID = r.RoleID
                WHERE u.User_ID = :uid
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
                'userId'                     => (int)$user['User_ID'],
                'email'                      => $user['Email'],
                'firstName'                  => $user['FirstName'],
                'lastName'                   => $user['LastName'],
                'avatar'                     => $user['Avatar'],
                'roleName'                   => $user['RoleName'],
                'roleId'                     => (int)$user['RoleID'],
                'isBanned'                   => $isBanned,
                'banType'                    => $banType,
                'bannedUntil'                => $bannedUntil,
                'emailNotificationsEnabled'  => (int)($user['EmailNotificationsEnabled'] ?? 1),
                'termsAccepted'              => (int)($user['termsAccepted'] ?? 0),
                'termsAcceptedAt'            => isset($user['termsAcceptedAt']) && $user['termsAcceptedAt'] ? $user['termsAcceptedAt'] : null,
            ]]);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $req, Response $res): Response
    {
        $data = $req->getParsedBody() ?? [];
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $otp = trim((string)($data['otp'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json($res, ['ok' => false, 'error' => 'Valid email required'], 400);
        }
        if ($otp === '') {
            return json($res, ['ok' => false, 'error' => 'Password required'], 400);
        }

        $pdo = ($this->makePdo)();

        $stmt = $pdo->prepare("
            SELECT User_ID, EmailVerified
            FROM dbo.Forum_Users
            WHERE Email = :email
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $expectedOtp = $_ENV['GLOBAL_OTP'] ?? '';

        if (!$user || !hash_equals($expectedOtp, $otp)) {
            return json($res, ['ok' => false, 'error' => 'Invalid credentials'], 401);
        }

        $isVerified = (int)($user['EmailVerified'] ?? 0);
        $updateSql = ($isVerified === 0)
            ? "UPDATE dbo.Forum_Users SET EmailVerified = 1, LastLogin=SYSDATETIME() WHERE User_ID = :uid"
            : "UPDATE dbo.Forum_Users SET LastLogin=SYSDATETIME() WHERE User_ID = :uid";

        $pdo->prepare($updateSql)->execute([':uid' => $user['User_ID']]);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash_hmac('sha256', $rawToken, $_ENV['HMAC_KEY']);

        $pdo->prepare("
            INSERT INTO dbo.Forum_Sessions (User_ID, Token_Hash, Expires)
            VALUES (:uid, :hash, DATEADD(hour, 24, SYSDATETIME()))
        ")->execute([
            ':uid' => $user['User_ID'],
            ':hash' => $tokenHash,
        ]);

        setSessionCookie($rawToken);

        return json($res, ['ok' => true]);
    }

    public function register(Request $req, Response $res): Response
    {
        try {
            $data = $req->getParsedBody() ?? [];
            $first = trim((string)($data['first'] ?? ''));
            $last = trim((string)($data['last'] ?? ''));
            $email = trim((string)($data['email'] ?? ''));

            $pdo = ($this->makePdo)();

            $check = $pdo->prepare("SELECT 1 FROM dbo.Forum_Users WHERE Email = :email");
            $check->execute([':email' => $email]);

            if ($check->fetchColumn()) {
                return json($res, ['ok' => false, 'message' => 'The provided email already exists. Try logging-in instead.'], 400);
            }

            $pdo->prepare("
                INSERT INTO dbo.Forum_Users (Email, FirstName, LastName, RoleID, Created)
                VALUES (:email, :first, :last, 1, GETDATE())
            ")->execute([
                ':email' => $email,
                ':first' => $first,
                ':last' => $last,
            ]);

            return json($res, ['ok' => true, 'message' => 'User registered successfully']);
        } catch (Throwable $e) {
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
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
            return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $req, Response $res): Response
    {
        $cookies = $req->getCookieParams();
        $rawToken = $cookies['session'] ?? '';

        if ($rawToken) {
            $tokenHash = hash_hmac('sha256', $rawToken, $_ENV['HMAC_KEY']);
            $pdo = ($this->makePdo)();
            $pdo->prepare("DELETE FROM dbo.Forum_Sessions WHERE Token_Hash = :hash")
                ->execute([':hash' => $tokenHash]);
        }

        clearSessionCookie();

        return json($res, ['ok' => true]);
    }
}
