<?php
require_once __DIR__ . '/auth.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ui_stamp(bool $closed, string $open = 'Open', string $closedLabel = 'Closed'): string
{
    return '<span class="stamp ' . ($closed ? 'closed' : 'open') . '">' . e($closed ? $closedLabel : $open) . '</span>';
}

function ui_header(string $title, string $active = '', array $options = []): void
{
    $links = [
        'index'   => ['01', 'Dashboard'],
        'input'   => ['02', 'Monthly Input'],
        'mapping' => ['03', 'Mappings'],
        'report'  => ['04', 'Report'],
        'fields'  => ['05', 'Fields'],
    ];
    $user       = auth_user();
    $head       = $options['head'] ?? '';
    $mobileMeta = $options['mobile_meta'] ?? 'TOP / FINANCE';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — TOP CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<?= $head ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Open navigation">
<label for="nav-toggle" class="nav-scrim" aria-hidden="true"></label>

<aside class="rail">
  <div class="rail-inner">
    <a class="brand" href="./">
      <span class="brand-mark">TOP</span>
      <span class="brand-sub">Triniti Optima Packindo<br>Monthly report desk</span>
    </a>

    <nav class="rail-nav" aria-label="Main">
      <?php foreach ($links as $href => [$num, $label]): ?>
        <a href="<?= e($href) ?>" class="rail-link <?= $active === $href ? 'active' : '' ?>" <?= $active === $href ? 'aria-current="page"' : '' ?>>
          <span class="rail-num"><?= e($num) ?></span>
          <span class="rail-label"><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="rail-foot">
      <?php if ($user): ?>
        <span class="rail-user">Signed in as <strong><?= e($user) ?></strong></span>
        <a class="rail-signout" href="login?logout=1">Sign out</a>
      <?php else: ?>
        <a class="rail-signout" href="login">Sign in</a>
      <?php endif; ?>
    </div>
  </div>
</aside>

<div class="shell">
  <div class="mobile-bar">
    <label for="nav-toggle" class="menu-btn">Menu</label>
    <span class="mobile-brand">TOP</span>
    <span class="mobile-meta"><?= e($mobileMeta) ?></span>
  </div>

  <div class="progress" id="page-progress" aria-hidden="true"><span></span></div>

  <main id="main" class="page">
    <div class="page-inner">
<?php
}

function ui_footer(): void
{
    ?>
    </div><!-- /page-inner -->
  </main>
</div><!-- /shell -->
<script src="assets/app.js" defer></script>
</body>
</html>
<?php
}
