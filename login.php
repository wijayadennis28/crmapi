<?php
require_once __DIR__ . '/auth.php';

if (isset($_GET['logout'])) {
    logout();
    header('Location: login.php');
    exit;
}

if (auth_user()) {
    header('Location: input.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (attempt_login(trim($_POST['user'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: input.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — CRM Monthly Report</title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .auth-wrap { max-width: 380px; margin: 12vh auto; padding: 0 20px; }
  .auth-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 28px; }
  .auth-card h1 { font-size: 1.15rem; margin-bottom: 6px; }
  .auth-card p.sub { font-size: .82rem; color: var(--muted); margin-bottom: 20px; }
  .auth-card label { display: block; font-size: .75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin: 14px 0 5px; }
  .auth-card input { width: 100%; height: 40px; padding: 0 10px; border: 1px solid var(--border); border-radius: 6px; font-size: .9rem; }
  .auth-card button { width: 100%; margin-top: 20px; justify-content: center; }
</style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>&#9685; TOP CRM Monthly Report</h1>
    <p class="sub">Sign in to enter monthly figures and view the report.</p>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label for="user">Username</label>
      <input id="user" name="user" autocomplete="username" required autofocus>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit" class="btn btn-primary">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
