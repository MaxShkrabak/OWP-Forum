<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

// This allows requests from the frontend port to the backend port (if the ports are different)
return function (Request $request, $handler) {
    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $resp = new \Slim\Psr7\Response(200);
    } else {
        $resp = $handler->handle($request);
    }

    return $resp
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')  // frontend port
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, PATCH, DELETE')
        ->withHeader('Access-Control-Allow-Credentials', 'true')  // allow cookies
        ->withHeader("Vary", "Origin");
};