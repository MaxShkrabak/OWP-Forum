<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use function Forum\Helpers\json;

$app->post('/api/upload-image', function (Request $request, Response $response) {
    $userId = $request->getAttribute('user_id');
    if (!$userId) return json($response, ['ok' => false, 'error' => 'Not Authenticated'], 401);

    $uploadedFiles = $request->getUploadedFiles();
    $image = $uploadedFiles['image'] ?? null;
    if (!$image || $image->getError() !== UPLOAD_ERR_OK) {
        return json($response, ['ok' => false, 'error' => 'No image uploaded'], 400);
    }

    $connectionString = $_ENV['AZURE_STORAGE_CONNECTION_STRING'];
    $accountName = $_ENV['AZURE_STORAGE_ACCOUNT_NAME'];
    $containerName = "images";
   
    if (!$connectionString || !$accountName) {
        return json($response, [
            'ok' => false,
            'error' => "Configuration missing. Ensure AZURE_STORAGE_CONNECTION_STRING is set in .env"
        ], 500);
    }
    try {
        $blobClient = BlobRestProxy::createBlobService($connectionString);

        $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION) ?: 'jpg';
        $blobName = uniqid('img_', true) . '.' . $extension;
       
        $content = $image->getStream()->getContents();
        $mimeType = $image->getClientMediaType();

        $options = new \MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions();
        $options->setContentType($mimeType);

        $blobClient->createBlockBlob($containerName, $blobName, $content, $options);

        $finalUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}";

        return json($response, ['ok' => true, 'url' => $finalUrl]);
    } catch (ServiceException $e) {
        return json($response, ['ok' => false, 'error' => 'Azure Error: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        return json($response, ['ok' => false, 'error' => 'Server Error: ' . $e->getMessage()], 500);
    }
});
