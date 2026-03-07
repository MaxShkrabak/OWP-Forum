<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Forum\Controllers\TermsController;

use function Forum\Helpers\{json, setSessionCookie, clearSessionCookie};

$app->get('/api/me', function(Request $req, Response $res) use ($makePdo) {
    try {
        // Verify auth
        $userId = $req->getAttribute('user_id');

        if ($userId === null) {
            return json($res, ['ok' => true, 'user' => null], 200);
        }

        $pdo = $makePdo();

        // Single query when ban + terms columns exist; fallback for older DBs
        $user = null;
        try {
            $sql = "
                SELECT u.User_ID, u.Email, u.FirstName, u.LastName, u.Avatar, r.Name as RoleName, r.RoleID,
                       ISNULL(u.IsBanned, 0) as IsBanned, u.BanType, u.BannedUntil,
                       ISNULL(u.EmailNotificationsEnabled, 1) as EmailNotificationsEnabled,
                       ISNULL(u.TermsAccepted, 0) as termsAccepted, u.TermsAcceptedAt as termsAcceptedAt
                FROM dbo.Users u
                LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                WHERE u.User_ID = :uid
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
        }
        if (!$user) {
            try {
                $sql = "
                    SELECT u.User_ID, u.Email, u.FirstName, u.LastName, u.Avatar, r.Name as RoleName, r.RoleID,
                           ISNULL(u.IsBanned, 0) as IsBanned, u.BanType, u.BannedUntil
                    FROM dbo.Users u
                    LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                    WHERE u.User_ID = :uid
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':uid' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                // Ban columns may not exist
            }
        }

        if (!$user) {
            $sql = "
                SELECT u.User_ID, u.Email, u.FirstName, u.LastName, u.Avatar, r.Name as RoleName, r.RoleID,
                    ISNULL(u.EmailNotificationsEnabled, 1) as EmailNotificationsEnabled
                FROM dbo.Users u
                LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
                WHERE u.User_ID = :uid
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user['IsBanned'] = 0;
                $user['BanType'] = null;
                $user['BannedUntil'] = null;
                $user['termsAccepted'] = 0;
                $user['termsAcceptedAt'] = null;
            }
        }
        if ($user && !array_key_exists('termsAccepted', $user)) {
            $user['termsAccepted'] = 0;
            $user['termsAcceptedAt'] = null;
        }

        if (!$user) {
            return json($res, ['ok' => false, 'error' => 'User not found'], 404);
        }

        $user['IsBanned'] = (int)($user['IsBanned'] ?? 0);
        $user['BanType'] = isset($user['BanType']) && $user['BanType'] ? trim((string)$user['BanType']) : null;
        $user['BannedUntil'] = isset($user['BannedUntil']) && $user['BannedUntil'] ? $user['BannedUntil'] : null;
        $user['termsAccepted'] = (int)($user['termsAccepted'] ?? 0);
        $user['termsAcceptedAt'] = isset($user['termsAcceptedAt']) && $user['termsAcceptedAt'] ? $user['termsAcceptedAt'] : null;

        // Effective ban: treat expired temporary ban as not banned (no DB write)
        if ($user['IsBanned'] && $user['BanType'] === 'temporary' && $user['BannedUntil']) {
            $until = $user['BannedUntil'] instanceof \DateTimeInterface
                ? $user['BannedUntil'] : new \DateTimeImmutable($user['BannedUntil'], new \DateTimeZone('UTC'));
            if ($until <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                $user['IsBanned'] = 0;
                $user['BanType'] = null;
                $user['BannedUntil'] = null;
            }
        }

        return json($res, ['ok' => true, 'user' => $user]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->post('/api/login', function(Request $req, Response $res) use ($makePdo) {
    $data = $req->getParsedBody() ?? [];
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $otp = trim((string)($data['otp'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return json($res, ['ok' => false, 'error' => 'Valid email required'], 400);
    }
    if ($otp === '') {
        return json($res, ['ok' => false, 'error' => 'Password required'], 400);
    }

    $pdo = $makePdo();

    // Fetch user by email
    $sql = "
        SELECT User_ID, EmailVerified 
        FROM dbo.Users 
        WHERE Email = :email
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $expectedOtp = $_ENV['GLOBAL_OTP'] ?? '';

    // Wrong login details
    if(!$user || !hash_equals($expectedOtp, $otp)) {
        return json($res, ['ok' => false, 'error' => 'Invalid credentials'], 401);
    }

    $isVerified = (int)($user['EmailVerified'] ?? 0);
    $updateSql = ($isVerified === 0) 
        ? "UPDATE dbo.Users SET EmailVerified = 1, LastLogin=SYSDATETIME() WHERE User_ID = :uid"
        : "UPDATE dbo.Users SET LastLogin=SYSDATETIME() WHERE User_ID = :uid";
    
    $pdo->prepare($updateSql)->execute([':uid' => $user['User_ID']]);
    
    // Generate session
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash_hmac('sha256', $rawToken, $_ENV['HMAC_KEY']);

    $sessionSql = "
        INSERT INTO dbo.Sessions (User_ID, Token_Hash, Expires)
        VALUES (:uid, :hash, DATEADD(hour, 24, SYSDATETIME()))
    ";

    // Store session details
    $pdo->prepare($sessionSql)-> execute([
            ':uid' => $user['User_ID'],
            ':hash' => $tokenHash
    ]);

    // Cookie on the users browser
    setSessionCookie($rawToken);

    return json($res, ['ok' => true]);
});

$app->post('/api/register-new-user', function (Request $req, Response $res) use ($makePdo) {
    try {
        $data = $req->getParsedBody() ?? [];
        $first = trim((string)($data['first'] ?? ''));
        $last = trim((string)($data['last'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));

        $pdo = $makePdo();
        
        $check = $pdo->prepare("SELECT 1 FROM dbo.Users WHERE Email = :email");  
        $check->execute([':email' => $email]);

        if ($check->fetchColumn()) {
            return json($res, ['ok' => false, 'message' => 'The provided email already exists. Try logging-in instead.'], 400);
        }
        
        $insertUser = "
            INSERT INTO dbo.USERS (Email, FirstName, LastName, RoleID, Created)
            VALUES (:email, :first, :last, 1, GETDATE())
        ";

        $pdo->prepare($insertUser) ->execute([                
            ":email" => $email,
            ":first" => $first,
            ":last" => $last,
        ]);

        return json($res, ['ok' => true, 'message' => 'User registered successfully']);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->post('/api/verify-email', function (Request $req, Response $res) use ($makePdo) {
    try {
        $data = $req->getParsedBody() ?? [];
        $email = trim((string)($data['email'] ?? ''));

        if ($email === '') {
            return json($res, ['ok' => false, 'error' => 'Email required'], 400);
        }

        $pdo = $makePdo();

        $checkEmailSql = "
            SELECT 1 
            FROM dbo.Users 
            WHERE Email = :email
        ";

        $check = $pdo->prepare($checkEmailSql);  
        $check->execute([':email' => $email]);
                         
        $emailExists = (bool)$check->fetchColumn();

        return json($res, ['ok' => true, 'emailExists' => $emailExists]);

    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->post('/api/accept-terms', function(Request $req, Response $res) use ($makePdo) {
    $pdo = $makePdo();
    $controller = new TermsController();
    return $controller->accept($req, $res, $pdo);
});

$app->post('/api/logout', function (Request $req, Response $res) use ($makePdo) {

    $cookies = $req->getCookieParams();
    $rawToken = $cookies['session'] ?? '';

    if ($rawToken) {
        $tokenHash = hash_hmac('sha256', $rawToken, $_ENV['HMAC_KEY']);

        $pdo = $makePdo();

        $deleteSessionSql = "DELETE FROM dbo.Sessions WHERE Token_Hash = :hash";
        $pdo->prepare($deleteSessionSql)->execute([':hash' => $tokenHash]);
    }

    // Expire the cookie in the browser
    clearSessionCookie();

    return json($res, ['ok' => true]);
});

// Update user avatar
$app->post('/api/user/avatar', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute('user_id');

        $data = $req->getParsedBody();
        $newAvatarPath = trim((string)($data['avatar'] ?? ''));

        if ($newAvatarPath === '') {
            return json($res, ['ok' => false, 'error' => 'No avatar provided'], 400);
        }

        $avatarFilename = basename($newAvatarPath);

        $pdo = $makePdo();

        $avatarSql = "
            UPDATE dbo.Users 
            SET Avatar = :avatar 
            WHERE User_ID = :uid
        ";

        $pdo->prepare($avatarSql)->execute([
            ':avatar' => $avatarFilename,
            ':uid'    => $userId
        ]);

        return json($res, [
            'ok' => true, 
            'message' => 'Avatar updated successfully',
            'newAvatar' => $avatarFilename
        ]);

    } catch (Throwable $e) {
        // Failed to save icon
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->get('/api/user/notification-settings', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute('user_id');

        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $pdo = $makePdo();

        $sql = "
            SELECT ISNULL(EmailNotificationsEnabled, 1) as EmailNotificationsEnabled
            FROM dbo.Users
            WHERE User_ID = :uid
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return json($res, ['ok' => false, 'error' => 'User not found'], 404);
        }

        return json($res, [
            'ok' => true,
            'settings' => [
                'emailNotifications' => (bool)$result['EmailNotificationsEnabled']
            ]
        ]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

$app->post('/api/user/notification-settings', function (Request $req, Response $res) use ($makePdo) {
    try {
        $userId = $req->getAttribute('user_id');

        if (!$userId) {
            return json($res, ['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $data = $req->getParsedBody() ?? [];
        
        if (!array_key_exists('emailNotifications', $data)) {
            return json($res, ['ok' => false, 'error' => 'Invalid emailNotifications value'], 400);
        }

        $emailNotifications = filter_var($data['emailNotifications'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($emailNotifications === null) {
            return json($res, ['ok' => false, 'error' => 'Invalid emailNotifications value'], 400);
        }

        $pdo = $makePdo();

        $updateSql = "
            UPDATE dbo.Users
            SET EmailNotificationsEnabled = :enabled
            WHERE User_ID = :uid
        ";

        $pdo->prepare($updateSql)->execute([
            ':enabled' => $emailNotifications ? 1 : 0,
            ':uid' => $userId
        ]);

        return json($res, [
            'ok' => true, 
            'settings' => [
                'emailNotifications' => $emailNotifications
            ]
        ]);
    } catch (Throwable $e) {
        return json($res, ['ok' => false, 'error' => $e->getMessage()], 500);
    }
});