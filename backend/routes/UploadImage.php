<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use function Forum\Helpers\json;

require_once __DIR__ . '/../src/MediaValidator.php';

$app->post('/api/upload-image', function (Request $request, Response $response) {
    $userId = $request->getAttribute('user_id');
    if (!$userId) return json($response, ['ok' => false, 'error' => 'Not Authenticated'], 401);

    $uploadedFiles = $request->getUploadedFiles();
    $image = $uploadedFiles['image'] ?? null;
    if (!$image) {
        return json($response, ['ok' => false, 'error' => 'No image uploaded'], 400);
    }
    if ($image->getError() === UPLOAD_ERR_INI_SIZE || $image->getError() === UPLOAD_ERR_FORM_SIZE) {
        return json($response, ['ok' => false, 'error' => 'File exceeds the server upload limit. Max 5 MB.'], 422);
    }
    if ($image->getError() !== UPLOAD_ERR_OK) {
        return json($response, ['ok' => false, 'error' => 'Upload failed (code ' . $image->getError() . ')'], 400);
    }

    // Writes to a temp file so MediaValidator can inspect it with finfo/getimagesize
    $tmpPath = tempnam(sys_get_temp_dir(), 'forum_upload_');
    $image->moveTo($tmpPath);

    $validation = MediaValidator::validateImagePath($tmpPath, (int) $image->getSize());
    if (!$validation['ok']) {
        unlink($tmpPath);
        return json($response, ['ok' => false, 'error' => $validation['error']], 422);
    }

    $connectionString = $_ENV['AZURE_STORAGE_CONNECTION_STRING'];
    $accountName = $_ENV['AZURE_STORAGE_ACCOUNT_NAME'];
    $containerName = "images";

    if (!$connectionString || !$accountName) {
        unlink($tmpPath);
        return json($response, [
            'ok' => false,
            'error' => "Configuration missing. Ensure AZURE_STORAGE_CONNECTION_STRING is set in .env"
        ], 500);
    }

    try {
        $blobClient = BlobRestProxy::createBlobService($connectionString);

        $mimeType = $validation['meta']['mime'];
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
        $blobName = uniqid('img_', true) . '.' . $extension;

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        $options = new \MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions();
        $options->setContentType($mimeType);

        $blobClient->createBlockBlob($containerName, $blobName, $content, $options);

        $finalUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}";

        return json($response, ['ok' => true, 'url' => $finalUrl]);
    } catch (ServiceException $e) {
        unlink($tmpPath);
        return json($response, ['ok' => false, 'error' => 'Azure Error: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        unlink($tmpPath);
        return json($response, ['ok' => false, 'error' => 'Server Error: ' . $e->getMessage()], 500);
    }
});
