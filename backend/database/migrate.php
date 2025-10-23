<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load the .env data
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// ---------- CONFIG ----------
$server   = $_ENV['DB_SERVER'];           // Docker-mapped or local
$database = $_ENV['DB_DATABASE'];         // name of the database
$user     = $_ENV['DB_USER'];             // db username
$pass     = $_ENV['DB_PASS'];             // db password
$migrationsDir = __DIR__ . '/migrations';

// ---------- CONNECT ----------
$dsn = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1";
$pdo = new PDO($dsn, $user, $pass, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
]);

// ---------- HELPERS ----------
function ensureSchemaVersions(PDO $pdo): void {
  $sql = <<<SQL
IF OBJECT_ID('dbo.SchemaVersions', 'U') IS NULL
BEGIN
  CREATE TABLE dbo.SchemaVersions (
      Id INT IDENTITY(1,1) PRIMARY KEY,
      ScriptName NVARCHAR(255) NOT NULL UNIQUE,
      AppliedAt  DATETIME2(0) NOT NULL DEFAULT SYSDATETIME()
  );
END
SQL;
  $pdo->exec($sql);
}

function appliedScripts(PDO $pdo): array {
  $rows = $pdo->query("SELECT ScriptName FROM dbo.SchemaVersions")->fetchAll(PDO::FETCH_COLUMN);
  return $rows ? array_flip($rows) : [];
}

/**
 * Split a SQL Server script on GO batch separators.
 * - Matches lines that are ONLY 'GO' (case-insensitive, allow leading/trailing spaces).
 */
function splitOnGo(string $sql): array {
  // Normalize line endings, ensure trailing newline
  $sql = str_replace("\r\n", "\n", $sql);
  if (!str_ends_with($sql, "\n")) $sql .= "\n";

  $parts = preg_split('/^[ \t]*GO[ \t]*$\n/mi', $sql);
  // Trim whitespace-only parts
  return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

function runSqlBatches(PDO $pdo, string $sql): void {
  $batches = splitOnGo($sql);
  foreach ($batches as $i => $batch) {
    if ($batch === '') continue;
    $pdo->exec($batch);
  }
}

// ---------- MAIN ----------
echo "== Database migrations ==\n";
echo "Server: $server | DB: $database\n";
echo "Scanning: $migrationsDir\n\n";

if (!is_dir($migrationsDir)) {
  fwrite(STDERR, "Migrations directory not found.\n");
  exit(1);
}

ensureSchemaVersions($pdo);
$applied = appliedScripts($pdo);

// Gather .sql files and natural sort (001_..., 002_..., etc.)
$files = array_filter(scandir($migrationsDir), fn($f) => preg_match('/\.sql$/i', $f));
natsort($files);
$files = array_values($files);

if (!$files) {
  echo "No migration files found.\n";
  exit(0);
}

$appliedCount = 0;
$skippedCount = 0;

foreach ($files as $file) {
  if (isset($applied[$file])) {
    echo "SKIP  $file (already applied)\n";
    $skippedCount++;
    continue;
  }

  $path = $migrationsDir . DIRECTORY_SEPARATOR . $file;
  $sql  = file_get_contents($path);
  if ($sql === false) {
    fwrite(STDERR, "ERROR reading $file\n");
    exit(1);
  }

  echo "APPLY $file ... ";
  try {
    // Per-file transaction
    $pdo->beginTransaction();
    runSqlBatches($pdo, $sql);
    $stmt = $pdo->prepare("INSERT INTO dbo.SchemaVersions (ScriptName) VALUES (:s)");
    $stmt->execute([':s' => $file]);
    $pdo->commit();
    echo "OK\n";
    $appliedCount++;
  } catch (Throwable $e) {
    $pdo->rollBack();
    echo "FAILED\n";
    fwrite(STDERR, "  -> " . $e->getMessage() . "\n");
    // Stop on first failure to keep DB consistent
    exit(1);
  }
}

echo "\nDone. Applied: $appliedCount, Skipped: $skippedCount\n";
