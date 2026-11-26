<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
if (logged_in()) redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = db()->prepare('SELECT id,password_hash FROM admins WHERE username=? LIMIT 1');
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($_POST['password'] ?? '', $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        redirect('dashboard.php');
    }
    $error = t('login.error');
}
$s = settings();
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
<title>administration – decurio cms</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/admin.css', '../')) ?>">
</head>
<body class="login-body">
<div>
    <div class="login-panel">
        <header class="login-head">
            <?php if (!empty($s['logo_file'])): ?>
                <img class="logo" src="../<?= e($s['logo_file']) ?>" alt="decurio">
            <?php else: ?>
                <span class="brand">decurio&nbsp;<span class="accent">cms</span></span>
            <?php endif; ?>
            <p class="subtitle"><?= e(t('login.heading')) ?></p>
        </header>
        <div class="langs">
            <?php foreach (SUPPORTED_LANGS as $l): ?>
                <a href="<?= lang_url($l) ?>"<?= $l === lang() ? ' class="active"' : '' ?>><?= strtoupper($l) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label for="username"><?= e(t('login.username')) ?></label>
            <input id="username" name="username" required autofocus autocomplete="username">
            <label for="password"><?= e(t('login.password')) ?></label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
            <p class="form-actions"><button type="submit"><?= e(t('login.submit')) ?></button></p>
        </form>
    </div>
</div>
</body>
</html>
