<?php
require_once __DIR__ . '/auth.php';

if (isset($_GET['logout'])) {
    logout();
    header('Location: login');
    exit;
}

if (auth_user()) {
    header('Location: input');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (attempt_login(trim($_POST['user'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: input');
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
<title>Sign in — TOP Monthly Report</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-shell">
  <section class="login-brand" aria-label="TOP Monthly Report">
    <span class="login-mark">TOP</span>
    <span class="login-brand-sub">Triniti Optima Packindo<br>Monthly report desk</span>
    <div class="login-meta">
      <span>Finance / Monthly close</span>
      <span>TOP &middot; MMP &middot; SAE</span>
    </div>
  </section>

  <section class="login-panel">
    <form method="post" class="login-form">
      <div class="eyebrow"><span class="doc-no">ACCESS</span><span class="dot">/</span><span>Finance</span></div>
      <h1 class="mt-12">Sign in</h1>
      <p class="sub">Enter monthly figures, map entities, and close the report.</p>

      <?php if ($error): ?>
        <div class="notice notice-danger" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="field">
        <label for="user">Username</label>
        <input class="input" id="user" name="user" autocomplete="username" required autofocus>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn btn-signal">Sign in</button>

      <p class="login-foot">Monthly report access only. The CRM dashboard remains public.</p>
    </form>
  </section>
</div>
</body>
</html>
