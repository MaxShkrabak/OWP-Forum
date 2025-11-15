<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

return function (Request $request, RequestHandler $handler) use ($makePdo) {
    // Routes always public (no auth required)
    $publicExact = [
        '/api/login',
        '/api/register-new-user',
        '/api/verify-email',
    ];

    // Prefix-based public routes (for GET requests)
    // e.g. /api/categories/1/posts should be public-read
    $publicPrefixes = [
        '/api/categories',
    ];

    $path   = $request->getUri()->getPath();
    $method = strtoupper($request->getMethod());

    $isPublic = in_array($path, $publicExact, true);

    if (!$isPublic) {
        foreach ($publicPrefixes as $prefix) {
            // Prefix match, only for GET (so future POST/PUT still require auth)
            if (strpos($path, $prefix) === 0 && $method === 'GET') {
                $isPublic = true;
                break;
            }
        }
    }

    if ($method === 'OPTIONS') {
        // Preflight always allowed
        return $handler->handle($request);
    }

    // Check if the user has a session cookie
    $token = $_COOKIE['session'] ?? '';

    // If there is NO token:
    if (!$token) {
        if ($isPublic) {
            // For public routes, let guests through as unauthenticated
            return $handler->handle($request);
        }

        // For protected routes, block
        $resp = new Response();
        $resp->getBody()->write(json_encode(['ok' => false, 'error' => 'Not authenticated']));
        return $resp->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    // If we DO have a token, validate it (for both public and protected routes)
    $tokenHash = hash_hmac('sha256', $token, $_ENV['HMAC_KEY']);
    $pdo = $makePdo();
    $stmt = $pdo->prepare('SELECT User_ID, Expires FROM dbo.Sessions WHERE Token_Hash = ?');
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    // The session expired or token invalid
    if (!$session || new DateTime() > new DateTime($session['Expires'])) {
        // For protected routes, block
        if (!$isPublic) {
            $resp = new Response();
            $resp->getBody()->write(json_encode(['ok' => false, 'error' => 'Session expired']));
            return $resp->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // For public routes, just treat as guest
        return $handler->handle($request);
    }

    // Valid session: attach user_id attribute
    $request = $request->withAttribute('user_id', $session['User_ID']);

    return $handler->handle($request);
};

