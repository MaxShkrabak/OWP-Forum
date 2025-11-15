<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

return function (Request $request, RequestHandler $handler) use ($makePdo) {
    $public = ['/api/login', '/api/register-new-user', '/api/verify-email']; // routes accessible to unregistered users

    // Get that path of the URL for the incoming request
    $path = $request->getUri()->getPath();
    if (in_array($path, $public) || strtoupper($request->getMethod()) === 'OPTIONS') {
        return $handler->handle($request);
    }

    if (strpos($path, '/api/categories') === 0 && $request->getMethod() === 'GET') {
    return $handler->handle($request);
    }

    // Check if the user has a session cookie
    $token = $_COOKIE['session'] ?? '';
    // Block access if there is no session token
    if (!$token) {
        $resp = new Response();
        $resp->getBody()->write(json_encode(['ok' => false, 'error' => 'Not authenticated']));
        return $resp->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    // Check that tokenHash matches the one stored in the database
    $tokenHash = hash_hmac('sha256', $token, $_ENV['HMAC_KEY']);
    $pdo = $makePdo();
    $stmt = $pdo->prepare('SELECT User_ID, Expires FROM dbo.Sessions WHERE Token_Hash = ?');
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    // The session expired
    if (!$session || new DateTime() > new DateTime($session['Expires'])) {
        $resp = new Response();
        $resp->getBody()->write(json_encode(['ok' => false, 'error' => 'Session expired']));
        return $resp->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $request = $request->withAttribute('user_id', $session['User_ID']);
    return $handler->handle($request);
};