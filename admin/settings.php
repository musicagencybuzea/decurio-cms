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
        'imprint_url' => trim($_POST['imprint_url'] ?? ''),
        'favicon_file' => trim($_POST['favicon_file'] ?? ''),
        'welcome_track_id' => (string)((int)($_POST['welcome_track_id'] ?? 0) ?: 0),
        // Design adjustments: applied directly, without a new version of the site
        'design_main_title' => (string)((int)($_POST['design_main_title'] ?? 0) ?: 19),
        'design_main_meta' => (string)((int)($_POST['design_main_meta'] ?? 0) ?: 15),
        'design_flag' => (string)((int)($_POST['design_flag'] ?? 0) ?: 11),
        'design_accent' => in_array($_POST['design_accent'] ?? '', ['#141414', '#f0f0f0', '#d6b399', '#f05078'], true) ? $_POST['design_accent'] : '#f05078',
        'design_rel_top' => isset($_POST['design_rel_top']) ? (string)max(0, (int)$_POST['design_rel_top']) : '38',
        'design_rel_cov' => isset($_POST['design_rel_cov']) ? (string)max(0, (int)$_POST['design_rel_cov']) : '6',
        'design_rel_gap' => isset($_POST['design_rel_gap']) ? (string)max(-60, (int)$_POST['design_rel_gap']) : '6',
        'design_rel_gap2' => isset($_POST['design_rel_gap2']) ? (string)max(-60, (int)$_POST['design_rel_gap2']) : '6',
        'design_rel_title' => (string)((int)($_POST['design_rel_title'] ?? 0) ?: 24),
        'design_rel_sub' => (string)((int)($_POST['design_rel_sub'] ?? 0) ?: 19),
        'design_rel_meta' => (string)((int)($_POST['design_rel_meta'] ?? 0) ?: 14),
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

<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('settings.saved')) ?></p><?php endif; ?>

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
    <label for="imprint_url"><?= e(t('settings.imprint')) ?></label>
    <input id="imprint_url" name="imprint_url" placeholder="https://www.decur.io/imprint/" value="<?= e($s['imprint_url'] ?? 'https://www.decur.io/imprint/') ?>">
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
    <p class="small"><a href="library.php?section=music"><?= e(t('albums.music.manage')) ?></a></p>

    <h2 class="design-head"><?= e(t('settings.design')) ?></h2>
    <p class="small"><?= e(t('settings.design_hint')) ?></p>
    <div class="design-grid">
        <div>
            <label for="design_main_title"><?= e(t('settings.design_main_title')) ?></label>
            <input type="number" min="10" max="40" id="design_main_title" name="design_main_title" value="<?= e($s['design_main_title'] ?? '19') ?>">
        </div>
        <div>
            <label for="design_main_meta"><?= e(t('settings.design_main_meta')) ?></label>
            <input type="number" min="8" max="32" id="design_main_meta" name="design_main_meta" value="<?= e($s['design_main_meta'] ?? '15') ?>">
        </div>
        <div>
            <label for="design_flag"><?= e(t('settings.design_flag')) ?></label>
            <input type="number" min="8" max="24" id="design_flag" name="design_flag" value="<?= e($s['design_flag'] ?? '11') ?>">
        </div>
        <div>
            <label for="design_accent"><?= e(t('settings.design_accent')) ?></label>
            <select id="design_accent" name="design_accent">
                <?php foreach (['#f05078' => '#f05078', '#d6b399' => '#d6b399', '#f0f0f0' => '#f0f0f0', '#141414' => '#141414'] as $hex): ?>
                    <option value="<?= $hex ?>" <?= ($s['design_accent'] ?? '#f05078') === $hex ? 'selected' : '' ?>><?= $hex ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="design_rel_top"><?= e(t('settings.design_rel_top')) ?></label>
            <input type="number" min="0" max="120" id="design_rel_top" name="design_rel_top" value="<?= e($s['design_rel_top'] ?? '38') ?>">
        </div>
        <div>
            <label for="design_rel_cov"><?= e(t('settings.design_rel_cov')) ?></label>
            <input type="number" min="0" max="120" id="design_rel_cov" name="design_rel_cov" value="<?= e($s['design_rel_cov'] ?? '6') ?>">
        </div>
        <div>
            <label for="design_rel_gap"><?= e(t('settings.design_rel_gap')) ?></label>
            <input type="number" min="-60" max="80" id="design_rel_gap" name="design_rel_gap" value="<?= e($s['design_rel_gap'] ?? '6') ?>">
        </div>
        <div>
            <label for="design_rel_gap2"><?= e(t('settings.design_rel_gap2')) ?></label>
            <input type="number" min="-60" max="80" id="design_rel_gap2" name="design_rel_gap2" value="<?= e($s['design_rel_gap2'] ?? '6') ?>">
        </div>
        <div>
            <label for="design_rel_title"><?= e(t('settings.design_rel_title')) ?></label>
            <input type="number" min="12" max="56" id="design_rel_title" name="design_rel_title" value="<?= e($s['design_rel_title'] ?? '24') ?>">
        </div>
        <div>
            <label for="design_rel_sub"><?= e(t('settings.design_rel_sub')) ?></label>
            <input type="number" min="10" max="48" id="design_rel_sub" name="design_rel_sub" value="<?= e($s['design_rel_sub'] ?? '19') ?>">
        </div>
        <div>
            <label for="design_rel_meta"><?= e(t('settings.design_rel_meta')) ?></label>
            <input type="number" min="8" max="32" id="design_rel_meta" name="design_rel_meta" value="<?= e($s['design_rel_meta'] ?? '14') ?>">
        </div>
    </div>
    <p class="small"><?= e(t('settings.design_units')) ?></p>

    <p class="form-actions"><button type="submit"><?= e(t('settings.save')) ?></button></p>
</form>
<?php admin_page_close();
