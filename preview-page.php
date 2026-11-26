<?php
declare(strict_types=1);

/**
 * Dedicated preview entry point: /preview-page.php?lang=xx
 * Same renderer as index.php?preview, but on its own URL so no host-level
 * cache or canonical /index.php -> / redirect can ever serve the homepage.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ui.php';

header('Cache-Control: no-store, max-age=0');
$_GET['preview'] = '1';
require __DIR__ . '/app/preview-render.php';
