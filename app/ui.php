<?php
declare(strict_types=1);

const BOX_COLORS = ['auto', 'noir', 'gris', 'blanc', 'beige', 'rose'];
const BG_PRESETS = ['noir', 'gris', 'blanc', 'beige', 'rose', 'blur'];
const STICKER_POSITIONS = ['tl', 'tc', 'tr'];

function release_url($album): string
{
    return ($album['release_number'] ?? '') !== '' ? '/' . rawurlencode($album['release_number']) : '';
}

function admin_page_open(string $title, string $active): void
{
    $s = settings();
    $favicon = $s['favicon_file'] ?? '';
    ?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
<?php if (!empty($favicon)): ?><link rel="icon" href="../<?= e($favicon) ?>"><?php endif; ?>
<title><?= e($title) ?> – decurio cms</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="wrap">
<header class="top">
    <a class="brandlink" href="dashboard.php">
        <?php if (!empty($s['logo_file'])): ?>
            <img class="logo" src="../<?= e($s['logo_file']) ?>" alt="decurio">
        <?php else: ?>
            <span class="brand">decurio&nbsp;<span class="accent">cms</span></span>
        <?php endif; ?>
    </a>
    <div class="langs">
        <?php foreach (SUPPORTED_LANGS as $l): ?>
            <a href="<?= lang_url($l) ?>"<?= $l === lang() ? ' class="active"' : '' ?>><?= strtoupper($l) ?></a>
        <?php endforeach; ?>
    </div>
</header>
<nav class="mainnav">
    <a class="tab<?= $active === 'dashboard' ? ' active' : '' ?>" href="dashboard.php"><?= e(t('nav.dashboard')) ?></a>
    <a class="tab<?= $active === 'albums' ? ' active' : '' ?>" href="albums.php"><?= e(t('nav.albums')) ?></a>
    <a class="tab<?= $active === 'platforms' ? ' active' : '' ?>" href="platforms.php"><?= e(t('nav.platforms')) ?></a>
    <a class="tab<?= $active === 'stickers' ? ' active' : '' ?>" href="stickers.php"><?= e(t('nav.stickers')) ?></a>
    <a class="tab<?= $active === 'music' ? ' active' : '' ?>" href="music.php"><?= e(t('nav.music')) ?></a>
    <a class="tab<?= $active === 'settings' ? ' active' : '' ?>" href="settings.php"><?= e(t('nav.settings')) ?></a>
    <a class="tab logout" href="logout.php"><?= e(t('nav.logout')) ?></a>
</nav>
<?php
}

function admin_page_close(): void
{
    ?>
</div>
</body>
</html>
    <?php
}

/** Branded error page for user-side validation failures. */
function fail(string $message): never
{
    http_response_code(422);
    $is_admin = str_contains(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), 'admin');
    $s = settings();
    $favicon = $s['favicon_file'] ?? '';
    $prefix = $is_admin ? '../' : '';
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#141414">
<?php if (!empty($favicon)): ?><link rel="icon" href="<?= e($prefix . $favicon) ?>"><?php endif; ?>
<title>decurio cms</title>
<link rel="stylesheet" href="<?= e($prefix) ?>assets/css/admin.css">
</head>
<body class="login-body">
<div>
    <div class="login-panel">
        <header class="login-head"><span class="brand">decurio&nbsp;<span class="accent">cms</span></span></header>
        <p class="notice error"><?= e($message) ?></p>
        <p class="form-actions"><button onclick="history.back()"><?= e(t('err.back')) ?></button></p>
    </div>
</div>
</body>
</html>
    <?php
    exit;
}

/** Resolve sticker slots into renderable entries. */
function album_stickers(array $album): array
{
    $slots = json_decode($album['sticker_slots_json'] ?? '[]', true) ?: [];
    if (!$slots) return [];
    $ids = array_filter(array_map(fn($x) => (int)($x['sticker_id'] ?? 0), $slots));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT id, name, image_path FROM stickers WHERE id IN ($in)");
    $st->execute(array_values($ids));
    $map = [];
    foreach ($st->fetchAll() as $row) $map[(int)$row['id']] = $row;
    $out = [];
    foreach ($slots as $slot) {
        $r = $map[(int)($slot['sticker_id'] ?? 0)] ?? null;
        if ($r) {
            $pos = in_array($slot['position'] ?? '', STICKER_POSITIONS, true) ? $slot['position'] : 'tl';
            $out[] = ['image' => $r['image_path'], 'name' => $r['name'], 'position' => $pos];
        }
    }
    return $out;
}

/** Reorder helper: fetch all tracks for selects. */
function tracks_for_select(): array
{
    try {
        return db()->query('SELECT id, name FROM tracks ORDER BY name')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
