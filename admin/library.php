<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

// Library: one tab, three sub-tabs (platforms / stickers / music)
$section = (string)($_GET['section'] ?? 'platforms');
if (!in_array($section, ['platforms', 'stickers', 'music'], true)) $section = 'platforms';

admin_page_open(t('nav.library'), 'library');
?>
<h1><?= e(t('nav.library')) ?></h1>

<div class="subtabs">
    <a class="subtab<?= $section === 'platforms' ? ' active' : '' ?>" href="library.php?section=platforms"><?= e(t('platforms.title')) ?></a>
    <a class="subtab<?= $section === 'stickers' ? ' active' : '' ?>" href="library.php?section=stickers"><?= e(t('stickers.title')) ?></a>
    <a class="subtab<?= $section === 'music' ? ' active' : '' ?>" href="library.php?section=music"><?= e(t('music.title')) ?></a>
</div>
<?php
require __DIR__ . '/_section_' . $section . '.php';
admin_page_close();
