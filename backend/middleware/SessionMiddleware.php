<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

use function Forum\Helpers\json;

return function (Request $request, RequestHandler $handler) use ($makePdo) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    $publicRoutes = [
        '/api/login'             => ['POST'],
        '/api/register-new-user' => ['POST'],
        '/api/verify-email'      => ['GET', 'POST'],
        '/api/me'                => ['GET'],
        '/api/posts'             => ['GET'],
        '/api/tags/filter'       => ['GET'],
        '/api/posts/pinned'      => ['GET'],
    ];

    // Check if route is public
    $isPublic = ($method === 'OPTIONS')
        || (isset($publicRoutes[$path]) && in_array($method, $publicRoutes[$path]))
        || ($method === 'GET' && str_starts_with($path, '/api/categories'))
        || ($method === 'GET' && str_starts_with($path, '/api/get-post'))
        || ($method === 'GET' && str_starts_with($path, '/api/profile'))
        || ($method === 'GET' && str_contains($path, '/comments')) 
        || ($method === 'GET' && str_contains($path, '/replies'));

    $token = $request->getCookieParams()['session'] ?? '';
    $session = null;

    if ($token) {
        $tokenHash = hash_hmac('sha256', $token, $_ENV['HMAC_KEY']);
        $stmt = $makePdo()->prepare('SELECT User_ID, Expires FROM dbo.Forum_Sessions WHERE Token_Hash = ?');
        $stmt->execute([$tokenHash]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check if session is still valid
    $active = $session && (new DateTime() < new DateTime($session['Expires']));
    if (!$active && !$isPublic) {
        return json(new Response(), [
            'ok'    => false,
            'error' => $token ? 'Session expired' : 'Not authenticated'
        ], 401);
    }

    if ($active) {
        $request = $request->withAttribute('user_id', $session['User_ID']);
    }

    return $handler->handle($request);
};