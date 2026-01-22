<?php
// edit.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
session_start();

function clean_string(?string $value, int $max = 120): string {
  $value = trim((string)$value);
  if ($value === '') return '';
  $value = preg_replace('/\s+/', ' ', $value);
  return mb_substr($value, 0, $max);
}

function redirect_with(string $url, array $params = []): void {
  $q = $params ? ('?' . http_build_query($params)) : '';
  header('Location: ' . $url . $q);
  exit();
}

function ensure_csrf(): void {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
}
ensure_csrf();

$id = null;
$name = '';
$email = '';

/**
 * GET: show form with user data
 * POST: update user data (secure)
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
  if (!$id || $id <= 0) {
    redirect_with('index.php', ['status' => 'error', 'msg' => 'Invalid user ID.']);
  }

  try {
    $stmt = $conn->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
      redirect_with('index.php', ['status' => 'error', 'msg' => 'User not found.']);
    }

    $row = $result->fetch_assoc();
    $id = (int)$row['id'];
    $name = (string)$row['name'];
    $email = (string)$row['email'];
  } catch (mysqli_sql_exception $e) {
    redirect_with('index.php', ['status' => 'error', 'msg' => 'Could not load user.']);
  }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  $token = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
    redirect_with('index.php', ['status' => 'error', 'msg' => 'Invalid CSRF token.']);
  }

  $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
  $name = clean_string($_POST['name'] ?? '', 80);
  $email = clean_string($_POST['email'] ?? '', 120);

  $errors = [];
  if (!$id || $id <= 0) $errors[] = 'Invalid user ID.';
  if ($name === '' || mb_strlen($name) < 2) $errors[] = 'Name must be at least 2 characters.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email.';

  if ($errors) {
    redirect_with('edit.php', [
      'id' => (int)($id ?: 0),
      'status' => 'error',
      'msg' => implode(' ', $errors),
    ]);
  }

  try {
    // Optional: if you want email unique, keep this check (recommended)
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->bind_param('si', $email, $id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    if ($exists) {
      redirect_with('edit.php', [
        'id' => $id,
        'status' => 'error',
        'msg' => 'This email is already used by another user.',
      ]);
    }

    $stmt = $conn->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
    $stmt->bind_param('ssi', $name, $email, $id);
    $stmt->execute();

    redirect_with('index.php', [
      'status' => 'success',
      'msg' => 'User updated successfully.',
    ]);
  } catch (mysqli_sql_exception $e) {
    redirect_with('edit.php', [
      'id' => $id,
      'status' => 'error',
      'msg' => 'Could not update user.',
    ]);
  }
}
else {
  http_response_code(405);
  exit('Method Not Allowed');
}

// If redirected back with messages
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit User</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 24px; }
    .card { max-width: 520px; border: 1px solid #ddd; border-radius: 12px; padding: 16px; }
    label { display: block; margin-top: 12px; font-weight: 600; }
    input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 10px; margin-top: 6px; }
    button { margin-top: 16px; padding: 10px 14px; border: 0; border-radius: 10px; cursor: pointer; }
    .row { display: flex; gap: 10px; align-items: center; }
    .muted { color: #666; font-size: 14px; margin-top: 6px; }
    .alert { margin-bottom: 12px; padding: 10px; border-radius: 10px; }
    .success { background: #e9fbef; border: 1px solid #bde7c7; }
    .error { background: #ffecec; border: 1px solid #f0b4b4; }
    a { color: #0b57d0; text-decoration: none; }
  </style>
</head>
<body>
  <div class="card">
    <div class="row" style="justify-content: space-between;">
      <h2 style="margin: 0;">Edit User</h2>
      <a href="index.php">← Back</a>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert <?= $status === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="edit.php" autocomplete="off">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">

      <label for="name">Name</label>
      <input id="name" type="text" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="email">Email</label>
      <input id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>

      <div class="muted">Changes are validated and saved securely.</div>

      <button type="submit">Update User</button>
    </form>
  </div>
</body>
</html>
