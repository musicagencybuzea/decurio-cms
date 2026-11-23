<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

$total = (int)db()->query('SELECT COUNT(*) FROM albums')->fetchColumn();
$published = (int)db()->query("SELECT COUNT(*) FROM albums WHERE status='published'")->fetchColumn();
$scheduled = (int)db()->query("SELECT COUNT(*) FROM albums WHERE status='scheduled'")->fetchColumn();
$drafts = $total - $published - $scheduled;

admin_page_open(t('nav.dashboard'), 'dashboard');
?>
<h1><?= e(t('nav.dashboard')) ?></h1>
<p class="subtitle"><?= e(t('dash.subtitle')) ?></p>

<div class="cards">
    <div class="card">
        <span class="label"><?= e(t('dash.total')) ?></span>
        <p class="num"><?= $total ?></p>
    </div>
    <div class="card">
        <span class="label"><?= e(t('dash.published')) ?></span>
        <p class="num"><?= $published ?></p>
    </div>
    <div class="card">
        <span class="label"><?= e(t('dash.scheduled')) ?></span>
        <p class="num"><?= $scheduled ?></p>
    </div>
    <div class="card">
        <span class="label"><?= e(t('dash.drafts')) ?></span>
        <p class="num"><?= $drafts ?></p>
    </div>
</div>

<p class="viewsite"><a href="../" target="_blank" rel="noopener"><?= e(t('dash.view_site')) ?></a></p>
<?php admin_page_close();
