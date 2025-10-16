<?php
$server   = getenv('DB_SERVER')   ?: 'localhost,1433';
$database = getenv('DB_DATABASE') ?: 'testdb';
$user     = getenv('DB_USER')     ?: 'sa';
$pass     = getenv('DB_PASS')     ?: 'YourStrong(!)Passw0rd';

$dsn = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1";
try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
  ]);

  $row = $pdo->query("SELECT COUNT(*) AS n FROM dbo.Users")->fetch(PDO::FETCH_ASSOC);
  header('Content-Type: application/json');
  echo json_encode(['ok' => true, 'users' => (int)$row['n']]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
