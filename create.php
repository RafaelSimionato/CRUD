<?php
// create.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();

function clean_string(?string $value, int $max = 120): string {
  $value = trim((string)$value);
  if ($value === '') return '';
  $value = preg_replace('/\s+/', ' ', $value) ?? $value;
  return mb_substr($value, 0, $max);
}

function redirect_with(string $url, array $params = []): void {
  $q = $params ? ('?' . http_build_query($params)) : '';
  header('Location: ' . $url . $q);
  exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

/**
 * CSRF protection
 * - index.php generates/stores $_SESSION['csrf']
 * - create.php must validate it
 */
$token = (string)($_POST['csrf'] ?? '');
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
  redirect_with('index.php', [
    'status' => 'error',
    'msg' => 'Invalid CSRF token. Please refresh and try again.',
  ]);
}

$name  = clean_string($_POST['name'] ?? '', 80);
$email = clean_string($_POST['email'] ?? '', 120);

// Basic validation
$errors = [];
if ($name === '' || mb_strlen($name) < 2) $errors[] = 'Name must be at least 2 characters.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email.';

if ($errors) {
  redirect_with('index.php', [
    'status' => 'error',
    'msg' => implode(' ', $errors),
  ]);
}

try {
  $stmt = $conn->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
  $stmt->bind_param('ss', $name, $email);
  $stmt->execute();

  redirect_with('index.php', [
    'status' => 'success',
    'msg' => 'User created successfully.',
  ]);
} catch (mysqli_sql_exception $e) {
  // Optional internal logging
  // error_log($e->getMessage());

  redirect_with('index.php', [
    'status' => 'error',
    'msg' => 'Could not create user. Email may already exist.',
  ]);
}
