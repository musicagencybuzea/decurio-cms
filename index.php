<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ui.php';

// Routing: links.decur.io/XXX (or XXXA for a linked release) + admin live preview (?preview)
if (isset($_GET['preview'])) {
    header('Cache-Control: no-store, max-age=0'); // preview must never be served from a cache
    if (logged_in() && !empty($_SESSION['preview_snapshot'])) {
        $snap = $_SESSION['preview_snapshot'];
        $previewAlbum = null;
        if (!empty($snap['id'])) {
            $q = db()->prepare('SELECT * FROM albums WHERE id=?');
            $q->execute([(int)$snap['id']]);
            $previewAlbum = $q->fetch();
        }
        // Live preview: the form state as it is on screen right now, saved or not
        $fields = ['title', 'subtitle', 'artists', 'release_number', 'links_json', 'socials_json',
                   'extra_socials_json', 'sticker_slots_json', 'bg_preset', 'video_id', 'box_color', 'box_transparent',
                   'link_text', 'release_date', 'status', 'publish_at'];
        if (!$previewAlbum && !empty($snap['title'])) {
            $previewAlbum = array_fill_keys([
                'id', 'title', 'subtitle', 'is_single', 'release_number', 'artists', 'cover_path', 'links_json',
                'socials_json', 'extra_socials_json', 'sticker_slots_json', 'bg_preset', 'video_id', 'box_color',
                'box_transparent', 'link_text', 'music_id', 'publish_at', 'status', 'sort_order', 'created_at', 'updated_at', 'release_date'], '');
            $previewAlbum['id'] = 0;
            $previewAlbum['is_single'] = 0;
            $previewAlbum['box_transparent'] = 0;
            $previewAlbum['music_id'] = null;
        }
        if ($previewAlbum) {
            foreach ($fields as $f) {
                if (array_key_exists($f, $snap)) $previewAlbum[$f] = $snap[$f];
            }
            if (!empty($snap['cover_path'])) $previewAlbum['cover_path'] = $snap['cover_path']; // freshly chosen cover
            $previewAlbum['status'] = 'published'; // preview always renders like a live page
            require __DIR__ . '/app/release-page.php';
            render_release_page($previewAlbum, true);
        }
    }
    fail('Preview not found.');
}

if (isset($_GET['num'])) {
    $num = strtoupper(trim((string)$_GET['num']));
    $q = db()->prepare('SELECT * FROM albums WHERE release_number=?');
    $q->execute([$num]);
    $album = $q->fetch();
    $visible = $album && (
        $album['status'] === 'published'
        || ($album['status'] === 'scheduled' && $album['publish_at'] !== null && strtotime((string)$album['publish_at']) <= time())
    );
    if ($visible) {
        require __DIR__ . '/app/release-page.php';
        render_release_page($album, false);
    }
    http_response_code(404);
    fail('This page does not exist.');
}

// Timezone-safe comparison: publish_at is stored in Europe/Berlin, computed in PHP
// (the MySQL server time zone is unknown on shared hosting, so avoid NOW()).
$nowTs = date('Y-m-d H:i:s');
$albumsStmt = db()->prepare(
    "SELECT * FROM albums WHERE status='published' OR (status='scheduled' AND publish_at<=?) ORDER BY sort_order ASC, created_at DESC"
);
$albumsStmt->execute([$nowTs]);
$albums = $albumsStmt->fetchAll();
$settings = settings();
$welcomeTrack = null;
if (!empty($settings['welcome_track_id'])) {
    $q = db()->prepare('SELECT id, name, file_path FROM tracks WHERE id=?');
    $q->execute([(int)$settings['welcome_track_id']]);
    $welcomeTrack = $q->fetch() ?: null;
}
$favicon = $settings['favicon_file'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
<?php if (!empty($favicon)): ?><link rel="icon" href="<?= e($favicon) ?>"><?php endif; ?>
<title><?= e($settings['site_title'] ?? 'decurio – records & links') ?></title>
<link rel="stylesheet" href="assets/css/site.css">
<style><?= design_vars($settings) ?></style>
</head>
<body>
<main class="page">

<header>
    <a class="logo-link" href="<?= e($settings['logo_url'] ?? 'https://decur.io') ?>" target="_blank" rel="noopener">
        <?php if (!empty($settings['logo_file'])): ?>
            <img class="logo-img" src="<?= e($settings['logo_file']) ?>" alt="decurio">
        <?php else: ?>
            <div class="logo-text">decur<span>i</span>o</div>
        <?php endif; ?>
    </a>
    <div class="tagline"><?= e($settings['tagline'] ?? 'record label') ?></div>
    <div class="filet"></div>
</header>

<section class="grid">
<?php foreach ($albums as $album):
    $links = json_decode($album['links_json'] ?: '[]', true) ?: [];
    $stickers = album_stickers($album);
    $pageUrl = release_url($album);
    ?>
    <article class="album" data-album-stats="<?= (int)$album['id'] ?>" data-stats-src="main">
        <div class="card">
            <div class="face front">
                <?php foreach ($stickers as $sticker): ?>
                    <img class="sticker st-<?= e($sticker['size'] ?? '2') ?> pos-<?= e($sticker['position']) ?>" src="<?= e($sticker['image']) ?>" alt="<?= e($sticker['name']) ?>">
                <?php endforeach; ?>
                <?php if ($album['cover_path']): ?>
                    <img class="cover-img" src="<?= e($album['cover_path']) ?>" alt="Cover – <?= e($album['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="placeholder"><?= e($album['title']) ?></div>
                <?php endif; ?>
                <div class="hint">listen</div>
            </div>
            <?php $linkCount = count($links); ?><div class="face back<?= $linkCount > 6 ? ' links-lots' : ($linkCount > 4 ? ' links-many' : '') ?>">
                <?php foreach ($links as $i => $link): ?>
                    <?= link_row_html($link, $i === 0 ? 'primary' : '') ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($pageUrl !== ''): ?><a class="album-meta" href="<?= e($pageUrl) ?>"><?php else: ?><div class="album-meta"><?php endif; ?>
            <h2 class="album-title"><?= e($album['title']) ?></h2>
            <?php if (!empty($album['subtitle'])): ?><p class="album-subtitle"><?= e($album['subtitle']) ?></p><?php endif; ?>
            <p class="album-artists"><?= artists_html((string)$album['artists']) ?></p>
            <?php if (!empty($album['is_single']) || !empty($album['release_date'])): ?>
            <p class="album-flags stacked">
                <?php if (!empty($album['is_single'])): ?><span class="flag">single</span><?php endif; ?>
                <?php if (!empty($album['release_date'])): ?><span class="flag flag-out"><?= e(t('albums.field.album_out')) ?> <?= e(album_out_text($album['release_date'])) ?></span><?php endif; ?>
            </p>
            <?php endif; ?>
        <?php if ($pageUrl !== ''): ?></a><?php else: ?></div><?php endif; ?>
    </article>
<?php endforeach; ?>
</section>

<footer><a href="https://decur.io">decur.io</a> · <?= e($settings['footer_text'] ?? 'we record, we publish, we discover') ?>
<?php $imprintUrl = trim((string)($settings['imprint_url'] ?? '')); if ($imprintUrl !== ''): ?>
    · <a class="imprint" href="<?= e($imprintUrl) ?>" target="_blank" rel="noopener"><?= e(t('site.imprint')) ?></a>
<?php endif; ?></footer>

<?php if ($welcomeTrack): ?>
<div class="welcome-player" id="welcome-player"
     data-src="<?= e($welcomeTrack['file_path']) ?>"
     data-label="<?= e($welcomeTrack['name']) ?>">
    <button type="button" class="welcome-toggle" aria-label="Play or stop">▶</button>
    <div class="welcome-scroll"><span><?= e($welcomeTrack['name']) ?></span></div>
</div>
<?php endif; ?>

</main>
<script>
// Flip cards
document.querySelectorAll('.card').forEach(c => c.addEventListener('click', e => {
    if (e.target.closest('a')) return;
    c.classList.toggle('flipped');
}));

// Shared snippet player: 30 s max, 1.5 s fade-out
function decurioSnippet(audio, button) {
    let timer = null, fader = null, endedBound = false;
    function stop() {
        if (fader) { clearInterval(fader); fader = null; }
        audio.pause();
        try { audio.currentTime = 0; } catch (err) {}
        audio.volume = 1;
        if (button) button.textContent = '▶';
    }
    return function toggle() {
        if (!audio.paused) { stop(); return; }
        if (timer) clearTimeout(timer);
        audio.volume = 1;
        if (!endedBound) { audio.addEventListener('ended', stop); endedBound = true; }
        audio.play().then(() => {
            if (button) button.textContent = '❚❚';
            const startedAt = Date.now();
            timer = setTimeout(() => {
                fader = setInterval(() => {
                    const left = startedAt + 31500 - Date.now();
                    if (left <= 0) { stop(); return; }
                    audio.volume = Math.max(0, Math.min(1, left / 1500));
                }, 50);
            }, 28500);
        }).catch(() => {
            if (button) button.textContent = '▶';
        });
    };
}

const welcomeP = document.getElementById('welcome-player');
if (welcomeP) {
    const audio = new Audio(welcomeP.dataset.src);
    const btn = welcomeP.querySelector('.welcome-toggle');
    btn.addEventListener('click', decurioSnippet(audio, btn));
}

// Release page cover player
const coverWrap = document.getElementById('cover-toggle-wrap');
if (coverWrap) {
    const audio = new Audio(coverWrap.dataset.src);
    const btn = coverWrap.querySelector('.cover-toggle');
    coverWrap.addEventListener('click', decurioSnippet(audio, btn));
}
</script>
<?php if (function_exists('stats_beacon_js')) echo stats_beacon_js(); ?>
</body>
</html>
