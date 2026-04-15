<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();

$server        = $_ENV['DB_SERVER'];
$database      = $_ENV['DB_DATABASE'];
$migrationsDir = __DIR__ . '/migrations';

$pdo = (require __DIR__ . '/../src/Database.php')()();

function ensureSchemaVersions(PDO $pdo): void {
  $pdo->exec(<<<SQL
    IF OBJECT_ID('dbo.Forum_SchemaVersions', 'U') IS NULL
    BEGIN
      CREATE TABLE dbo.Forum_SchemaVersions (
          Id INT IDENTITY(1,1) PRIMARY KEY,
          ScriptName NVARCHAR(255) NOT NULL UNIQUE,
          AppliedAt  DATETIME2(0) NOT NULL DEFAULT SYSDATETIME()
      );
    END
    SQL);
}

function appliedScripts(PDO $pdo): array {
  $rows = $pdo->query("SELECT ScriptName FROM dbo.Forum_SchemaVersions")->fetchAll(PDO::FETCH_COLUMN);
  return $rows ? array_flip($rows) : [];
}

/**
 * Split a SQL Server script on GO batch separators.
 * Matches lines that are ONLY 'GO' (case-insensitive, optional surrounding whitespace).
 */
function splitOnGo(string $sql): array {
  $sql = str_replace("\r\n", "\n", $sql);
  if (!str_ends_with($sql, "\n")) $sql .= "\n";

  $parts = preg_split('/^[ \t]*GO[ \t]*$\n/mi', $sql);
  return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

function runSqlBatches(PDO $pdo, string $sql): void {
  foreach (splitOnGo($sql) as $batch) {
    $pdo->exec($batch);
  }
}

echo "== Database migrations ==\n";
echo "Server: $server | DB: $database\n";
echo "Scanning: $migrationsDir\n\n";

if (!is_dir($migrationsDir)) {
  fwrite(STDERR, "Migrations directory not found.\n");
  exit(1);
}

ensureSchemaVersions($pdo);
$applied = appliedScripts($pdo);

// natsort ensures 001_..., 002_... ordering
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

  $sql = file_get_contents($migrationsDir . '/' . $file);
  if ($sql === false) {
    fwrite(STDERR, "ERROR reading $file\n");
    exit(1);
  }

  echo "APPLY $file ... ";
  try {
    $pdo->beginTransaction();
    runSqlBatches($pdo, $sql);
    $pdo->prepare("INSERT INTO dbo.Forum_SchemaVersions (ScriptName) VALUES (:s)")->execute([':s' => $file]);
    $pdo->commit();
    echo "OK\n";
    $appliedCount++;
  } catch (Throwable $e) {
    $pdo->rollBack();
    echo "FAILED\n";
    fwrite(STDERR, "  -> " . $e->getMessage() . "\n");
    exit(1); // stop on first failure to keep DB consistent
  }
}

echo "\nDone. Applied: $appliedCount, Skipped: $skippedCount\n";
