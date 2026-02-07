<?php
namespace Forum\Helpers;

use Psr\Http\Message\ResponseInterface as Response;

function json(Response $res, array $data, int $status = 200): Response {
    $res->getBody()->write(json_encode($data));
    return $res
        ->withStatus($status)
        ->withHeader('Content-Type', 'application/json');
}

function setSessionCookie(string $rawToken): void {
    setcookie('session', $rawToken, [
        'expires'  => time() + 86400, // 24 hours
        'path'     => '/',
        'domain'   => 'localhost',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearSessionCookie(): void {
    setcookie('session', '', [
        'expires'  => time() - 3600, // One hour ago
        'path'     => '/',
        'domain'   => 'localhost',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}