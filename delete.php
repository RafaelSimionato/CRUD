<?php
// delete.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
session_start();

function redirect_with(string $url, array $params = []): void {
  $q = $params ? ('?' . http_build_query($params)) : '';
  header('Location: ' . $url . $q);
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

// CSRF protection
$token = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
  redirect_with('index.php', [
    'status' => 'error',
    'msg' => 'Invalid CSRF token.',
  ]);
}

// Validate ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
  redirect_with('index.php', [
    'status' => 'error',
    'msg' => 'Invalid user ID.',
  ]);
}

// Delete safely
try {
  $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
  $stmt->bind_param('i', $id);
  $stmt->execute();

  redirect_with('index.php', [
    'status' => 'success',
    'msg' => 'User deleted successfully.',
  ]);
} catch (mysqli_sql_exception $e) {
  redirect_with('index.php', [
    'status' => 'error',
    'msg' => 'Could not delete user.',
  ]);
}
