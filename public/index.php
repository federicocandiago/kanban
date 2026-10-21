<?php

declare(strict_types=1);

// PHP built-in server: let it serve real files (css, js, wasm...) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

define('PUBLIC_PATH', __DIR__);

/*
 * Where the project (app/, storage/, .env...) lives. Checked in order:
 *  1. the folder above this one          → project/public/index.php (default layout)
 *  2. a "kalban" folder next to this one → /home/user/public_html + /home/user/kalban (shared hosting)
 * Using another folder? Put its absolute path first in the list.
 */
$candidates = [dirname(__DIR__), dirname(__DIR__) . '/kalban'];
foreach ($candidates as $root) {
    if (is_file($root . '/bootstrap/app.php')) {
        require $root . '/bootstrap/app.php';
        (new App\Core\App())->run();
        return;
    }
}

http_response_code(500);
echo 'Kalban: project folder not found. Edit $candidates in index.php.';
