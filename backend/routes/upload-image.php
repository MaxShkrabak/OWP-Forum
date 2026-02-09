<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app->post('/api/upload-image', function (Request $request, Response $response) {
    $userId = $request->getAttribute('user_id');
    if (!$userId) {
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'User ID is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $uploadedFiles = $request->getUploadedFiles();

    if (empty($uploadedFiles['image'])) {
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'No image file uploaded'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $image = $uploadedFiles['image'];

    if ($image->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'Error uploading file'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $mimeType = $image->getClientMediaType();
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mimeType, $allowedTypes, true)) {
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'Invalid image type. Only JPG, PNG, and GIF are allowed.'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $uploadDir = __DIR__ . '/../public/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION) ?: 'bin';
    $filename = uniqid('img_', true) . '.' . $extension;
    $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    $image->moveTo($filePath);

    $relativePath = '/uploads/' . $filename;

    $response->getBody()->write(json_encode([
        'ok' => true,
        'url' => $relativePath
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});