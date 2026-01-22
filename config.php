<?php
// config.php
declare(strict_types=1);

/**
 * Database configuration for the CRUD demo.
 * Uses environment variables when available and falls back to local defaults.
 * Safe for production when secrets are injected via .env or server config.
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Load environment variables (optional if you later use dotenv)
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: 'root';
$DB_NAME = getenv('DB_NAME') ?: 'CRUDdb';

try {
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  // In production, never expose internal errors
  http_response_code(500);
  exit('Database connection error.');
}
