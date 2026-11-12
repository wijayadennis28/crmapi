<?php
require_once __DIR__ . '/auth.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ui_header(string $title, string $active = ''): void
{
    $links = [
        'index.php'   => 'Dashboard',
        'input.php'   => 'Monthly Input',
        'mapping.php' => 'Mappings',
        'report.php'  => 'Report',
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — TOP CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-nav">
  <span class="brand">&#9685; TOP CRM</span>
  <?php foreach ($links as $href => $label): ?>
    <a href="<?= e($href) ?>" class="<?= $active === $href ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <span class="spacer"></span>
  <span class="nav-user"><?= e(auth_user() ?? '') ?></span>
  <a href="login.php?logout=1">Sign out</a>
</div>
<div class="app-body">
<?php
}

function ui_footer(): void
{
    ?>
</div><!-- /app-body -->
</body>
</html>
<?php
}
