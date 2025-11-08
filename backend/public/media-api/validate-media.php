<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../src/MediaValidator.php';

// CORS for your Vite dev server (adjust in prod)
header('Access-Control-Allow-Origin: http://127.0.0.1:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'POST only']); exit;
}

if (!isset($_FILES['file'])) {
  http_response_code(400);
  echo json_encode(['error' => 'No file field named "file"']); exit;
}

$result = MediaValidator::validateUploadedFile($_FILES['file']);
http_response_code($result['ok'] ? 200 : 422);
header('Content-Type: application/json');
echo json_encode($result);
