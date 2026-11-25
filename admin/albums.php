<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

function save_uploaded_image(array $f, int $maxBytes = 8388608): string
{
    if ($f['error'] !== UPLOAD_ERR_OK) fail(t('err.upload'));
    if ($f['size'] > $maxBytes) fail(t('err.too_large'));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) fail(t('err.format'));
    $dir = ROOT . '/uploads/albums';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) fail(t('err.save_cover'));
    return 'uploads/albums/' . $filename;
}

/** Download a remote image (Spotify CDN) into uploads/albums. Returns null if not possible. */
function fetch_remote_image(string $url): ?string
{
    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => 1, 'max_redirects' => 3]]);
        $raw = @file_get_contents($url, false, $ctx);
    }
    if (!is_string($raw) || $raw === '' || strlen($raw) > 8 * 1024 * 1024) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($raw);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) return null;
    $dir = ROOT . '/uploads/albums';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (file_put_contents($dir . '/' . $filename, $raw) === false) return null;
    return 'uploads/albums/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    if ($title === '') fail(t('err.title_required'));
    $artists = trim($_POST['artists'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $isSingle = isset($_POST['is_single']) ? 1 : 0;

    $releaseNumber = '';
    $rawNumber = preg_replace('/\s+/', '', (string)($_POST['release_number'] ?? ''));
    if ($rawNumber !== '') {
        if (!preg_match('/^\d{1,4}$/', $rawNumber)) fail(t('err.number_invalid'));
        $releaseNumber = str_pad($rawNumber, 3, '0', STR_PAD_LEFT);
        $st = db()->prepare('SELECT id FROM albums WHERE release_number=? AND id<>?');
        $st->execute([$releaseNumber, $id]);
        if ($st->fetch()) fail(t('err.number_taken'));
    }

    $status = $_POST['status'] ?? 'draft';
    if (!in_array($status, ['draft', 'published', 'scheduled'], true)) $status = 'draft';
    $publishAt = null;
    if ($status === 'scheduled') {
        $raw = trim((string)($_POST['publish_at'] ?? ''));
        $ts = $raw === '' ? false : strtotime(str_replace('T', ' ', $raw));
        if ($ts === false) fail(t('err.publish_at_required'));
        $publishAt = date('Y-m-d H:i:s', $ts);
    }

    // Links: one unified list (name + URL), sent in display order
    $links = [];
    $names = $_POST['link_name'] ?? [];
    $urls = $_POST['link_url'] ?? [];
    foreach ($names as $i => $name) {
        $url = trim((string)($urls[$i] ?? ''));
        $name = trim((string)$name);
        if ($name !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $links[] = ['name' => $name, 'url' => $url];
        }
    }

    // Socials
    $socials = [];
    foreach (['instagram', 'facebook', 'tiktok', 'youtube'] as $key) {
        $u = trim((string)($_POST['socials'][$key] ?? ''));
        if ($u !== '' && filter_var($u, FILTER_VALIDATE_URL)) $socials[$key] = $u;
    }

    // Stickers
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
            $slots[] = ['sticker_id' => $sid, 'position' => $pos];
        }
        if (!$slots) $stickersOn = false;
    }

    $bg = in_array($_POST['bg_preset'] ?? 'noir', BG_PRESETS, true) ? $_POST['bg_preset'] : 'noir';
    $boxColor = in_array($_POST['box_color'] ?? 'auto', BOX_COLORS, true) ? $_POST['box_color'] : 'auto';
    $boxTransparent = isset($_POST['box_transparent']) ? 1 : 0;
    $musicId = (int)($_POST['music_id'] ?? 0) ?: null;

    // Cover: manual upload wins; otherwise the Spotify-provided URL, fetched server-side
    $cover = '';
    if (!empty($_FILES['cover']['name'])) {
        $cover = save_uploaded_image($_FILES['cover']);
    } else {
        $coverUrl = trim((string)($_POST['cover_url'] ?? ''));
        if ($coverUrl !== '' && filter_var($coverUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $coverUrl)) {
            $fetched = fetch_remote_image($coverUrl);
            if ($fetched === null) fail(t('err.cover_fetch'));
            $cover = $fetched;
        }
    }

    if ($id) {
        $stmt = db()->prepare('SELECT cover_path FROM albums WHERE id=?');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if (!$cover) $cover = (string)$old;
        $q = db()->prepare('UPDATE albums SET title=?,subtitle=?,is_single=?,release_number=?,artists=?,cover_path=?,links_json=?,socials_json=?,sticker_slots_json=?,bg_preset=?,box_color=?,box_transparent=?,music_id=?,publish_at=?,status=?,updated_at=NOW() WHERE id=?');
        $q->execute([$title, $subtitle, $isSingle, $releaseNumber !== '' ? $releaseNumber : null, $artists, $cover, json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode($socials, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ($stickersOn ? json_encode($slots) : null), $bg, $boxColor, $boxTransparent, $musicId, $publishAt, $status, $id]);
    } else {
        $min = (int)db()->query('SELECT COALESCE(MIN(sort_order),10) FROM albums')->fetchColumn();
        $q = db()->prepare('INSERT INTO albums(title,subtitle,is_single,release_number,artists,cover_path,links_json,socials_json,sticker_slots_json,bg_preset,box_color,box_transparent,music_id,publish_at,status,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $q->execute([$title, $subtitle, $isSingle, $releaseNumber !== '' ? $releaseNumber : null, $artists, $cover, json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode($socials, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ($stickersOn ? json_encode($slots) : null), $bg, $boxColor, $boxTransparent, $musicId, $publishAt, $status, $min - 10]);
    }
    redirect('releases.php?saved=1');
}

$edit = null;
if (isset($_GET['edit'])) {
    $q = db()->prepare('SELECT * FROM albums WHERE id=?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch();
    if ($edit) $edit['links'] = json_decode($edit['links_json'] ?: '[]', true) ?: [];
}
$platforms = db()->query('SELECT * FROM platforms WHERE active=1 ORDER BY sort_order, name')->fetchAll();
$preferred = array_values(array_filter($platforms, fn($p) => !empty($p['preferred'])));
$others = array_values(array_filter($platforms, fn($p) => empty($p['preferred'])));
$stickers = db()->query('SELECT id, name, image_path FROM stickers ORDER BY name')->fetchAll();
$tracks = tracks_for_select();
$editLinks = $edit['links'] ?? [];
if (!$edit) $editLinks = [['name' => '', 'url' => '']]; // one empty row for the no-JS fallback
$editSlots = $edit ? (json_decode($edit['sticker_slots_json'] ?: '[]', true) ?: []) : [];
$socials = $edit ? (json_decode($edit['socials_json'] ?: '[]', true) ?: []) : [];

admin_page_open(t('albums.manage'), 'albums');
?>
<h1><?= e($edit ? t('albums.edit') : t('albums.manage')) ?></h1>
<p class="subtitle"><?= e(t('albums.subtitle')) ?></p>

<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('albums.saved')) ?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data" id="album-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
    <input type="hidden" name="cover_url" id="cover_url" value="">

    <div class="find-panel">
        <label for="find-spotify"><?= e(t('find.spotify')) ?></label>
        <input id="find-spotify" name="find_spotify" placeholder="https://open.spotify.com/album/&hellip;">
        <p class="form-actions">
            <button type="button" id="find-btn"><?= e(t('find.btn')) ?></button>
            <span class="small" id="find-status"></span>
        </p>
        <p class="small"><?= e(t('find.note')) ?></p>
        <div id="find-cover" hidden>
            <img class="cover-thumb" id="find-cover-img" alt="Cover">
            <p class="form-actions"><button type="button" id="use-cover"><?= e(t('find.cover_btn')) ?></button></p>
        </div>
    </div>

    <div class="form-box">
        <h2><?= e(t('albums.section.main')) ?></h2>
        <label for="title"><?= e(t('albums.field.title')) ?> *</label>
        <input id="title" name="title" required value="<?= e($edit['title'] ?? '') ?>">
        <label for="subtitle"><?= e(t('albums.field.subtitle')) ?></label>
        <input id="subtitle" name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>">
        <label for="artists"><?= e(t('albums.field.artists')) ?></label>
        <input id="artists" name="artists" placeholder="A, B, C" value="<?= e($edit['artists'] ?? '') ?>">

        <label for="cover"><?= e(t('albums.field.cover')) ?></label>
        <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($edit['cover_path'])): ?>
            <p class="small"><?= e(t('albums.field.cover_current')) ?>: <?= e($edit['cover_path']) ?></p>
        <?php endif; ?>

        <p class="check-row"><input type="checkbox" id="single" name="is_single" value="1" <?= !empty($edit['is_single']) ? 'checked' : '' ?>><label class="inline" for="single"><?= e(t('albums.field.single')) ?></label></p>
        <p class="check-row"><input type="checkbox" id="stickers_enable" name="stickers_enable" value="1" <?= $editSlots ? 'checked' : '' ?>><label class="inline" for="stickers_enable"><?= e(t('albums.field.stickers_enable')) ?></label></p>
        <div id="sticker-panel"<?= $editSlots ? '' : ' hidden' ?>>
            <?php for ($i = 1; $i <= 3; $i++): $slot = $editSlots[$i - 1] ?? null; ?>
                <div class="sticker-slot">
                    <label for="sticker_<?= $i ?>"><?= e(t('albums.sticker.slot')) ?> <?= $i ?></label>
                    <select id="sticker_<?= $i ?>" name="sticker_<?= $i ?>">
                        <option value="0">&mdash;</option>
                        <?php foreach ($stickers as $s2): ?>
                            <option value="<?= (int)$s2['id'] ?>" <?= (int)($slot['sticker_id'] ?? 0) === (int)$s2['id'] ? 'selected' : '' ?>><?= e($s2['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="sticker_pos_<?= $i ?>"><?= e(t('albums.sticker.position')) ?></label>
                    <select id="sticker_pos_<?= $i ?>" name="sticker_pos_<?= $i ?>">
                        <?php foreach ([['tl', 'albums.sticker.pos.tl'], ['tc', 'albums.sticker.pos.tc'], ['tr', 'albums.sticker.pos.tr']] as [$val, $lab]): ?>
                            <option value="<?= e($val) ?>" <?= ($slot['position'] ?? 'tl') === $val ? 'selected' : '' ?>><?= e(t($lab)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endfor; ?>
            <p class="small"><a href="library.php?section=stickers"><?= e(t('stickers.add')) ?> &rarr;</a></p>
        </div>

        <h2><?= e(t('albums.links.preferred')) ?></h2>
        <div class="chip-list">
            <?php foreach ($preferred as $p): ?>
                <button type="button" class="chip" data-name="<?= e($p['name']) ?>"><?= e($p['name']) ?></button>
            <?php endforeach; ?>
        </div>
        <p class="small"><strong><?= e(t('albums.links.selected')) ?></strong></p>
        <div id="link-list">
            <?php foreach ($editLinks as $l): ?>
                <div class="link-item" data-name="<?= e($l['name'] ?? '') ?>">
                    <span class="drag-cell">&#8942;&#8942;</span>
                    <input name="link_name[]" placeholder="<?= e(t('link.placeholder.name')) ?>" value="<?= e($l['name'] ?? '') ?>">
                    <input type="url" name="link_url[]" placeholder="https://&hellip;" value="<?= e($l['url'] ?? '') ?>">
                    <button type="button" class="remove-row" title="<?= e(t('albums.links.remove')) ?>">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="find-row" style="margin-top:10px">
            <select id="other-platform">
                <option value="">&mdash;</option>
                <?php foreach ($others as $o): ?>
                    <option value="<?= e($o['name']) ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="add-other"><?= e(t('albums.links.add_platform')) ?></button>
            <button type="button" id="add-custom"><?= e(t('albums.field.add_link')) ?></button>
        </div>
    </div>

    <div class="form-box">
        <h2><?= e(t('albums.section.exclusive')) ?></h2>
        <label for="release_number"><?= e(t('albums.field.number')) ?></label>
        <input id="release_number" name="release_number" maxlength="4" inputmode="numeric" placeholder="007" style="max-width:180px" value="<?= e($edit['release_number'] ?? '') ?>">
        <p class="small"><?= e(t('albums.field.number_hint')) ?></p>

        <h2><?= e(t('albums.field.socials')) ?></h2>
        <div class="social-grid">
            <?php foreach (['instagram' => 'albums.social.instagram', 'facebook' => 'albums.social.facebook', 'tiktok' => 'albums.social.tiktok', 'youtube' => 'albums.social.youtube'] as $key => $lab): ?>
                <div>
                    <label for="social-<?= e($key) ?>"><?= e(t($lab)) ?></label>
                    <input id="social-<?= e($key) ?>" type="url" name="socials[<?= e($key) ?>]" placeholder="https://&hellip;" value="<?= e($socials[$key] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <h2><?= e(t('albums.field.music')) ?></h2>
        <label for="music_id"><?= e(t('albums.field.music')) ?></label>
        <select id="music_id" name="music_id">
            <option value="0"><?= e(t('albums.music.none')) ?></option>
            <?php foreach ($tracks as $tr2): ?>
                <option value="<?= (int)$tr2['id'] ?>" <?= (int)($edit['music_id'] ?? 0) === (int)$tr2['id'] ? 'selected' : '' ?>><?= e($tr2['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="small"><a href="library.php?section=music"><?= e(t('albums.music.manage')) ?></a></p>

        <h2><?= e(t('albums.field.appearance')) ?></h2>
        <label><?= e(t('albums.field.background')) ?></label>
        <div class="swatches">
            <?php foreach ([['noir', 'albums.bg.black', '#141414'], ['gris', 'albums.bg.grey', '#4d4d4d'], ['blanc', 'albums.bg.white', '#f0f0f0'], ['beige', 'albums.bg.beige', '#d6b399'], ['rose', 'albums.bg.rose', '#f05078'], ['blur', 'albums.bg.blur', null]] as [$val, $lab, $color]): ?>
                <p class="swatch-choice">
                    <input type="radio" id="bg-<?= e($val) ?>" name="bg_preset" value="<?= e($val) ?>" <?= ($edit['bg_preset'] ?? 'noir') === $val ? 'checked' : '' ?>>
                    <label class="inline" for="bg-<?= e($val) ?>">
                        <?php if ($color): ?><span class="swatch" style="background:<?= $color ?>"></span><?php else: ?><span class="swatch swatch-blur">&#9650;</span><?php endif; ?>
                        <?= e(t($lab)) ?>
                    </label>
                </p>
            <?php endforeach; ?>
        </div>
        <label for="box_color"><?= e(t('albums.field.box_color')) ?></label>
        <select id="box_color" name="box_color">
            <?php foreach (['auto' => t('albums.box.auto'), 'noir' => t('albums.bg.black'), 'gris' => t('albums.bg.grey'), 'blanc' => t('albums.bg.white'), 'beige' => t('albums.bg.beige'), 'rose' => t('albums.bg.rose')] as $val => $lab): ?>
                <option value="<?= e($val) ?>" <?= ($edit['box_color'] ?? 'auto') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="check-row"><input type="checkbox" id="box_transparent" name="box_transparent" value="1" <?= !empty($edit['box_transparent']) ? 'checked' : '' ?>><label class="inline" for="box_transparent"><?= e(t('albums.box.transparent')) ?></label></p>

        <?php if ($edit): ?>
            <p class="form-actions"><a class="btn" href="../?preview=<?= (int)$edit['id'] ?>&lang=<?= e(lang()) ?>" target="_blank" rel="noopener"><?= e(t('albums.preview')) ?> &nearr;</a></p>
        <?php else: ?>
            <p class="small"><?= e(t('albums.preview')) ?>: <?= e(t('albums.preview_after_save')) ?></p>
        <?php endif; ?>
    </div>

    <div class="form-box">
        <h2><?= e(t('albums.section.publication')) ?></h2>
        <label for="status"><?= e(t('albums.field.status')) ?></label>
        <div class="status-row">
            <select id="status" name="status" onchange="document.getElementById('publish-row').hidden = this.value !== 'scheduled'">
                <option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>><?= e(t('albums.status.draft')) ?></option>
                <option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= e(t('albums.status.published')) ?></option>
                <option value="scheduled" <?= ($edit['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>><?= e(t('albums.status.scheduled')) ?></option>
            </select>
        </div>
        <div id="publish-row"<?= ($edit['status'] ?? '') === 'scheduled' ? '' : ' hidden' ?>>
            <label for="publish_at"><?= e(t('albums.field.publish_at')) ?></label>
            <input type="datetime-local" id="publish_at" name="publish_at" value="<?= e(!empty($edit['publish_at']) ? str_replace(' ', 'T', substr((string)$edit['publish_at'], 0, 16)) : '') ?>">
        </div>
        <p class="form-actions">
            <button type="submit"><?= e(t('albums.save')) ?></button>
            <a class="btn" href="releases.php"><?= e(t('albums.cancel')) ?></a>
        </p>
        <p class="small"><a href="releases.php"><?= e(t('nav.releases')) ?> &rarr;</a></p>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/fr.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/de.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const LANG = <?= json_encode(lang()) ?>;

// ---------- link list: chips + dropdown + custom, all draggable ----------
const linkList = document.getElementById('link-list');
function makeLinkRow(name, url) {
    const row = document.createElement('div');
    row.className = 'link-item';
    row.dataset.name = name;
    row.innerHTML =
        '<span class="drag-cell">\u22ee\u22ee</span>' +
        '<input name="link_name[]" placeholder="' + <?= json_encode(t('link.placeholder.name')) ?> + '" value="">' +
        '<input type="url" name="link_url[]" placeholder="https://\u2026" value="">' +
        '<button type="button" class="remove-row" title="' + <?= json_encode(t('albums.links.remove')) ?> + '">\u00d7</button>';
    row.children[1].value = name;
    row.children[2].value = url;
    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove(); syncChips();
    });
    linkList.appendChild(row);
    if (!window.Sortable) row.querySelector('.drag-cell').append(...arrowsFor(row));
    return row;
}
function rowByName(name) {
    return [...linkList.querySelectorAll('.link-item')].find(
        r => r.dataset.name.toLowerCase() === name.toLowerCase()
    );
}
function syncChips() {
    document.querySelectorAll('.chip').forEach(ch => {
        ch.classList.toggle('on', !!rowByName(ch.dataset.name));
    });
}
document.querySelectorAll('.chip').forEach(ch => {
    ch.addEventListener('click', () => {
        const existing = rowByName(ch.dataset.name);
        if (existing) { existing.remove(); } else {
            makeLinkRow(ch.dataset.name, '');
            const url = linkList.lastElementChild.querySelector('input[type=url]');
            url.focus();
        }
        syncChips();
    });
});
document.getElementById('add-other').addEventListener('click', () => {
    const sel = document.getElementById('other-platform');
    if (sel.value === '') return;
    if (!rowByName(sel.value)) makeLinkRow(sel.value, '');
    sel.value = ''; syncChips();
});
document.getElementById('add-custom').addEventListener('click', () => {
    makeLinkRow('', '').querySelector('input[name="link_name[]"]').focus();
});
// server-rendered rows: bind their remove buttons
linkList.querySelectorAll('.link-item').forEach(it => {
    it.querySelector('.remove-row').addEventListener('click', () => { it.remove(); syncChips(); });
});
syncChips();

// drag & drop for the links (fallback arrows if the CDN is unreachable)
function arrowsFor(item) {
    const up = document.createElement('button'); up.type = 'button'; up.textContent = '\u2191'; up.className = 'mini-arrow';
    const down = document.createElement('button'); down.type = 'button'; down.textContent = '\u2193'; down.className = 'mini-arrow';
    up.addEventListener('click', () => { const p = item.previousElementSibling; if (p) { item.parentNode.insertBefore(item, p); } });
    down.addEventListener('click', () => { const n = item.nextElementSibling; if (n) { item.parentNode.insertBefore(n, item); } });
    return [up, down];
}
if (window.Sortable) {
    new Sortable(linkList, { handle: '.drag-cell', animation: 150 });
} else {
    linkList.querySelectorAll('.link-item').forEach(it => it.querySelector('.drag-cell').append(...arrowsFor(it)));
}

// platform checkboxes outside this page no longer exist; keep stickers panel toggle
const stk = document.getElementById('stickers_enable');
if (stk) stk.addEventListener('change', () => { document.getElementById('sticker-panel').hidden = !stk.checked; });

// scheduled date calendar (native fallback otherwise)
if (window.flatpickr) {
    window.flatpickr('#publish_at', {
        enableTime: true, time_24hr: true, dateFormat: 'Y-m-d\\TH:i',
        locale: LANG === 'fr' ? 'fr' : (LANG === 'de' ? 'de' : 'default')
    });
}

// ---------- automatic search ----------
const findBtn = document.getElementById('find-btn');
const findStatus = document.getElementById('find-status');
const FIND_API_MISSING = <?= json_encode(t('find.api_missing')) ?>;
const FIND_NOT_FOUND = <?= json_encode(t('find.not_found')) ?>;
const FIND_ERROR = <?= json_encode(t('find.error')) ?>;
findBtn.addEventListener('click', async () => {
    const url = document.getElementById('find-spotify').value.trim();
    if (!url) return;
    findStatus.textContent = <?= json_encode(t('find.searching')) ?>;
    findBtn.disabled = true;
    try {
        const body = new FormData();
        body.append('csrf', CSRF);
        body.append('spotify_url', url);
        const res = await fetch('find-links.php', { method: 'POST', body });
        const data = await res.json();
        if (data.error) {
            findStatus.textContent = data.error === 'api_missing' ? FIND_API_MISSING
                : (data.error === 'not_found' || data.error === 'invalid_url' ? FIND_NOT_FOUND : FIND_ERROR);
        } else {
            findStatus.textContent = '\u2713 ' + data.album.title + ' \u2014 ' + data.album.artists;
            data.results.forEach(r => {
                if (!rowByName(r.platform)) {
                    makeLinkRow(r.platform, r.url);
                } else {
                    rowByName(r.platform).querySelector('input[type=url]').value = r.url;
                }
            });
            syncChips();
            if (data.album.cover) {
                document.getElementById('find-cover-img').src = data.album.cover;
                document.getElementById('find-cover').hidden = false;
            }
        }
    } catch (err) {
        findStatus.textContent = FIND_ERROR;
    }
    findBtn.disabled = false;
});
// use the Spotify cover: remember the URL; it is downloaded when the form is saved
document.getElementById('use-cover').addEventListener('click', () => {
    const srcUrl = document.getElementById('find-cover-img').src;
    document.getElementById('cover_url').value = srcUrl;
    document.getElementById('find-status').textContent = <?= json_encode(t('find.cover_set')) ?>;
});
</script>
<?php admin_page_close();
