<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $values = [
        'site_title' => trim($_POST['site_title'] ?? ''),
        'tagline' => trim($_POST['tagline'] ?? ''),
        'logo_url' => trim($_POST['logo_url'] ?? ''),
        'logo_file' => trim($_POST['logo_file'] ?? ''),
        'footer_text' => trim($_POST['footer_text'] ?? ''),
        'favicon_file' => trim($_POST['favicon_file'] ?? ''),
        'welcome_track_id' => (string)((int)($_POST['welcome_track_id'] ?? 0) ?: 0),
    ];
    $q = db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach ($values as $k => $v) $q->execute([$k, $v]);
    redirect('settings.php?saved=1');
}
$s = settings();
$tracks = tracks_for_select();

admin_page_open(t('settings.title'), 'settings');
?>
<h1><?= e(t('settings.title')) ?></h1>
<p class="subtitle"><?= e(t('settings.subtitle')) ?></p>

<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('settings.saved')) ?></p><?php endif; ?>

<div class="panel">
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="site_title"><?= e(t('settings.site_title')) ?></label>
        <input id="site_title" name="site_title" value="<?= e($s['site_title'] ?? '') ?>">
        <label for="tagline"><?= e(t('settings.tagline')) ?></label>
        <input id="tagline" name="tagline" value="<?= e($s['tagline'] ?? '') ?>">
        <label for="logo_url"><?= e(t('settings.logo_url')) ?></label>
        <input id="logo_url" name="logo_url" value="<?= e($s['logo_url'] ?? '') ?>">
        <label for="footer_text"><?= e(t('settings.footer')) ?></label>
        <input id="footer_text" name="footer_text" value="<?= e($s['footer_text'] ?? '') ?>">
        <label for="logo_file"><?= e(t('settings.logo_file')) ?></label>
        <input id="logo_file" name="logo_file" value="<?= e($s['logo_file'] ?? '') ?>">
        <label for="favicon_file"><?= e(t('settings.favicon')) ?></label>
        <input id="favicon_file" name="favicon_file" placeholder="assets/images/favicon.png" value="<?= e($s['favicon_file'] ?? '') ?>">
        <label for="welcome_track_id"><?= e(t('settings.welcome')) ?></label>
        <select id="welcome_track_id" name="welcome_track_id">
            <option value="0"><?= e(t('settings.welcome_none')) ?></option>
            <?php foreach ($tracks as $tr): ?>
                <option value="<?= (int)$tr['id'] ?>" <?= (int)($s['welcome_track_id'] ?? 0) === (int)$tr['id'] ? 'selected' : '' ?>><?= e($tr['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="small"><a href="music.php"><?= e(t('albums.music.manage')) ?></a></p>
        <p class="form-actions"><button type="submit"><?= e(t('settings.save')) ?></button></p>
    </form>
</div>
<?php admin_page_close();
