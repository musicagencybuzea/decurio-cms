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
    if ($bg === 'blur') $bg = 'blur2'; // legacy value from v1.2 = medium blur
    // video/Canvas background: resolve the library reference (table may not exist yet)
    $bgVideoPath = '';
    if ($bg === 'video' && !empty($album['video_id'])) {
        try {
            $vq = db()->prepare('SELECT file_path FROM videos WHERE id=?');
            $vq->execute([(int)$album['video_id']]);
            $bgVideoPath = (string)$vq->fetchColumn();
        } catch (Throwable $e) {
            $bgVideoPath = ''; // videos table not migrated yet → fall back to noir
        }
        if ($bgVideoPath === '') { $bg = 'noir'; $album['bg_preset'] = 'noir'; }
    }
    $box = in_array($album['box_color'] ?? 'auto', BOX_COLORS, true) ? $album['box_color'] : 'auto';
    $transparent = !empty($album['box_transparent']);
    $releaseDate = (string)($album['release_date'] ?? '');
    // v1.4 phase 2: per-release link-text colour (empty = automatic) + sticker-aware page spacing
    $inkSetting = trim((string)($album['link_text'] ?? ''));
    $inkClass = $inkSetting === 'clair' ? ' ink-clair' : ($inkSetting === 'fonce' ? ' ink-fonce' : '');
    $stickerClass = $stickers ? ' has-sticker' : '';
    // cover-adapted background (adapt1/2/3): dark base classes so all box/link styles keep working,
    // then a small script recolours the page from the cover's own palette
    $adaptGrade = str_starts_with($bg, 'adapt') ? substr($bg, -1) : '';
    $bodyClass = $adaptGrade !== '' ? 'bg-noir bg-adapt bg-adapt' . $adaptGrade : 'bg-' . $bg;

    http_response_code(200);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
<?php if (!empty($settings['favicon_file'])): ?><link rel="icon" href="<?= e($settings['favicon_file']) ?>"><?php endif; ?>
<title><?= e($album['title']) ?> – decurio</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/release.css')) ?>">
<style><?= design_vars($settings) ?></style>
</head>
<body class="release-page <?= e($bodyClass) ?><?= e($inkClass) ?><?= $stickerClass ?>">
<?php if (str_starts_with($bg, 'blur') && !empty($album['cover_path'])): ?>
<div class="bg-blur-layer blur-g<?= e(substr($bg, -1)) ?>" style="background-image:url('<?= e($album['cover_path']) ?>')"></div>
<?php endif; ?>
<?php if ($bg === 'video' && $bgVideoPath !== ''): ?>
<video class="bg-canvas-video" src="/<?= e($bgVideoPath) ?>" autoplay muted loop playsinline></video>
<?php endif; ?>
<?php if ($preview): ?><div class="preview-banner">PREVIEW</div><?php endif; ?>

<main class="release-wrap">
    <a class="release-brand" href="/">decur.io</a>

    <section class="release-cover">
        <?php if ($bg === 'ambilight' && !empty($album['cover_path'])): ?>
            <img class="ambilight-glow" src="<?= e($album['cover_path']) ?>" alt="" aria-hidden="true">
        <?php endif; ?>
        <?php foreach ($stickers as $sticker): ?>
            <img class="sticker st-<?= e($sticker['size'] ?? '2') ?> pos-<?= e($sticker['position']) ?>" src="<?= e($sticker['image']) ?>" alt="<?= e($sticker['name']) ?>">
        <?php endforeach; ?>
        <?php if (!empty($album['cover_path'])): ?>
            <img class="cover-img" src="<?= e($album['cover_path']) ?>" alt="Cover – <?= e($album['title']) ?>" loading="lazy">
        <?php else: ?>
            <div class="placeholder cover-placeholder"><?= e($album['title']) ?></div>
        <?php endif; ?>
    </section>

    <header class="release-head">
        <h1><?= e($album['title']) ?></h1>
        <?php if (!empty($album['subtitle'])): ?><p class="release-subtitle"><?= e($album['subtitle']) ?></p><?php endif; ?>
        <p class="release-artists"><?= artists_html((string)$album['artists']) ?></p>
        <?php if (!empty($album['is_single']) || $releaseDate !== ''): ?>
        <p class="album-flags centered stacked">
            <?php if (!empty($album['is_single'])): ?><span class="flag">single</span><?php endif; ?>
            <?php if ($releaseDate !== ''): ?><span class="flag flag-out"><?= e(t('albums.field.album_out')) ?> <?= e(album_out_text($releaseDate)) ?></span><?php endif; ?>
        </p>
        <?php endif; ?>
    </header>

    <section class="link-box box-<?= e($box) ?><?= $transparent ? ' box-transparent' : '' ?>" data-album-stats="<?= (int)$album['id'] ?>" data-stats-src="release">
        <?php if ($links): ?>
            <?php $first = true; foreach ($links as $link): $row = link_row_html((array)$link, $first ? 'primary' : ''); $first = false; echo $row; endforeach; ?>
        <?php else: ?>
            <p class="small">Streaming links coming soon.</p>
        <?php endif; ?>
    </section>

    <?php if ($socials || $extraSocials): ?>
    <nav class="socials">
        <?php foreach (['instagram', 'facebook', 'youtube', 'tiktok'] as $key): ?>
            <?php if (!empty($socials[$key])): ?>
            <a href="<?= e($socials[$key]) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($key)) ?>">
                <?= social_icon($key) ?>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($extraSocials as $extra): ?>
            <?php $k = (string)($extra['platform'] ?? ''); if (isset($extra['url']) && $extra['url'] !== ''): ?>
            <a href="<?= e($extra['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($k)) ?>"><?= social_icon($k) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
</main>
<?php if (function_exists('stats_beacon_js')) echo stats_beacon_js(); ?>
<?php if ($adaptGrade !== '' && !empty($album['cover_path'])): ?>
<script>
(function () {
  var cover = document.querySelector('.release-cover img.cover-img');
  if (!cover || !cover.getAttribute('src')) return;
  var img = new Image();
  img.onload = function () {
    try {
      var S = 24;
      var c = document.createElement('canvas');
      c.width = S; c.height = S;
      var ctx = c.getContext('2d');
      ctx.drawImage(img, 0, 0, S, S);
      var d = ctx.getImageData(0, 0, S, S).data;
      var buckets = {};
      for (var i = 0; i < d.length; i += 4) {
        var key = ((d[i] >> 5) << 10) | ((d[i + 1] >> 5) << 5) | (d[i + 2] >> 5);
        var b = buckets[key] || (buckets[key] = { n: 0, r: 0, g: 0, b: 0 });
        b.n++; b.r += d[i]; b.g += d[i + 1]; b.b += d[i + 2];
      }
      var list = [];
      for (var k in buckets) {
        var v = buckets[k];
        list.push({ n: v.n, r: Math.round(v.r / v.n), g: Math.round(v.g / v.n), b: Math.round(v.b / v.n) });
      }
      list.sort(function (a, b2) { return b2.n - a.n; });
      if (!list.length) return;
      var dom = list[0];
      var dist = function (a, b2) {
        var dr = a.r - b2.r, dg = a.g - b2.g, db = a.b - b2.b;
        return Math.sqrt(dr * dr + dg * dg + db * db);
      };
      var far = list[0];
      for (var i2 = 1; i2 < list.length; i2++) if (dist(list[i2], dom) > dist(far, dom)) far = list[i2];
      var mid = null, best = -1;
      for (var j = 1; j < list.length; j++) {
        var score = Math.min(dist(list[j], dom), dist(list[j], far));
        if (score > best && list[j].n >= list[0].n * 0.15) { best = score; mid = list[j]; }
      }
      if (!mid) mid = far;
      var shade = function (c) {
        var lum = 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b;
        if (lum > 130) {
          var f = 130 / lum;
          return { r: Math.round(c.r * f), g: Math.round(c.g * f), b: Math.round(c.b * f) };
        }
        return c;
      };
      var m = document.body.className.match(/bg-adapt(\d)/);
      var pick = m && m[1] === '2' ? mid : (m && m[1] === '3' ? far : dom);
      pick = shade(pick);
      document.body.style.background = 'rgb(' + pick.r + ',' + pick.g + ',' + pick.b + ')';
    } catch (e) { /* canvas not readable (old browser): keep the dark fallback */ }
  };
  img.src = cover.getAttribute('src');
})();
</script>
<?php endif; ?>
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
