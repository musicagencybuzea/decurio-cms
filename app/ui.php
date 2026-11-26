<?php
declare(strict_types=1);

const BOX_COLORS = ['auto', 'noir', 'gris', 'blanc', 'beige', 'rose'];
const BG_PRESETS = ['noir', 'gris', 'blanc', 'beige', 'rose', 'blur1', 'blur2', 'blur3', 'ambilight', 'video', 'adapt1', 'adapt2', 'adapt3'];
const STICKER_POSITIONS = ['tl', 'tc', 'tr'];
const LINK_ACTIONS = ['play', 'buy', 'download', 'visit', 'listen', 'learn', 'subscribe', 'join', 'support'];

function release_url($album): string
{
    return ($album['release_number'] ?? '') !== '' ? '/' . rawurlencode($album['release_number']) : '';
}

/** Translated month names for the "ALBUM OUT" mention (no IntlDateFormatter on shared hosting). */
const MONTH_NAMES = [
    'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
    'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
];

/** Format YYYY-MM-DD as "24 April 2026" — month name always in English (public pages). */
function album_out_text(?string $ymd): string
{
    if (!$ymd || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) return '';
    $day = (int)$m[3];
    return $day . ' ' . MONTH_NAMES['en'][(int)$m[2] - 1] . ' ' . $m[1];
}

/** Neutral monochrome platform logo (inline SVG, currentColor). Returns '' for unknown platforms. */
function platform_logo(string $name): string
{
    $n = strtolower(trim((string)preg_replace('/[^\p{L}\p{N}]+/u', '', $name)));
    switch (true) {
        case str_contains($n, 'spotify'):
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M7.2 9.6c3.4-1 7-.55 9.6.95M7.5 12.6c2.8-.8 5.7-.4 7.9.85M7.9 15.4c2.2-.6 4.5-.3 6.2.7"/></svg>';
        case str_contains($n, 'apple') || str_contains($n, 'itunes'):
            return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.6 4.9c.7-.9 1.2-2 1-3.2-1 .1-2.2.7-2.9 1.6-.7.8-1.2 2-1 3.1 1.1 0 2.2-.6 2.9-1.5zM17.7 12.2c0-2.5 2-3.7 2.1-3.8-1.2-1.7-2.9-1.9-3.5-2-1.5-.1-2.9.9-3.7.9-.8 0-1.9-.9-3.2-.8-1.6 0-3.1 1-4 2.4-1.7 2.9-.4 7.2 1.2 9.6.8 1.1 1.7 2.4 3 2.4 1.2 0 1.7-.8 3.2-.8s1.9.8 3.2.8c1.3 0 2.2-1.2 3-2.3.9-1.4 1.3-2.7 1.3-2.8-.1 0-2.6-1-2.6-3.6z"/></svg>';
        case str_contains($n, 'deezer'):
            return '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="2" y="5" width="3.4" height="3.4"/><rect x="7.5" y="5" width="3.4" height="3.4"/><rect x="13" y="5" width="3.4" height="3.4"/><rect x="18.5" y="5" width="3.4" height="3.4"/><rect x="7.5" y="10.3" width="3.4" height="3.4"/><rect x="13" y="10.3" width="3.4" height="3.4"/><rect x="18.5" y="10.3" width="3.4" height="3.4"/><rect x="13" y="15.6" width="3.4" height="3.4"/><rect x="18.5" y="15.6" width="3.4" height="3.4"/></svg>';
        case str_contains($n, 'youtube'):
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M10 8.6l6 3.4-6 3.4z" fill="currentColor" stroke="none"/></svg>';
        case str_contains($n, 'tidal'):
            return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 6.2l3 3-3 3-3-3zM6 3.2l3 3-3 3-3-3zM18 3.2l3 3-3 3-3-3zM12 12.2l3 3-3 3-3-3z"/></svg>';
        case str_contains($n, 'amazon'):
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="9.5" r="5.5"/><path d="M12 7.2v4.6l2.6 1.6"/><path d="M4.5 18.2c2.2 1.5 4.8 2.3 7.5 2.3s5.3-.8 7.5-2.3M17.5 17l2-1-.5 2.2"/></svg>';
        case str_contains($n, 'soundcloud'):
            return '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="2" y="10" width="1.5" height="5.5"/><rect x="4.4" y="8.4" width="1.5" height="7.1"/><rect x="6.8" y="6.8" width="1.5" height="8.7"/><path d="M11 16.7V7.9c.4-.5 1-.7 1.6-.7a5.6 5.6 0 0 1 4.6 2.4c.6-.2 1.3-.2 1.9 0a3.6 3.6 0 0 1 2.7 3.5 3.6 3.6 0 0 1-3.6 3.6z"/></svg>';
        case str_contains($n, 'bandcamp'):
            return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.3 5.5H2.5l6.2 13H21.5z"/></svg>';
        case str_contains($n, 'qobuz'):
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7"/><path d="M15.5 15.5L21 21"/></svg>';
        case str_contains($n, 'beatport'):
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="13.5" r="5.5"/><path d="M12 8V3h6.5"/></svg>';
        case str_contains($n, 'napster'):
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="7.5" cy="14" r="5.5"/><circle cx="16.5" cy="14" r="5.5"/></svg>';
    }
    return '';
}

/** Translated + whitelisted label for a link action button. */
function action_label(string $action): string
{
    if (in_array($action, LINK_ACTIONS, true)) return mb_strtoupper(t('action.' . $action));
    if ($action !== '') return mb_strtoupper(mb_substr($action, 0, 12));
    return mb_strtoupper(t('action.play'));
}

/** Store an uploaded video/Canvas file into the video library; returns its id (or null). */
function save_video_upload(): ?int
{
    if (empty($_FILES['bg_video']['name']) || ($_FILES['bg_video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $f = $_FILES['bg_video'];
    if ($f['size'] > 48 * 1024 * 1024) fail(t('videos.err.too_large'));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    if (!isset($allowed[$mime])) fail(t('videos.err.video'));
    $dir = ROOT . '/uploads/videos';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) fail(t('videos.err.save'));
    $name = trim((string)($_POST['bg_video_name'] ?? ''));
    if ($name === '') $name = 'Canvas ' . date('Y-m-d H:i');
    db()->prepare('INSERT INTO videos(name,file_path) VALUES(?,?)')->execute([$name, 'uploads/videos/' . $filename]);
    return (int)db()->lastInsertId();
}

/** Auto-add a saved cover to the cover library (idempotent: same file is not added twice). */
function register_cover(string $path, string $title): void
{
    if ($path === '' || str_starts_with(basename($path), 'preview_')) return;
    try {
        $st = db()->prepare('SELECT id FROM covers WHERE file_path=?');
        $st->execute([$path]);
        if ($st->fetch()) return;
        $name = trim($title) !== '' ? trim($title) : 'Cover ' . date('Y-m-d H:i');
        db()->prepare('INSERT INTO covers(name,file_path) VALUES(?,?)')->execute([$name, $path]);
    } catch (Throwable $e) {
        // covers table not migrated yet — silently skip
    }
}

/** Shared public link row: platform logo (or name) left, fixed-size action button right. */
function link_row_html(array $link, string $classes = ''): string
{
    $url = (string)($link['url'] ?? '');
    if ($url === '') return '';
    $name = trim((string)($link['name'] ?? '')) ?: 'Listen';
    $logo = platform_logo($name);
    $left = $logo !== ''
        ? '<span class="pl-logo">' . $logo . '</span><span class="pl-name">' . e($name) . '</span>'
        : '<span class="pl-name">' . e($name) . '</span>';
    $action = action_label((string)($link['action'] ?? ''));
    $rawAction = trim((string)($link['action'] ?? '')) ?: 'play';
    return '<div class="rlink ' . e($classes) . '">' . $left
        . '<a class="act" href="' . e($url) . '" target="_blank" rel="noopener noreferrer"'
        . ' data-platform="' . e($name) . '" data-action="' . e($rawAction) . '">' . e($action) . '</a></div>';
}

/** Shared click-tracking script (statistics). Delegated listener + sendBeacon so navigation is never blocked. */
function stats_beacon_js(): string
{
    return <<<'HTML'
<script>
(function () {
    function track(albumEl, src, act) {
        var box = act.closest('[data-album-stats]');
        if (!box || !albumEl) return;
        var acts = Array.prototype.slice.call(box.querySelectorAll('.rlink a.act'));
        var pos = acts.indexOf(act) + 1;
        var url = '/click.php?album=' + albumEl + '&src=' + src
            + '&platform=' + encodeURIComponent(act.dataset.platform || 'unknown')
            + '&action=' + encodeURIComponent(act.dataset.action || 'play')
            + '&pos=' + (pos > 0 ? pos : 1);
        if (navigator.sendBeacon) navigator.sendBeacon(url);
        else fetch(url, { method: 'POST', keepalive: true }).catch(function () {});
    }
    document.addEventListener('click', function (ev) {
        var act = ev.target.closest ? ev.target.closest('a.act') : null;
        if (!act) return;
        var box = act.closest('[data-album-stats]');
        if (!box) return;
        track(box.getAttribute('data-album-stats'), box.getAttribute('data-stats-src') || 'main', act);
    }, true);
})();
</script>
HTML;
}

/** Design adjustments from Settings, injected as CSS variables (site owner can tweak without a new version). */
function design_vars(array $s): string
{
    $num = static function (string $k, int $d): int { return ($v = (int)round((float)($s[$k] ?? $d))) > 0 ? $v : $d; };
    // like $num, but 0 is a valid choice (used for the release-page spacings)
    $num0 = static function (string $k, int $d) use ($s): int {
        if (!isset($s[$k]) || trim((string)$s[$k]) === '') return $d;
        return max(0, (int)round((float)$s[$k]));
    };
    // like $num0, but negative values down to -60 are allowed: the blocks can overlap
    // (e.g. pull the link box up over the remaining cushion below the titles)
    $numNeg = static function (string $k, int $d) use ($s): int {
        if (!isset($s[$k]) || trim((string)$s[$k]) === '') return $d;
        return max(-60, (int)round((float)$s[$k]));
    };
    $accent = in_array($s['design_accent'] ?? '', ['#141414', '#f0f0f0', '#d6b399', '#f05078'], true) ? $s['design_accent'] : '#f05078';
    $title = $num('design_main_title', 19);
    $meta = $num('design_main_meta', 15);
    $flag = $num('design_flag', 11);
    $relTop = $num0('design_rel_top', 38);
    $relCov = $num0('design_rel_cov', 6);      // label link <-> cover (sticker releases: raise it)
    $relGap = $numNeg('design_rel_gap', 6);    // cover <-> titles (can overlap)
    $relGap2 = $numNeg('design_rel_gap2', 6);  // titles <-> link box (can overlap)
    $relTitle = $num('design_rel_title', 24);
    $relSub = $num('design_rel_sub', 19);
    $relMeta = $num('design_rel_meta', 14);
    return ":root{--mp-title:{$title}px;--mp-meta:{$meta}px;--mp-flag:{$flag}px;--accent:{$accent};"
        . "--rel-top:{$relTop}px;--rel-cov:{$relCov}px;--rel-gap:{$relGap}px;--rel-gap2:{$relGap2}px;--rel-title:{$relTitle}px;--rel-sub:{$relSub}px;--rel-meta:{$relMeta}px;}";
}

/** Validate the catalogue number: 007 (main release) or 007A (linked release, letter suffix). */
function clean_release_number(string $raw): string
{
    if (!preg_match('/^(\d{1,4})([A-Za-z])?$/', $raw, $m)) return '';
    $number = str_pad($m[1], 3, '0', STR_PAD_LEFT);
    return isset($m[2]) ? $number . strtoupper($m[2]) : $number;
}

/** Collect the album fields from a POST — shared by the save action and the live preview. */
function collect_album_post(bool $forPreview): array
{
    $out = [];
    $out['title'] = trim($_POST['title'] ?? '');
    if ($out['title'] === '' && !$forPreview) fail(t('err.title_required'));
    $out['subtitle'] = trim($_POST['subtitle'] ?? '');
    $out['artists'] = trim($_POST['artists'] ?? '');
    $out['is_single'] = isset($_POST['is_single']) ? 1 : 0;

    $rawNumber = preg_replace('/\s+/', '', (string)($_POST['release_number'] ?? ''));
    $releaseNumber = $rawNumber !== '' ? clean_release_number($rawNumber) : '';
    if ($rawNumber !== '' && $releaseNumber === '' && !$forPreview) fail(t('err.number_invalid'));
    $out['release_number'] = $releaseNumber;

    $rd = trim((string)($_POST['release_date'] ?? ''));
    $out['release_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rd) ? $rd : '';

    // Links: one unified draggable list, with an optional action word per row
    $links = [];
    $names = $_POST['link_name'] ?? [];
    $urls = $_POST['link_url'] ?? [];
    $actions = $_POST['link_action'] ?? [];
    foreach ($names as $i => $name) {
        $url = trim((string)($urls[$i] ?? ''));
        $name = trim((string)$name);
        if ($name === '' && !filter_var($url, FILTER_VALIDATE_URL)) continue;
        $action = trim((string)($actions[$i] ?? ''));
        if ($action === 'custom') $action = trim((string)($_POST['link_action_custom'][$i] ?? ''));
        if ($action === '') $action = 'play'; // default action
        if (!in_array($action, LINK_ACTIONS, true) && mb_strlen($action) > 12) $action = mb_substr($action, 0, 12);
        $links[] = ['name' => $name !== '' ? $name : 'Listen', 'url' => filter_var($url, FILTER_VALIDATE_URL) ? $url : '', 'action' => $action];
    }
    $links = array_values(array_filter($links, fn($l) => $l['url'] !== ''));
    $out['links'] = $links;

    // Socials: four fixed accounts + extra accounts (two artists, two Instagram accounts, ...)
    $socials = [];
    foreach (['instagram', 'facebook', 'tiktok', 'youtube'] as $key) {
        $u = trim((string)($_POST['socials'][$key] ?? ''));
        if ($u !== '' && filter_var($u, FILTER_VALIDATE_URL)) $socials[$key] = $u;
    }
    $out['socials'] = $socials;
    $extras = [];
    $exPlatforms = $_POST['extra_platform'] ?? [];
    $exUrls = $_POST['extra_url'] ?? [];
    foreach ($exPlatforms as $i => $platform) {
        $platform = (string)$platform;
        $u = trim((string)($exUrls[$i] ?? ''));
        if (in_array($platform, ['instagram', 'facebook', 'tiktok', 'youtube'], true)
            && $u !== '' && filter_var($u, FILTER_VALIDATE_URL)) {
            $extras[] = ['platform' => $platform, 'url' => $u];
        }
    }
    $out['extras'] = $extras;

    // Stickers: up to 3, position + size
    $stickersOn = isset($_POST['stickers_enable']);
    $slots = [];
    if ($stickersOn) {
        $used = [];
        for ($i = 1; $i <= 3; $i++) {
            $sid = (int)($_POST['sticker_' . $i] ?? 0);
            if ($sid <= 0) continue;
            $exists = db()->prepare('SELECT id FROM stickers WHERE id=?');
            $exists->execute([$sid]);
            if (!$exists->fetch()) continue;
            $pos = (string)($_POST['sticker_pos_' . $i] ?? 'tl');
            if (!in_array($pos, STICKER_POSITIONS, true) || in_array($pos, $used, true)) {
                foreach (STICKER_POSITIONS as $p2) {
                    if (!in_array($p2, $used, true)) { $pos = $p2; break; }
                }
            }
            $used[] = $pos;
            $size = (string)($_POST['sticker_size_' . $i] ?? '2');
            if (!in_array($size, ['1', '2', '3'], true)) $size = '2';
            $slots[] = ['sticker_id' => $sid, 'position' => $pos, 'size' => $size];
        }
    }
    $out['stickers_on'] = $stickersOn && $slots;
    $out['slots'] = $slots;

    $out['bg'] = in_array($_POST['bg_preset'] ?? 'noir', BG_PRESETS, true) ? $_POST['bg_preset'] : 'noir';
    // video/Canvas background: library reference (validated); a freshly uploaded file overrides it
    $out['video_id'] = null;
    if ($out['bg'] === 'video') {
        $vid = (int)($_POST['video_id'] ?? 0);
        if ($vid > 0) {
            try {
                $ex = db()->prepare('SELECT id FROM videos WHERE id=?');
                $ex->execute([$vid]);
                if ($ex->fetch()) $out['video_id'] = $vid;
            } catch (Throwable $e2) {
                $out['video_id'] = null; // videos table not migrated yet
            }
        }
    }
    $out['box_color'] = in_array($_POST['box_color'] ?? 'auto', BOX_COLORS, true) ? $_POST['box_color'] : 'auto';
    $out['box_transparent'] = isset($_POST['box_transparent']) ? 1 : 0;
    // link text colour for this release's link box on the exclusive page ("" = automatic)
    $lt = trim((string)($_POST['link_text'] ?? ''));
    $out['link_text'] = in_array($lt, ['clair', 'fonce'], true) ? $lt : '';
    return $out;
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
<link rel="stylesheet" href="<?= e(asset_url('assets/css/admin.css', '../')) ?>">
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
    <a class="tab<?= $active === 'releases' ? ' active' : '' ?>" href="releases.php"><?= e(t('nav.releases')) ?></a>
    <a class="tab<?= $active === 'stats' ? ' active' : '' ?>" href="stats.php"><?= e(t('nav.stats')) ?></a>
    <a class="tab<?= $active === 'library' ? ' active' : '' ?>" href="library.php"><?= e(t('nav.library')) ?></a>
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
<link rel="stylesheet" href="<?= e(asset_url('assets/css/admin.css', $prefix)) ?>">
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
            $size = in_array($slot['size'] ?? '', ['1', '2', '3', 's', 'm', 'l'], true) ? (string)$slot['size'] : '2';
            $out[] = ['image' => $r['image_path'], 'name' => $r['name'], 'position' => $pos, 'size' => $size];
        }
    }
    return $out;
}

/** Render "Artist A, Artist B" with per-name non-breaking wraps (line breaks only after commas). */
function artists_html(string $artists): string
{
    $names = array_values(array_filter(array_map('trim', explode(',', $artists)), fn($a) => $a !== ''));
    return implode(', ', array_map(fn($a) => '<span class="artist">' . e($a) . '</span>', $names));
}

/** Cache-busting asset URL: /assets/css/site.css?v=<file mtime> — every FileZilla upload changes
 *  the mtime, so browsers and phones fetch the fresh file instead of their cached copy. */
function asset_url(string $path, string $prefix = ''): string
{
    $abs = ROOT . '/' . ltrim($path, '/');
    $v = is_file($abs) ? (string)filemtime($abs) : '1';
    return $prefix . ltrim($path, '/') . '?v=' . $v;
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
