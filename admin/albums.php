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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Rows reordering (drag & drop)
    if (isset($_POST['reorder_ids'])) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$_POST['reorder_ids']))));
        $q = db()->prepare('UPDATE albums SET sort_order=? WHERE id=?');
        foreach ($ids as $i => $id) $q->execute([$i * 10, $id]);
        redirect('albums.php?saved=1');
    }

    // Deletion
    if (isset($_POST['delete_id'])) {
        db()->prepare('DELETE FROM albums WHERE id=?')->execute([(int)$_POST['delete_id']]);
        redirect('albums.php');
    }

    // Create / update
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

    // Links: platform checkboxes + custom rows
    $links = [];
    $platforms = db()->query('SELECT * FROM platforms WHERE active=1 ORDER BY sort_order, name')->fetchAll();
    $platformUrls = $_POST['platform_url'] ?? [];
    $platformChecked = $_POST['platform_check'] ?? [];
    foreach ($platforms as $p) {
        $pid = (string)$p['id'];
        if (!isset($platformChecked[$pid])) continue;
        $url = trim((string)($platformUrls[$pid] ?? ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $links[] = ['name' => $p['name'], 'url' => $url];
        }
    }
    $names = $_POST['link_name'] ?? [];
    $urls = $_POST['link_url'] ?? [];
    foreach ($names as $i => $name) {
        $url = trim($urls[$i] ?? '');
        if (trim((string)$name) !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $links[] = ['name' => trim((string)$name), 'url' => $url];
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

    $cover = '';
    if (!empty($_FILES['cover']['name'])) {
        $cover = save_uploaded_image($_FILES['cover']);
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
    redirect('albums.php?saved=1');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = db()->prepare('SELECT * FROM albums WHERE id=?');
        $q->execute([$id]);
        $album = $q->fetch();
    }
}
$edit = null;
if (isset($_GET['edit'])) {
    $q = db()->prepare('SELECT * FROM albums WHERE id=?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch();
    if ($edit) $edit['links'] = json_decode($edit['links_json'] ?: '[]', true) ?: [];
}
$albums = db()->query('SELECT * FROM albums ORDER BY sort_order, created_at DESC')->fetchAll();
$platforms = db()->query('SELECT * FROM platforms WHERE active=1 ORDER BY sort_order, name')->fetchAll();
$stickers = db()->query('SELECT id, name, image_path FROM stickers ORDER BY name')->fetchAll();
$tracks = tracks_for_select();
$editLinksByName = [];
$customLinks = [];
foreach (($edit['links'] ?? []) as $l) {
    $matched = false;
    foreach ($platforms as $p) {
        if (mb_strtolower(trim($p['name'])) === mb_strtolower(trim($l['name'] ?? ''))) { $editLinksByName[(string)$p['id']] = $l['url']; $matched = true; break; }
    }
    if (!$matched) $customLinks[] = $l;
}
$customLinks[] = ['name' => '', 'url' => ''];
$editSlots = $edit ? (json_decode($edit['sticker_slots_json'] ?: '[]', true) ?: []) : [];
$socials = $edit ? (json_decode($edit['socials_json'] ?: '[]', true) ?: []) : [];

admin_page_open(t('albums.manage'), 'albums');
?>
<h1><?= e(t('albums.manage')) ?></h1>
<p class="subtitle"><?= e(t('albums.subtitle')) ?></p>

<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('albums.saved')) ?></p><?php endif; ?>

<div class="panel">
    <h2><?= e($edit ? t('albums.edit') : t('albums.add')) ?></h2>
    <form method="post" enctype="multipart/form-data" id="album-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

        <div class="find-panel">
            <label for="find-spotify"><?= e(t('find.spotify')) ?></label>
            <input id="find-spotify" name="find_spotify" placeholder="https://open.spotify.com/album/…">
            <p class="form-actions">
                <button type="button" id="find-btn"><?= e(t('find.btn')) ?></button>
                <span class="small" id="find-status"></span>
            </p>
            <p class="small"><?= e(t('find.note')) ?></p>
        </div>

        <label for="title"><?= e(t('albums.field.title')) ?> *</label>
        <input id="title" name="title" required value="<?= e($edit['title'] ?? '') ?>">
        <label for="subtitle"><?= e(t('albums.field.subtitle')) ?></label>
        <input id="subtitle" name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>">
        <label for="artists"><?= e(t('albums.field.artists')) ?></label>
        <input id="artists" name="artists" value="<?= e($edit['artists'] ?? '') ?>">
        <p class="check-row"><input type="checkbox" id="single" name="is_single" value="1" <?= !empty($edit['is_single']) ? 'checked' : '' ?>><label class="inline" for="single"><?= e(t('albums.field.single')) ?></label></p>
        <label for="release_number"><?= e(t('albums.field.number')) ?></label>
        <input id="release_number" name="release_number" maxlength="4" inputmode="numeric" placeholder="007" value="<?= e($edit['release_number'] ?? '') ?>">
        <p class="small"><?= e(t('albums.field.number_hint')) ?></p>
        <label for="cover"><?= e(t('albums.field.cover')) ?></label>
        <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($edit['cover_path'])): ?>
            <p class="small"><?= e(t('albums.field.cover_current')) ?>: <?= e($edit['cover_path']) ?></p>
        <?php endif; ?>

        <h2><?= e(t('albums.field.links')) ?></h2>
        <p class="small"><?= e(t('albums.links.platforms')) ?></p>
        <div class="platform-list">
        <?php foreach ($platforms as $p): $pid = (string)$p['id']; $u = $editLinksByName[$pid] ?? ''; ?>
            <div class="platform-row" data-pname="<?= e(mb_strtolower($p['name'])) ?>">
                <input type="hidden" name="platform_check[<?= e($pid) ?>]" value="0">
                <input type="checkbox" id="pc_<?= e($pid) ?>" name="platform_check[<?= e($pid) ?>]" value="1" <?= $u !== '' ? 'checked' : '' ?>>
                <label class="inline" for="pc_<?= e($pid) ?>"><?= e($p['name']) ?></label>
                <input type="url" name="platform_url[<?= e($pid) ?>]" placeholder="https://…" value="<?= e($u) ?>">
            </div>
        <?php endforeach; ?>
        </div>
        <p class="small"><?= e(t('albums.links.custom')) ?></p>
        <div id="links">
            <?php foreach ($customLinks as $l): ?>
                <div class="links">
                    <input name="link_name[]" placeholder="<?= e(t('link.placeholder.name')) ?>" value="<?= e($l['name'] ?? '') ?>">
                    <input name="link_url[]" placeholder="https://…" value="<?= e($l['url'] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-link"><?= e(t('albums.field.add_link')) ?></button>

        <h2><?= e(t('albums.field.socials')) ?></h2>
        <?php foreach (['instagram' => 'albums.social.instagram', 'facebook' => 'albums.social.facebook', 'tiktok' => 'albums.social.tiktok', 'youtube' => 'albums.social.youtube'] as $key => $lab): ?>
            <label for="social-<?= e($key) ?>"><?= e(t($lab)) ?></label>
            <input id="social-<?= e($key) ?>" type="url" name="socials[<?= e($key) ?>]" placeholder="https://…" value="<?= e($socials[$key] ?? '') ?>">
        <?php endforeach; ?>

        <h2><?= e(t('albums.field.appearance')) ?></h2>
        <label><?= e(t('albums.field.background')) ?></label>
        <div class="swatches">
            <?php foreach ([['noir', 'albums.bg.black', '#141414'], ['gris', 'albums.bg.grey', '#4d4d4d'], ['blanc', 'albums.bg.white', '#f0f0f0'], ['beige', 'albums.bg.beige', '#d6b399'], ['rose', 'albums.bg.rose', '#f05078'], ['blur', 'albums.bg.blur', null]] as [$val, $lab, $color]): ?>
                <p class="swatch-choice">
                    <input type="radio" id="bg-<?= e($val) ?>" name="bg_preset" value="<?= e($val) ?>" <?= ($edit['bg_preset'] ?? 'noir') === $val ? 'checked' : '' ?>>
                    <label class="inline" for="bg-<?= e($val) ?>">
                        <?php if ($color): ?><span class="swatch" style="background:<?= $color ?>"></span><?php else: ?><span class="swatch swatch-blur">▲</span><?php endif; ?>
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

        <h2><?= e(t('albums.field.stickers')) ?></h2>
        <p class="check-row"><input type="checkbox" id="stickers_enable" name="stickers_enable" value="1" <?= $editSlots ? 'checked' : '' ?>><label class="inline" for="stickers_enable"><?= e(t('albums.field.stickers_enable')) ?></label></p>
        <div id="sticker-panel"<?= $editSlots ? '' : ' hidden' ?>>
            <?php for ($i = 1; $i <= 3; $i++): $slot = $editSlots[$i - 1] ?? null; ?>
                <div class="sticker-slot">
                    <label for="sticker_<?= $i ?>"><?= e(t('albums.sticker.slot')) ?> <?= $i ?></label>
                    <select id="sticker_<?= $i ?>" name="sticker_<?= $i ?>">
                        <option value="0">—</option>
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
            <p class="small"><a href="stickers.php"><?= e(t('stickers.add')) ?> →</a></p>
        </div>

        <h2><?= e(t('albums.field.music')) ?></h2>
        <label for="music_id"><?= e(t('albums.field.music')) ?></label>
        <select id="music_id" name="music_id">
            <option value="0"><?= e(t('albums.music.none')) ?></option>
            <?php foreach ($tracks as $tr2): ?>
                <option value="<?= (int)$tr2['id'] ?>" <?= (int)($edit['music_id'] ?? 0) === (int)$tr2['id'] ? 'selected' : '' ?>><?= e($tr2['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="small"><a href="music.php"><?= e(t('albums.music.manage')) ?></a></p>

        <h2><?= e(t('albums.field.status')) ?></h2>
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
            <?php if ($edit): ?>
                <a class="btn" href="albums.php"><?= e(t('albums.cancel')) ?></a>
                <a class="btn" href="../?preview=<?= (int)$edit['id'] ?>&lang=<?= e(lang()) ?>" target="_blank" rel="noopener">Preview ↗</a>
            <?php endif; ?>
        </p>
    </form>
</div>

<h2><?= e(t('albums.existing')) ?></h2>
<p class="small"><?= e(t('albums.hint.reorder')) ?></p>
<table>
    <tbody id="album-rows">
    <?php foreach ($albums as $a): ?>
    <tr data-id="<?= (int)$a['id'] ?>">
        <td class="drag-cell">⋮⋮</td>
        <td><?= e($a['title']) ?></td>
        <td><?= e($a['artists']) ?></td>
        <td class="status"><?= e($a['release_number'] ?? '') ?></td>
        <td class="status"><?= e($a['status'] === 'published' ? t('albums.status.published') : ($a['status'] === 'scheduled' ? t('albums.status.scheduled') : t('albums.status.draft'))) ?></td>
        <td class="actions">
            <a class="rowlink" href="?edit=<?= (int)$a['id'] ?>"><?= e(t('action.edit')) ?></a> ·
            <form method="post" onsubmit="return confirm('<?= e(t('albums.confirm_delete')) ?>')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="delete_id" value="<?= (int)$a['id'] ?>">
                <button type="submit"><?= e(t('action.delete')) ?></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/fr.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/de.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const LANG = <?= json_encode(lang()) ?>;

// Platform checkboxes: unchecking clears the URL, checking focuses it
document.querySelectorAll('.platform-row input[type=checkbox]').forEach(cb => {
    const url = cb.closest('.platform-row').querySelector('input[type=url]');
    cb.addEventListener('change', () => { if (!cb.checked) url.value = ''; else url.focus(); });
});

// Custom links: add row
document.getElementById('add-link').addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'links';
    row.innerHTML = '<input name="link_name[]" placeholder="' + <?= json_encode(t('link.placeholder.name')) ?> + '"><input name="link_url[]" placeholder="https://…">';
    document.getElementById('links').appendChild(row);
});

// Stickers panel visibility
const stk = document.getElementById('stickers_enable');
if (stk) stk.addEventListener('change', () => { document.getElementById('sticker-panel').hidden = !stk.checked; });

// Nice calendar for the scheduled date (native picker fallback if the CDN is unreachable)
if (window.flatpickr) {
    window.flatpickr('#publish_at', {
        enableTime: true, time_24hr: true, dateFormat: 'Y-m-d\\TH:i',
        locale: LANG === 'fr' ? 'fr' : (LANG === 'de' ? 'de' : 'default')
    });
}

// Table reordering: drag & drop, with up/down fallback if the CDN is unreachable
function submitOrder() {
    const ids = [...document.querySelectorAll('#album-rows tr')].map(tr => tr.dataset.id);
    const f = document.createElement('form');
    f.method = 'post';
    f.innerHTML = '<input name="csrf" value="' + CSRF + '"><input name="reorder_ids" value="' + ids.join(',') + '">';
    document.body.appendChild(f); f.submit();
}
if (window.Sortable) {
    new Sortable(document.getElementById('album-rows'), { handle: '.drag-cell', animation: 150, onEnd: submitOrder });
} else {
    document.querySelectorAll('#album-rows tr').forEach(tr => {
        const cell = tr.querySelector('.drag-cell');
        const up = document.createElement('button'); up.type = 'button'; up.textContent = '↑';
        const down = document.createElement('button'); down.type = 'button'; down.textContent = '↓';
        up.addEventListener('click', () => {
            const prev = tr.previousElementSibling;
            if (prev) { tr.parentNode.insertBefore(tr, prev); submitOrder(); }
        });
        down.addEventListener('click', () => {
            const next = tr.nextElementSibling;
            if (next) { tr.parentNode.insertBefore(next, tr); submitOrder(); }
        });
        cell.append(up, down);
    });
}

// Automatic link search
const findBtn = document.getElementById('find-btn');
const findStatus = document.getElementById('find-status');
const FIND_API_MISSING = <?= json_encode(t('find.api_missing')) ?>;
const FIND_NOT_FOUND = <?= json_encode(t('find.not_found')) ?>;
const FIND_ERROR = <?= json_encode(t('find.error')) ?>; // js l10n trio
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
            findStatus.textContent = '✓ ' + data.album.title + ' — ' + data.album.artists;
            data.results.forEach(r => {
                const row = document.querySelector('.platform-row[data-pname="' + r.platform.toLowerCase() + '"]');
                if (row) {
                    row.querySelector('input[type=checkbox]').checked = true;
                    row.querySelector('input[type=url]').value = r.url;
                }
            });
        }
    } catch (err) {
        findStatus.textContent = FIND_ERROR;
    }
    findBtn.disabled = false;
});
</script>
<?php admin_page_close();
