<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$albums = db()->query("SELECT * FROM albums WHERE status='published' ORDER BY sort_order ASC, created_at DESC")->fetchAll();
$settings = settings();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
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
    $links = json_decode($album['links_json'] ?: '[]', true) ?: []; ?>
    <article class="album">
        <div class="card">
            <div class="face front">
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
        <h2 class="album-title"><?= e($album['title']) ?></h2>
        <p class="album-artists"><?= e($album['artists']) ?></p>
    </article>
<?php endforeach; ?>
</section>

<footer><a href="https://decur.io">decur.io</a> · <?= e($settings['footer_text'] ?? 'we record, we publish, we discover') ?></footer>

</main>
<script>
document.querySelectorAll('.card').forEach(c => c.addEventListener('click', e => {
    if (e.target.closest('a')) return;
    c.classList.toggle('flipped');
}));
</script>
</body>
</html>
