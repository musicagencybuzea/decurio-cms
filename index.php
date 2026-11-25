<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ui.php';

// Routing: links.decur.io/XXX (.htaccess rewrites to ?num=XXX) + admin preview (?preview=ID)
if (isset($_GET['preview'])) {
    require_login();
    if (logged_in()) {
        $q = db()->prepare('SELECT * FROM albums WHERE id=?');
        $q->execute([(int)$_GET['preview']]);
        $previewAlbum = $q->fetch();
        if ($previewAlbum) {
            require __DIR__ . '/app/release-page.php';
            render_release_page($previewAlbum, true);
        }
    }
    fail('Preview not found.');
}

if (isset($_GET['num'])) {
    $num = trim((string)$_GET['num']);
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
    <article class="album">
        <div class="card">
            <div class="face front">
                <?php foreach ($stickers as $sticker): ?>
                    <img class="sticker pos-<?= e($sticker['position']) ?>" src="<?= e($sticker['image']) ?>" alt="<?= e($sticker['name']) ?>">
                <?php endforeach; ?>
                <?php if ($album['cover_path']): ?>
                    <img src="<?= e($album['cover_path']) ?>" alt="Cover – <?= e($album['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="placeholder"><?= e($album['title']) ?></div>
                <?php endif; ?>
                <div class="hint">listen</div>
            </div>
            <div class="face back">
                <?php foreach ($links as $i => $link): if (!empty($link['url'])): ?>
                    <a class="btn <?= $i === 0 ? 'primary' : '' ?>" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <span><?= e($link['name'] ?? 'Listen') ?></span><span class="arrow">→</span>
                    </a>
                <?php endif; endforeach; ?>
            </div>
        </div>
        <?php if ($pageUrl !== ''): ?><a class="album-meta" href="<?= e($pageUrl) ?>"><?php else: ?><div class="album-meta"><?php endif; ?>
            <h2 class="album-title"><?= e($album['title']) ?></h2>
            <?php if (!empty($album['subtitle'])): ?><p class="album-subtitle"><?= e($album['subtitle']) ?></p><?php endif; ?>
            <p class="album-artists"><?= artists_html((string)$album['artists']) ?></p>
            <?php if (!empty($album['is_single'])): ?><p class="album-single">single</p><?php endif; ?>
        <?php if ($pageUrl !== ''): ?></a><?php else: ?></div><?php endif; ?>
    </article>
<?php endforeach; ?>
</section>

<footer><a href="https://decur.io">decur.io</a> · <?= e($settings['footer_text'] ?? 'we record, we publish, we discover') ?></footer>

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
</body>
</html>
