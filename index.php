<?php
// index.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
session_start();

function e(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ensure_csrf(): void {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
}
ensure_csrf();

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg    = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

$users = [];
$total = 0;

try {
  $stmt = $conn->prepare('SELECT id, name, email FROM users ORDER BY id DESC');
  $stmt->execute();
  $res = $stmt->get_result();
  $users = $res->fetch_all(MYSQLI_ASSOC);
  $total = count($users);
  $stmt->close();
} catch (mysqli_sql_exception $e) {
  $status = 'error';
  $msg = 'Could not load users.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CRUD System</title>
  <style>
    :root{
      --bg:#0b1220;
      --card:#111a2e;
      --card2:#0f1730;
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

    .wrap{
      max-width: 980px;
      margin: 0 auto;
      padding: 28px 16px 46px;
    }

    .topbar{
      display:flex;
      align-items:flex-end;
      justify-content:space-between;
      gap:16px;
      margin-bottom: 18px;
    }

    h1{
      margin:0;
      font-size: 22px;
      letter-spacing: .2px;
    }
    .subtitle{
      margin-top:6px;
      color:var(--muted);
      font-size: 13px;
      line-height: 1.4;
    }

    .badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 10px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,.06);
      border:1px solid var(--line);
      color: var(--muted);
      font-size: 13px;
      white-space:nowrap;
    }
    .dot{
      width:10px;height:10px;border-radius:999px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      box-shadow: 0 0 18px rgba(110,231,255,.35);
    }

    .grid{
      display:grid;
      grid-template-columns: 360px 1fr;
      gap: 16px;
      align-items:start;
    }
    @media (max-width: 920px){
      .grid{ grid-template-columns: 1fr; }
    }

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
    }
    .cardHeader h2{
      margin:0;
      font-size: 14px;
      letter-spacing:.2px;
    }
    .cardHeader p{
      margin:6px 0 0;
      color:var(--muted);
      font-size: 12.5px;
      line-height: 1.4;
    }

    .cardBody{ padding: 16px; }

    .alert{
      margin-bottom: 14px;
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

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      width:100%;
      padding: 11px 14px;
      border-radius: 12px;
      border:1px solid rgba(110,231,255,.35);
      background: linear-gradient(135deg, rgba(110,231,255,.22), rgba(167,139,250,.18));
      color: var(--text);
      font-weight: 600;
      cursor:pointer;
      transition: transform .12s ease, filter .12s ease;
    }
    .btn:hover{ filter: brightness(1.05); transform: translateY(-1px); }
    .btn:active{ transform: translateY(0); }

    table{
      width:100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    th, td{
      text-align:left;
      padding: 12px 10px;
      border-bottom: 1px solid var(--line);
      vertical-align: middle;
    }
    th{
      color: var(--muted);
      font-weight: 600;
      font-size: 12px;
      letter-spacing: .2px;
    }
    tbody tr:hover{
      background: rgba(255,255,255,.03);
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap: wrap;
      align-items: center;
    }
    .link{
      color: var(--accent);
      text-decoration:none;
      font-weight: 600;
      background: none;
      border: 0;
      padding: 0;
      font: inherit;
      cursor: pointer;
    }
    .link:hover{ text-decoration: underline; }
    .danger{ color: var(--err); }

    .empty{
      padding: 18px 12px;
      color: var(--muted);
      text-align:center;
    }

    .small{
      color: var(--muted);
      font-size: 12px;
      margin-top: 10px;
      line-height: 1.45;
    }

    /* Make delete POST form look like a link */
    .inlineForm{ display:inline; margin:0; }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="topbar">
      <div>
        <h1>User Management System</h1>
        <div class="subtitle">
          Simple CRUD portfolio project, secure input handling, clean UI, and scalable structure.
        </div>
      </div>
      <div class="badge" title="Current rows in users table">
        <span class="dot"></span>
        <span><strong><?= (int)$total ?></strong> users</span>
      </div>
    </div>

    <?php if ($status && $msg): ?>
      <div class="alert <?= $status === 'success' ? 'ok' : ($status === 'error' ? 'err' : '') ?>">
        <?= e($msg) ?>
      </div>
    <?php endif; ?>

    <div class="grid">
      <!-- Create form -->
      <section class="card">
        <div class="cardHeader">
          <h2>Add User</h2>
          <p>Creates a new row in <code>users</code> using server-side validation.</p>
        </div>
        <div class="cardBody">
          <form method="post" action="create.php" autocomplete="off">
            <label for="name">Name</label>
            <input id="name" type="text" name="name" required minlength="2" maxlength="80" placeholder="e.g. Rafael Simionato" />

            <label for="email">Email</label>
            <input id="email" type="email" name="email" required maxlength="120" placeholder="e.g. hello@example.com" />

            <button class="btn" type="submit">Add user</button>
          </form>

          <div class="small">
            Tip: In a real system, enforce a UNIQUE index on <code>email</code> and handle duplicates gracefully (already supported in the create handler).
          </div>
        </div>
      </section>

      <!-- User list -->
      <section class="card">
        <div class="cardHeader">
          <h2>User List</h2>
          <p>Edit and delete actions. Output is escaped to prevent XSS. Delete uses POST + CSRF.</p>
        </div>
        <div class="cardBody" style="padding:0;">
          <div style="overflow:auto;">
            <table>
              <thead>
                <tr>
                  <th style="min-width:160px;">Name</th>
                  <th style="min-width:220px;">Email</th>
                  <th style="min-width:180px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$users): ?>
                  <tr>
                    <td colspan="3" class="empty">No users found. Add the first user using the form.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($users as $row): ?>
                    <?php
                      $id = (int)$row['id'];
                      $name = (string)$row['name'];
                      $email = (string)$row['email'];
                    ?>
                    <tr>
                      <td><?= e($name) ?></td>
                      <td><?= e($email) ?></td>
                      <td>
                        <div class="actions">
                          <a class="link" href="edit.php?id=<?= $id ?>">Edit</a>

                          <form class="inlineForm" method="post" action="delete.php"
                                onsubmit="return confirm('Delete this user? This action cannot be undone.');">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
                            <button class="link danger" type="submit">Delete</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</body>
</html>
