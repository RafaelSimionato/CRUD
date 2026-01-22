<?php
// edit.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
session_start();

function e(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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

// If redirected back with messages
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg    = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

/**
 * GET: show form with user data
 * POST: update user data (secure)
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
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

    $stmt->close();
  } catch (mysqli_sql_exception $e) {
    redirect_with('index.php', ['status' => 'error', 'msg' => 'Could not load user.']);
  }
}
elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  // CSRF check (string-safe)
  $token = (string)($_POST['csrf'] ?? '');
  if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
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
    // Email uniqueness check (recommended)
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->bind_param('si', $email, $id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($exists) {
      redirect_with('edit.php', [
        'id' => $id,
        'status' => 'error',
        'msg' => 'This email is already used by another user.',
      ]);
    }

    // Update
    $stmt = $conn->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
    $stmt->bind_param('ssi', $name, $email, $id);
    $stmt->execute();

    // Nice UX: detect missing user
    if ($stmt->affected_rows < 1) {
      // Could be "no changes" OR "not found". We'll verify quickly.
      $stmt->close();

      $check = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
      $check->bind_param('i', $id);
      $check->execute();
      $found = $check->get_result()->num_rows === 1;
      $check->close();

      if (!$found) {
        redirect_with('index.php', [
          'status' => 'error',
          'msg' => 'User not found (maybe deleted).',
        ]);
      }

      // User exists, just no changes
      redirect_with('index.php', [
        'status' => 'success',
        'msg' => 'No changes to save (already up to date).',
      ]);
    }

    $stmt->close();

    redirect_with('index.php', [
      'status' => 'success',
      'msg' => 'User updated successfully.',
    ]);
  } catch (mysqli_sql_exception $e) {
    redirect_with('edit.php', [
      'id' => (int)$id,
      'status' => 'error',
      'msg' => 'Could not update user.',
    ]);
  }
}
else {
  http_response_code(405);
  exit('Method Not Allowed');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit User</title>
  <style>
    :root{
      --bg:#0b1220;
      --card:#111a2e;
      --text:#e6edf7;
      --muted:#a7b4cc;
      --line:rgba(255,255,255,.10);
      --accent:#6ee7ff;
      --accent2:#a78bfa;
      --ok:#34d399;
      --err:#fb7185;
      --shadow: 0 18px 50px rgba(0,0,0,.45);
      --radius: 16px;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      background: radial-gradient(1200px 700px at 20% -10%, rgba(110,231,255,.14), transparent 55%),
                  radial-gradient(900px 600px at 95% 0%, rgba(167,139,250,.16), transparent 60%),
                  var(--bg);
      color:var(--text);
    }
    .wrap{ max-width: 720px; margin: 0 auto; padding: 28px 16px 46px; }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
      border:1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    .cardHeader{
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      background: rgba(255,255,255,.02);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
    }
    .cardHeader h1{ margin:0; font-size: 16px; letter-spacing:.2px; }
    .back{
      color: var(--accent);
      text-decoration:none;
      font-weight: 700;
      font-size: 13px;
    }
    .back:hover{ text-decoration: underline; }
    .cardBody{ padding: 16px; }

    .alert{
      margin: 0 0 14px;
      padding: 12px 14px;
      border-radius: 12px;
      border:1px solid var(--line);
      background: rgba(255,255,255,.05);
      color: var(--text);
      font-size: 13px;
    }
    .alert.ok{ border-color: rgba(52,211,153,.35); background: rgba(52,211,153,.10); }
    .alert.err{ border-color: rgba(251,113,133,.35); background: rgba(251,113,133,.10); }

    label{
      display:block;
      color: var(--muted);
      font-size: 12px;
      margin-bottom: 6px;
    }
    input{
      width:100%;
      padding: 11px 12px;
      border-radius: 12px;
      border:1px solid var(--line);
      background: rgba(0,0,0,.18);
      color: var(--text);
      outline:none;
      transition: border .15s ease, box-shadow .15s ease;
      margin-bottom: 12px;
    }
    input:focus{
      border-color: rgba(110,231,255,.45);
      box-shadow: 0 0 0 4px rgba(110,231,255,.10);
    }

    .muted{ color: var(--muted); font-size: 12px; margin-top: 6px; line-height: 1.45; }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:100%;
      padding: 11px 14px;
      border-radius: 12px;
      border:1px solid rgba(110,231,255,.35);
      background: linear-gradient(135deg, rgba(110,231,255,.22), rgba(167,139,250,.18));
      color: var(--text);
      font-weight: 700;
      cursor:pointer;
      transition: transform .12s ease, filter .12s ease;
      margin-top: 4px;
    }
    .btn:hover{ filter: brightness(1.05); transform: translateY(-1px); }
    .btn:active{ transform: translateY(0); }
  </style>
</head>
<body>
  <div class="wrap">
    <section class="card">
      <div class="cardHeader">
        <h1>Edit User</h1>
        <a class="back" href="index.php">← Back</a>
      </div>

      <div class="cardBody">
        <?php if ($msg !== ''): ?>
          <div class="alert <?= $status === 'success' ? 'ok' : 'err' ?>">
            <?= e($msg) ?>
          </div>
        <?php endif; ?>

        <form method="post" action="edit.php" autocomplete="off">
          <input type="hidden" name="id" value="<?= (int)$id ?>">
          <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">

          <label for="name">Name</label>
          <input id="name" type="text" name="name" value="<?= e($name) ?>" required minlength="2" maxlength="80">

          <label for="email">Email</label>
          <input id="email" type="email" name="email" value="<?= e($email) ?>" required maxlength="120">

          <div class="muted">Changes are validated, protected with CSRF, and saved using prepared statements.</div>

          <button class="btn" type="submit">Save changes</button>
        </form>
      </div>
    </section>
  </div>
</body>
</html>
