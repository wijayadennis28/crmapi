<?php
// Copy to config.local.php and fill in. config.local.php is gitignored.
// Generate a hash: php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), "\n";'
define('APP_USER', 'admin');
define('APP_PASSWORD_HASH', '$2y$10$replace-with-your-own-hash');
define('DATA_DIR', __DIR__ . '/data');
