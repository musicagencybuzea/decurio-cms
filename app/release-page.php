<?php
declare(strict_types=1);

/**
 * Exclusive per-release page (links.decur.io/XXX or /XXXA).
 * Renders and exits. $album must be a full albums row, $preview = admin preview mode.
 */
function render_release_page(array $album, bool $preview): never
{
    global $settings;
    if (!isset($settings)) $settings = settings();

    $links = json_decode($album['links_json'] ?: '[]', true) ?: [];
    $socials = json_decode($album['socials_json'] ?: '[]', true) ?: [];
    $extraSocials = json_decode($album['extra_socials_json'] ?: '[]', true) ?: [];
    $stickers = album_stickers($album);
    $bg = in_array($album['bg_preset'] ?? 'noir', BG_PRESETS, true) ? $album['bg_preset'] : 'noir';
    $box = in_array($album['box_color'] ?? 'auto', BOX_COLORS, true) ? $album['box_color'] : 'auto';
    $transparent = !empty($album['box_transparent']);
    $releaseDate = (string)($album['release_date'] ?? '');

    $baseIcons = ['instagram', 'facebook', 'youtube', 'tiktok']; // screen corners: top-left, bottom-left, top-right, bottom-right
    $cornerSocials = [];
    foreach ($baseIcons as $key) {
        if (!empty($socials[$key])) $cornerSocials[$key] = $socials[$key];
    }

    http_response_code(200);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
<?php if (!empty($settings['favicon_file'])): ?><link rel="icon" href="<?= e($settings['favicon_file']) ?>"><?php endif; ?>
<title><?= e($album['title']) ?> – decurio</title>
<link rel="stylesheet" href="/assets/css/site.css">
<link rel="stylesheet" href="/assets/css/release.css">
<style><?= design_vars($settings) ?></style>
</head>
<body class="release-page bg-<?= e($bg) ?>">
<?php if ($bg === 'blur' && !empty($album['cover_path'])): ?>
<div class="bg-blur-layer" style="background-image:url('<?= e($album['cover_path']) ?>')"></div>
<?php endif; ?>
<?php if ($preview): ?><div class="preview-banner">PREVIEW</div><?php endif; ?>

<nav class="corner-socials">
<?php foreach ($cornerSocials as $key => $url): ?>
    <a class="corner-social cs-<?= $key === 'instagram' ? 'tl' : ($key === 'facebook' ? 'bl' : ($key === 'youtube' ? 'tr' : 'br')) ?>"
       href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($key)) ?>"><?= social_icon($key) ?></a>
<?php endforeach; ?>
</nav>

<main class="release-wrap">
    <a class="release-brand" href="/">decur.io</a>

    <section class="release-cover">
        <?php foreach ($stickers as $sticker): ?>
            <img class="sticker st-<?= e($sticker['size'] ?? '2') ?> pos-<?= e($sticker['position']) ?>" src="<?= e($sticker['image']) ?>" alt="<?= e($sticker['name']) ?>">
        <?php endforeach; ?>
        <?php if (!empty($album['cover_path'])): ?>
            <img src="<?= e($album['cover_path']) ?>" alt="Cover – <?= e($album['title']) ?>">
        <?php else: ?>
            <div class="placeholder cover-placeholder"><?= e($album['title']) ?></div>
        <?php endif; ?>
    </section>

    <header class="release-head">
        <h1><?= e($album['title']) ?></h1>
        <?php if (!empty($album['subtitle'])): ?><p class="release-subtitle"><?= e($album['subtitle']) ?></p><?php endif; ?>
        <p class="release-artists"><?= artists_html((string)$album['artists']) ?></p>
        <?php if (!empty($album['is_single']) || $releaseDate !== ''): ?>
        <p class="album-flags centered">
            <?php if (!empty($album['is_single'])): ?><span class="flag">single</span><?php endif; ?>
            <?php if ($releaseDate !== ''): ?><span class="flag flag-out"><?= e(t('albums.field.album_out')) ?> <?= e(album_out_text($releaseDate)) ?></span><?php endif; ?>
        </p>
        <?php endif; ?>
    </header>

    <section class="link-box box-<?= e($box) ?><?= $transparent ? ' box-transparent' : '' ?>">
        <?php if ($links): ?>
            <?php $first = true; foreach ($links as $link): $row = link_row_html((array)$link, $first ? 'primary' : ''); $first = false; echo $row; endforeach; ?>
        <?php else: ?>
            <p class="small">Streaming links coming soon.</p>
        <?php endif; ?>
    </section>

    <?php if ($extraSocials): ?>
    <nav class="socials">
        <?php foreach ($extraSocials as $extra): ?>
            <?php $k = (string)($extra['platform'] ?? ''); if (isset($extra['url']) && $extra['url'] !== ''): ?>
            <a href="<?= e($extra['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($k)) ?>"><?= social_icon($k) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
</main>
</body>
</html>
    <?php
    exit;
}

/** Monochrome inline SVG icons (single colour, follows the brand social-media usage). */
function social_icon(string $key): string
{
    switch ($key) {
        case 'instagram':
            return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>';
        case 'facebook':
            return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15.5 3.5h-2.2c-2 0-3.3 1.3-3.3 3.4v2.4H7.6v3h2.4v8.2h3.1v-8.2h2.6l.5-3h-3.1V7.2c0-.6.3-.9.9-.9h2.2z"/></svg>';
        case 'tiktok':
            return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13.5 3v10.6a3.4 3.4 0 1 1-3.4-3.4M13.5 3c.3 2.6 2 4.6 4.9 5"/></svg>';
        case 'youtube':
            return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="6" width="19" height="12" rx="4"/><path d="M10.2 9.5l5 2.5-5 2.5z" fill="currentColor" stroke="none"/></svg>';
    }
    return '';
}
