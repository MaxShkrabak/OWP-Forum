<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function Forum\Helpers\{json, setSessionCookie, clearSessionCookie};

$app->get('/api/me', function(Request $req, Response $res) use ($makePdo) {
    try {
        // Verify auth
        $userId = $req->getAttribute('user_id');

        if ($userId === null) {
            return json($res, ['ok' => false, 'error' => 'Not Authenticated'], 401);
        }

        $pdo = $makePdo();

        // Base user fields (no IsBanned so login works before migration 008 is run)
        $sql = "
            SELECT u.User_ID, u.Email, u.FirstName, u.LastName, u.Avatar, r.Name as RoleName, r.RoleID
            FROM dbo.Users u
            LEFT JOIN dbo.Roles r ON u.RoleID = r.RoleID
            WHERE u.User_ID = :uid
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return json($res, ['ok' => false, 'error' => 'User not found'], 404);
        }

        // Add IsBanned if column exists (migration 008_user_ban.sql)
        $user['IsBanned'] = 0;
        try {
            $banStmt = $pdo->prepare("SELECT ISNULL(IsBanned, 0) FROM dbo.Users WHERE User_ID = :uid");
            $banStmt->execute([':uid' => $userId]);
            $user['IsBanned'] = (int) $banStmt->fetchColumn();
        } catch (Throwable $e) {
            // Column may not exist yet
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

        $stmt = $pdo->prepare($insertUser) ->execute([                
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