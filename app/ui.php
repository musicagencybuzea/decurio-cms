<?php
declare(strict_types=1);

function admin_page_open(string $title, string $active): void
{
    $s = settings();
    ?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
<title><?= e($title) ?> – decurio cms</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="wrap">
<header class="top">
    <a class="brandlink" href="dashboard.php">
        <?php if (!empty($s['logo_file'])): ?>
            <img class="logo" src="../<?= e($s['logo_file']) ?>" alt="decurio">
        <?php else: ?>
            <span class="brand">decurio&nbsp;<span class="accent">cms</span></span>
        <?php endif; ?>
    </a>
    <div class="langs">
        <?php foreach (SUPPORTED_LANGS as $l): ?>
            <a href="<?= lang_url($l) ?>"<?= $l === lang() ? ' class="active"' : '' ?>><?= strtoupper($l) ?></a>
        <?php endforeach; ?>
    </div>
</header>
<nav class="mainnav">
    <a class="tab<?= $active === 'dashboard' ? ' active' : '' ?>" href="dashboard.php"><?= e(t('nav.dashboard')) ?></a>
    <a class="tab<?= $active === 'albums' ? ' active' : '' ?>" href="albums.php"><?= e(t('nav.albums')) ?></a>
    <a class="tab<?= $active === 'settings' ? ' active' : '' ?>" href="settings.php"><?= e(t('nav.settings')) ?></a>
    <a class="tab logout" href="logout.php"><?= e(t('nav.logout')) ?></a>
</nav>
<?php
}

function admin_page_close(): void
{
    ?>
</div>
</body>
</html>
    <?php
}
