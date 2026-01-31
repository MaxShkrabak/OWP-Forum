<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use function Forum\Helpers\json;

$app->post('/api/upload-image', function (Request $request, Response $response) {
    $userId = $request->getAttribute('user_id');
    if (!$userId) {
        return json($response, ['ok' => false, 'error' => 'Not Authenticated'], 401);
    }

    $uploadedFiles = $request->getUploadedFiles();

    if (empty($uploadedFiles['image'])) {
        return json($response, ['ok' => false, 'error' => 'No image uploaded'], 400);
    }

    $image = $uploadedFiles['image'];

    if ($image->getError() !== UPLOAD_ERR_OK) {
        return json($response, ['ok' => false, 'error' => 'Upload failed with error code ' . $image->getError()], 400);
    }

    $mimeType = $image->getClientMediaType();
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    if (!in_array($mimeType, $allowedTypes, true)) {
        return json($response, ['ok' => false, 'error' => 'Invalid image type'], 400);
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

    return json($response, ['ok' => true, 'url' => $relativePath]);
});