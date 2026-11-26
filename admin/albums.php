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

/** Handle the cover: manual upload wins, then the Spotify-provided URL, then the previous cover. */
function resolve_cover(int $oldId): string
{
    if (!empty($_FILES['cover']['name'])) return save_uploaded_image($_FILES['cover']);
    $coverUrl = trim((string)($_POST['cover_url'] ?? ''));
    if ($coverUrl !== '' && filter_var($coverUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $coverUrl)) {
        $fetched = fetch_remote_image($coverUrl);
        if ($fetched === null) fail(t('err.cover_fetch'));
        return $fetched;
    }
    // choose from the cover library (only when no file and no Spotify URL were given)
    $libId = (int)($_POST['cover_lib_id'] ?? 0);
    if ($libId > 0) {
        try {
            $st2 = db()->prepare('SELECT file_path FROM covers WHERE id=?');
            $st2->execute([$libId]);
            $libPath = (string)$st2->fetchColumn();
            if ($libPath !== '') return $libPath;
        } catch (Throwable $e3) {
            // covers table not migrated yet
        }
    }
    if ($oldId) {
        $stmt = db()->prepare('SELECT cover_path FROM albums WHERE id=?');
        $stmt->execute([$oldId]);
        return (string)$stmt->fetchColumn();
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['preview_mode'])) {
    csrf_check();

    $id = (int)($_POST['id'] ?? 0);
    $p = collect_album_post(false);

    if ($p['release_number'] !== '') {
        $st = db()->prepare('SELECT id FROM albums WHERE release_number=? AND id<>?');
        $st->execute([$p['release_number'], $id]);
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

    $cover = resolve_cover($id);
    register_cover($cover, $p['title']); // auto-add every new cover to the cover library

    // video/Canvas background: a freshly uploaded file goes straight into the video library
    $newVideoId = save_video_upload();
    if ($newVideoId) $p['video_id'] = $newVideoId;

    if ($id) {
        $q = db()->prepare('UPDATE albums SET title=?,subtitle=?,is_single=?,release_number=?,artists=?,cover_path=?,links_json=?,socials_json=?,extra_socials_json=?,sticker_slots_json=?,bg_preset=?,video_id=?,box_color=?,box_transparent=?,link_text=?,release_date=?,publish_at=?,status=?,updated_at=NOW() WHERE id=?');
        $q->execute([$p['title'], $p['subtitle'], $p['is_single'], $p['release_number'] !== '' ? $p['release_number'] : null, $p['artists'], $cover, json_encode($p['links'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode($p['socials'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode($p['extras'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null, ($p['stickers_on'] ? json_encode($p['slots']) : null), $p['bg'], $p['video_id'], $p['box_color'], $p['box_transparent'], $p['link_text'] !== '' ? $p['link_text'] : null, $p['release_date'] !== '' ? $p['release_date'] : null, $publishAt, $status, $id]);
    } else {
        $min = (int)db()->query('SELECT COALESCE(MIN(sort_order),10) FROM albums')->fetchColumn();
        $q = db()->prepare('INSERT INTO albums(title,subtitle,is_single,release_number,artists,cover_path,links_json,socials_json,extra_socials_json,sticker_slots_json,bg_preset,video_id,box_color,box_transparent,link_text,release_date,publish_at,status,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $q->execute([$p['title'], $p['subtitle'], $p['is_single'], $p['release_number'] !== '' ? $p['release_number'] : null, $p['artists'], $cover, json_encode($p['links'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode($p['socials'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode($p['extras'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null, ($p['stickers_on'] ? json_encode($p['slots']) : null), $p['bg'], $p['video_id'], $p['box_color'], $p['box_transparent'], $p['link_text'] !== '' ? $p['link_text'] : null, $p['release_date'] !== '' ? $p['release_date'] : null, $publishAt, $status, $min - 10]);
    }
    unset($_SESSION['preview_snapshot']); // saved version now matches the preview
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
try {
    $covers = db()->query('SELECT id, name, file_path FROM covers ORDER BY name')->fetchAll();
} catch (Throwable $e4) {
    $covers = []; // covers table not migrated yet
}
try {
    $videos = db()->query('SELECT id, name, file_path FROM videos ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    $videos = []; // videos table not migrated yet
}
$editLinks = $edit ? (json_decode($edit['links_json'] ?: '[]', true) ?: []) : [];
$editSlots = $edit ? (json_decode($edit['sticker_slots_json'] ?: '[]', true) ?: []) : [];
$socials = $edit ? (json_decode($edit['socials_json'] ?: '[]', true) ?: []) : [];
$extraSocials = $edit ? (json_decode($edit['extra_socials_json'] ?: '[]', true) ?: []) : [];

admin_page_open(t('albums.manage'), 'albums');
?>
<h1><?= e($edit ? t('albums.edit') : t('albums.manage')) ?></h1>

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
        <label for="release_number"><?= e(t('albums.field.number')) ?></label>
        <input id="release_number" name="release_number" maxlength="5" placeholder="007 or 007A" style="max-width:180px" value="<?= e($edit['release_number'] ?? '') ?>">
        <p class="small"><?= e(t('albums.field.number_hint')) ?></p>

        <label for="title"><?= e(t('albums.field.title')) ?> *</label>
        <input id="title" name="title" required value="<?= e($edit['title'] ?? '') ?>">
        <label for="subtitle"><?= e(t('albums.field.subtitle')) ?></label>
        <input id="subtitle" name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>">
        <label for="artists"><?= e(t('albums.field.artists')) ?></label>
        <input id="artists" name="artists" placeholder="A, B, C" value="<?= e($edit['artists'] ?? '') ?>">

        <div class="flags-row">
            <p class="check-row"><input type="checkbox" id="single" name="is_single" value="1" <?= !empty($edit['is_single']) ? 'checked' : '' ?>><label class="inline" for="single"><?= e(t('albums.field.single')) ?></label></p>
            <p class="check-row"><input type="checkbox" id="album_out" name="album_out" value="1" <?= !empty($edit['release_date']) ? 'checked' : '' ?>><label class="inline" for="album_out"><?= e(t('albums.field.album_out')) ?></label></p>
        </div>
        <div id="album-out-row"<?= !empty($edit['release_date']) ? '' : ' hidden' ?>>
            <label for="release_date"><?= e(t('albums.field.album_out_date')) ?></label>
            <input type="date" id="release_date" name="release_date" value="<?= e($edit['release_date'] ?? '') ?>">
        </div>

        <p class="check-row"><input type="checkbox" id="stickers_enable" name="stickers_enable" value="1" <?= $editSlots ? 'checked' : '' ?>><label class="inline" for="stickers_enable"><?= e(t('albums.field.stickers_enable')) ?></label></p>
        <div id="sticker-panel"<?= $editSlots ? '' : ' hidden' ?>>
            <?php $visibleSlots = max(1, count($editSlots)); for ($i = 1; $i <= 3; $i++): $slot = $editSlots[$i - 1] ?? null; ?>
                <div class="sticker-slot" data-slot="<?= $i ?>"<?= $i > $visibleSlots ? ' hidden' : '' ?>>
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
                    <label for="sticker_size_<?= $i ?>"><?= e(t('albums.sticker.size')) ?></label>
                    <select id="sticker_size_<?= $i ?>" name="sticker_size_<?= $i ?>">
                        <?php foreach ([['1', 'albums.sticker.size.s'], ['2', 'albums.sticker.size.m'], ['3', 'albums.sticker.size.l']] as [$val, $lab]): ?>
                            <option value="<?= $val ?>" <?= ($slot['size'] ?? '2') === $val ? 'selected' : '' ?>><?= e(t($lab)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endfor; ?>
            <p class="check-row"><button type="button" id="add-sticker-slot" class="linklike"<?= count($editSlots) >= 3 ? ' hidden' : '' ?>>&plus; <?= e(t('albums.sticker.add_another')) ?></button></p>
            <p class="small"><a href="library.php?section=stickers"><?= e(t('stickers.add')) ?> &rarr;</a></p>
        </div>

        <label for="cover"><?= e(t('albums.field.cover')) ?></label>
        <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
        <label for="cover_lib_id"><?= e(t('albums.cover_lib')) ?></label>
        <select id="cover_lib_id" name="cover_lib_id">
            <option value=""><?= e(t('albums.cover_lib_none')) ?></option>
            <?php foreach ($covers as $c3): ?>
                <option value="<?= (int)$c3['id'] ?>"><?= e($c3['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($edit['cover_path'])): ?>
            <p class="small"><?= e(t('albums.field.cover_current')) ?>: <?= e($edit['cover_path']) ?></p>
        <?php endif; ?>
    </div>

    <div class="form-box">
        <h2><?= e(t('albums.links.heading')) ?></h2>
        <div class="chip-list">
            <?php foreach ($preferred as $p): ?>
                <button type="button" class="chip" data-name="<?= e($p['name']) ?>"><?= e($p['name']) ?></button>
            <?php endforeach; ?>
        </div>
        <div id="link-list">
            <?php foreach ($editLinks as $l): ?>
                <div class="link-item" data-name="<?= e($l['name'] ?? '') ?>">
                    <span class="drag-cell">&#8942;&#8942;</span>
                    <input name="link_name[]" placeholder="<?= e(t('link.placeholder.name')) ?>" value="<?= e($l['name'] ?? '') ?>">
                    <input type="url" name="link_url[]" placeholder="https://&hellip;" value="<?= e($l['url'] ?? '') ?>">
                    <?php $act = (string)($l['action'] ?? ''); $isCustom = $act !== '' && !in_array($act, LINK_ACTIONS, true); ?>
                    <span class="action-cell" data-act="<?= e($isCustom ? 'custom' : $act) ?>">
                        <select name="link_action[]">
                            <?php foreach ([['', t('action.play')], ...array_map(fn($a) => [$a, t('action.' . $a)], LINK_ACTIONS), ['custom', t('action.custom')]] as [$val, $lab]): if ($val === '') continue; ?>
                                <option value="<?= e($val) ?>" <?= ($isCustom ? 'custom' : $act) === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="link_action_custom[]" class="action-custom" placeholder="<?= e(t('action.word')) ?>" value="<?= e($isCustom ? $act : '') ?>"<?= ($isCustom ? '' : ' hidden') ?>>
                    </span>
                    <button type="button" class="remove-row" title="<?= e(t('albums.links.remove')) ?>">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-actions link-tools">
            <button type="button" id="toggle-other"><?= e(t('albums.links.other_toggle')) ?></button>
            <button type="button" id="toggle-custom"><?= e(t('albums.links.custom_toggle')) ?></button>
        </p>
        <div class="find-row" id="other-platform-row" hidden>
            <select id="other-platform">
                <option value="">&mdash;</option>
                <?php foreach ($others as $o): ?>
                    <option value="<?= e($o['name']) ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="add-other"><?= e(t('albums.links.add_platform')) ?></button>
        </div>
    </div>

    <div class="form-box">
        <h2><?= e(t('albums.section.exclusive')) ?></h2>
        <h3><?= e(t('albums.field.socials')) ?></h3>
        <div class="social-grid">
            <?php foreach (['instagram' => 'albums.social.instagram', 'facebook' => 'albums.social.facebook', 'youtube' => 'albums.social.youtube', 'tiktok' => 'albums.social.tiktok'] as $key => $lab): ?>
                <div>
                    <label for="social-<?= e($key) ?>"><?= e(t($lab)) ?></label>
                    <input id="social-<?= e($key) ?>" type="url" name="socials[<?= e($key) ?>]" placeholder="https://&hellip;" value="<?= e($socials[$key] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div id="extra-socials">
            <?php foreach ($extraSocials as $extra): ?>
                <div class="extra-social">
                    <select name="extra_platform[]">
                        <?php foreach (['instagram', 'facebook', 'youtube', 'tiktok'] as $p2): ?>
                            <option value="<?= e($p2) ?>" <?= ($extra['platform'] ?? '') === $p2 ? 'selected' : '' ?>><?= e(t('albums.social.' . $p2)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="extra_url[]" placeholder="https://&hellip;" value="<?= e($extra['url'] ?? '') ?>">
                    <button type="button" class="remove-row">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="check-row"><button type="button" id="add-social" class="linklike">&plus; <?= e(t('albums.field.more_socials')) ?></button></p>

        <h3><?= e(t('albums.field.appearance')) ?></h3>
        <?php $bgVal = (string)($edit['bg_preset'] ?? 'noir');
        if ($bgVal === 'blur') $bgVal = 'blur2'; // legacy value from v1.2
        $bgGroup = str_starts_with($bgVal, 'blur') ? 'blur'
            : (in_array($bgVal, ['noir', 'gris', 'blanc', 'beige', 'rose', 'adapt1', 'adapt2', 'adapt3'], true) ? 'solid'
            : ($bgVal === 'ambilight' ? 'ambilight' : 'video')); ?>
        <input type="hidden" name="bg_preset" id="bg_preset" value="<?= e($bgVal) ?>">
        <label><?= e(t('albums.field.background')) ?></label>
        <div class="bg-choice">
            <?php foreach ([['blur', 'albums.bg.blur'], ['solid', 'albums.bg.solid'], ['ambilight', 'albums.bg.ambilight'], ['video', 'albums.bg.video']] as [$g, $k]): ?>
                <p class="swatch-choice">
                    <input type="radio" id="bgc-<?= e($g) ?>" name="bg_group" value="<?= e($g) ?>" <?= $bgGroup === $g ? 'checked' : '' ?>>
                    <label class="inline" for="bgc-<?= e($g) ?>"><?= e(t($k)) ?></label>
                </p>
            <?php endforeach; ?>
        </div>
        <div class="bg-sub" data-sub="blur" <?= $bgGroup === 'blur' ? '' : 'hidden' ?>>
            <label for="bg_blur_grade"><?= e(t('albums.bg.blur_degree')) ?></label>
            <select id="bg_blur_grade" name="bg_blur_grade">
                <option value="1" <?= $bgVal === 'blur1' ? 'selected' : '' ?>><?= e(t('albums.bg.blur1')) ?></option>
                <option value="2" <?= $bgVal === 'blur2' ? 'selected' : '' ?>><?= e(t('albums.bg.blur2')) ?></option>
                <option value="3" <?= $bgVal === 'blur3' ? 'selected' : '' ?>><?= e(t('albums.bg.blur3')) ?></option>
            </select>
        </div>
        <div class="bg-sub" data-sub="solid" <?= $bgGroup === 'solid' ? '' : 'hidden' ?>>
            <label for="bg_solid"><?= e(t('albums.bg.solid')) ?></label>
            <select id="bg_solid" name="bg_solid">
                <optgroup label="<?= e(t('albums.bg.solid')) ?>">
                <?php foreach ([['noir', 'albums.bg.black'], ['gris', 'albums.bg.grey'], ['blanc', 'albums.bg.white'], ['beige', 'albums.bg.beige'], ['rose', 'albums.bg.rose']] as [$v, $k2]): ?>
                    <option value="<?= e($v) ?>" <?= $bgVal === $v ? 'selected' : '' ?>><?= e(t($k2)) ?></option>
                <?php endforeach; ?>
                </optgroup>
                <optgroup label="<?= e(t('albums.bg.adapt_group')) ?>">
                <?php foreach ([['adapt1', 'albums.bg.adapt1'], ['adapt2', 'albums.bg.adapt2'], ['adapt3', 'albums.bg.adapt3']] as [$v2, $k3]): ?>
                    <option value="<?= e($v2) ?>" <?= $bgVal === $v2 ? 'selected' : '' ?>><?= e(t($k3)) ?></option>
                <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div class="bg-sub" data-sub="video" <?= $bgGroup === 'video' ? '' : 'hidden' ?>>
            <label for="bg_video_file"><?= e(t('albums.bg.video_upload')) ?></label>
            <input type="file" id="bg_video_file" name="bg_video" accept="video/mp4,video/webm">
            <label for="video_id"><?= e(t('albums.bg.video_lib')) ?></label>
            <select id="video_id" name="video_id">
                <option value=""><?= e(t('albums.bg.video_none')) ?></option>
                <?php foreach ($videos as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" <?= (string)($edit['video_id'] ?? '') === (string)$v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label for="box_color"><?= e(t('albums.field.box_color')) ?></label>
        <select id="box_color" name="box_color">
            <?php foreach (['auto' => t('albums.box.auto'), 'noir' => t('albums.bg.black'), 'gris' => t('albums.bg.grey'), 'blanc' => t('albums.bg.white'), 'beige' => t('albums.bg.beige'), 'rose' => t('albums.bg.rose')] as $val => $lab): ?>
                <option value="<?= e($val) ?>" <?= ($edit['box_color'] ?? 'auto') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="check-row"><input type="checkbox" id="box_transparent" name="box_transparent" value="1" <?= !empty($edit['box_transparent']) ? 'checked' : '' ?>><label class="inline" for="box_transparent"><?= e(t('albums.box.transparent')) ?></label></p>
        <label for="link_text"><?= e(t('albums.link_text')) ?></label>
        <select id="link_text" name="link_text">
            <?php $linkTextVal = (string)($edit['link_text'] ?? ''); ?>
            <option value="" <?= $linkTextVal === '' ? 'selected' : '' ?>><?= e(t('albums.link_text.auto')) ?></option>
            <option value="clair" <?= $linkTextVal === 'clair' ? 'selected' : '' ?>><?= e(t('albums.link_text.light')) ?></option>
            <option value="fonce" <?= $linkTextVal === 'fonce' ? 'selected' : '' ?>><?= e(t('albums.link_text.dark')) ?></option>
        </select>

        <p class="form-actions">
            <button type="submit" name="preview_mode" value="1" formaction="preview.php" formmethod="post" formtarget="preview_frame"><?= e(t('albums.preview')) ?> &nearr;</button>
        </p>
        <p class="form-actions"><a class="small-open" href="#" id="open-preview-tab" target="_blank" hidden><?= e(t('albums.preview_tab')) ?> &nearr;&nearr;</a></p>
        <div class="preview-panel" id="preview-panel" hidden>
            <p class="small"><?= e(t('albums.preview_live')) ?></p>
            <iframe name="preview_frame" title="preview"></iframe>
        </div>
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

// ---------- link list: chips, other platforms, custom links, action words, draggable ----------
const linkList = document.getElementById('link-list');
const ACTION_OPTIONS = <?= json_encode(array_merge(LINK_ACTIONS, ['custom'])) ?>;

function actionCellHTML(action) {
    const isCustom = action !== '' && !ACTION_OPTIONS.includes(action);
    const sel = ACTION_OPTIONS.includes(action) ? action : (isCustom ? 'custom' : '');
    let html = '<span class="action-cell" data-act="' + (isCustom ? 'custom' : action) + '">';
    html += '<select name="link_action[]">';
    ACTION_OPTIONS.forEach(o => {
        html += '<option value="' + o + '"' + (sel === o ? ' selected' : '') + '>' + T_action(o) + '</option>';
    });
    html += '</select>';
    html += '<input type="text" name="link_action_custom[]" class="action-custom" placeholder="' + <?= json_encode(t('action.word')) ?> + '" value="' + (isCustom ? action : '') + '"' + (isCustom ? '' : ' hidden') + '>';
    html += '</span>';
    return html;
}
function bindActionCell(cell) {
    const sel = cell.querySelector('select');
    const custom = cell.querySelector('.action-custom');
    sel.addEventListener('change', () => {
        custom.hidden = sel.value !== 'custom';
        cell.dataset.act = sel.value;
    });
}
document.querySelectorAll('.action-cell').forEach(bindActionCell);

function makeLinkRow(name, url, action) {
    const row = document.createElement('div');
    row.className = 'link-item';
    row.dataset.name = name;
    row.innerHTML =
        '<span class="drag-cell">\u22ee\u22ee</span>' +
        '<input name="link_name[]" placeholder="' + <?= json_encode(t('link.placeholder.name')) ?> + '" value="">' +
        '<input type="url" name="link_url[]" placeholder="https://\u2026" value="">' +
        actionCellHTML(action || '') +
        '<button type="button" class="remove-row" title="' + <?= json_encode(t('albums.links.remove')) ?> + '">\u00d7</button>';
    row.children[1].value = name;
    row.children[2].value = url;
    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove(); syncChips();
    });
    bindActionCell(row.querySelector('.action-cell'));
    linkList.appendChild(row);
    if (!window.Sortable) row.querySelector('.drag-cell').append(...arrowsFor(row));
    return row;
}
function rowByName(name) {
    return [...linkList.querySelectorAll('.link-item')].find(
        r => r.dataset.name.toLowerCase() === name.toLowerCase() && r.dataset.name !== ''
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
            linkList.lastElementChild.querySelector('input[type=url]').focus();
        }
        syncChips();
    });
});
// "Other platforms" and "Custom link" stay folded away until needed
document.getElementById('toggle-other').addEventListener('click', () => {
    const row2 = document.getElementById('other-platform-row');
    row2.hidden = !row2.hidden;
});
document.getElementById('toggle-custom').addEventListener('click', () => {
    const row2 = makeLinkRow('', '');
    row2.querySelector('input[name="link_name[]"]').focus();
});
document.getElementById('add-other').addEventListener('click', () => {
    const sel = document.getElementById('other-platform');
    if (sel.value === '') return;
    if (!rowByName(sel.value)) makeLinkRow(sel.value, '');
    sel.value = ''; syncChips();
});
// server-rendered rows: bind remove + action buttons
linkList.querySelectorAll('.link-item').forEach(it => {
    it.querySelector('.remove-row').addEventListener('click', () => { it.remove(); syncChips(); });
});
syncChips();

// ---------- extra social accounts ----------
document.getElementById('add-social').addEventListener('click', () => {
    const wrap = document.getElementById('extra-socials');
    const row2 = document.createElement('div');
    row2.className = 'extra-social';
    row2.innerHTML =
        '<select name="extra_platform[]">' +
        <?= json_encode(implode('', array_map(
            fn($p) => '<option value="' . $p . '">' . e(t('albums.social.' . $p)) . '</option>',
            ['instagram', 'facebook', 'youtube', 'tiktok']
        ))) ?> +
        '</select>' +
        '<input type="url" name="extra_url[]" placeholder="https://\u2026">' +
        '<button type="button" class="remove-row">\u00d7</button>';
    row2.querySelector('.remove-row').addEventListener('click', () => row2.remove());
    wrap.appendChild(row2);
});
document.querySelectorAll('#extra-socials .remove-row').forEach(b => {
    b.addEventListener('click', () => b.parentNode.remove());
});

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

// stickers panel + album-out date toggles
const stk = document.getElementById('stickers_enable');
if (stk) stk.addEventListener('change', () => { document.getElementById('sticker-panel').hidden = !stk.checked; });
// one sticker slot first; "+ Add another sticker" reveals the next (max 3)
const addSlotBtn = document.getElementById('add-sticker-slot');
if (addSlotBtn) addSlotBtn.addEventListener('click', function () {
    const next = document.querySelector('.sticker-slot[hidden]');
    if (next) next.hidden = false;
    if (!document.querySelector('.sticker-slot[hidden]')) addSlotBtn.hidden = true;
});
const ao = document.getElementById('album_out');
if (ao) ao.addEventListener('change', () => { document.getElementById('album-out-row').hidden = !ao.checked; });
if (window.flatpickr && document.getElementById('release_date')) {
    window.flatpickr('#release_date', {
        dateFormat: 'Y-m-d',
        locale: LANG === 'fr' ? 'fr' : (LANG === 'de' ? 'de' : 'default')
    });
}

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
                    makeLinkRow(r.platform, r.url, 'play');
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

// translated action labels for the JS-generated dropdown (no inline PHP inside loops)
function T_action(o) {
    return {
        play: <?= json_encode(t('action.play')) ?>,
        buy: <?= json_encode(t('action.buy')) ?>,
        download: <?= json_encode(t('action.download')) ?>,
        visit: <?= json_encode(t('action.visit')) ?>,
        listen: <?= json_encode(t('action.listen')) ?>,
        learn: <?= json_encode(t('action.learn')) ?>,
        subscribe: <?= json_encode(t('action.subscribe')) ?>,
        join: <?= json_encode(t('action.join')) ?>,
        support: <?= json_encode(t('action.support')) ?>,
        custom: <?= json_encode(t('action.custom')) ?>
    }[o] || o;
}

/* Design background: 4 choices, each revealing its own sub-panel.
   The hidden bg_preset input always carries the final value for save + preview. */
(function () {
    var hidden = document.getElementById('bg_preset');
    function syncBg() {
        var g = document.querySelector('input[name="bg_group"]:checked');
        var group = g ? g.value : 'blur';
        document.querySelectorAll('.bg-sub').forEach(function (p) {
            p.hidden = p.getAttribute('data-sub') !== group;
        });
        if (!hidden) return;
        if (group === 'blur') {
            var grade = document.getElementById('bg_blur_grade');
            hidden.value = 'blur' + (grade ? grade.value : '2');
        } else if (group === 'solid') {
            var solid = document.getElementById('bg_solid');
            hidden.value = solid ? solid.value : 'noir';
        } else if (group === 'ambilight') {
            hidden.value = 'ambilight';
        } else {
            hidden.value = 'video';
        }
    }
    document.querySelectorAll('input[name="bg_group"], #bg_blur_grade, #bg_solid, #video_id')
        .forEach(function (el) { el.addEventListener('change', syncBg); });
    if (hidden) window.syncBg = syncBg;
    syncBg();
})();

/* inline live preview (phase 2):
   1) the click always reveals the panel;
   2) the form state is POSTed to preview.php via fetch (snapshot in the session);
   3) the preview page is then FETCHED (parent context → session cookies guaranteed)
      and injected via srcdoc with <base href="/"> so relative URLs resolve.
   Both requests carry a cache-buster + no-store: the preview can never show a
   stale "same thing" again — the earlier symptom was browser/host caching. */
(function () {
    var btn = document.querySelector('[name=preview_mode]');
    var panel = document.getElementById('preview-panel');
    var tab = document.getElementById('open-preview-tab');
    if (!btn || !panel) return;
    var iframe = panel.querySelector('iframe');
    var WAIT = '<style>html,body{height:100%;margin:0;background:#141414}'
        + 'p{color:#f0f0f0;font-size:11px;letter-spacing:.3em;text-transform:uppercase;'
        + 'font-family:sans-serif;text-align:center;padding-top:48%}</style><p>…</p>';
    var WAIT_ERR_HEAD = '<style>html,body{height:100%;margin:0;background:#141414}'
        + 'p{color:#f05078;font-size:11px;letter-spacing:.15em;text-transform:uppercase;'
        + 'font-family:sans-serif;text-align:center;padding-top:38%;max-width:82%;margin-left:auto;margin-right:auto}'
        + '</style><p>';
    function errPage(msg) {
        var safe = String(msg || 'unknown error').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return WAIT_ERR_HEAD + safe + '</p>';
    }
    function marker(html, fallback) {
        var m = String(html || '').match(/<!--PREVIEW-ERROR:([\s\S]*?)-->/);
        return m ? m[1].trim() : fallback;
    }
    btn.addEventListener('click', function (ev) {
        panel.hidden = false;
        if (tab) tab.hidden = false;
        if (!window.fetch) return; // very old browser: native POST into the iframe as a fallback
        ev.preventDefault();
        var form = btn.closest('form');
        if (!form) return;
        if (window.syncBg) window.syncBg(); // final bg choice before snapshotting
        var t = Date.now();
        var fd = new FormData(form);
        fd.set('preview_mode', '1');
        if (iframe) iframe.srcdoc = WAIT;
        fetch('preview.php?t=' + t, { method: 'POST', body: fd, cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) return r.text().then(function (tx) {
                    throw new Error(marker(tx, 'step 1 failed (saving the form state), status ' + r.status));
                });
            })
            .then(function () {
                return fetch('/preview-page.php?lang=' + LANG + '&t=' + Date.now(), { cache: 'no-store' });
            })
            .then(function (r) {
                if (!r.ok) return r.text().then(function (tx) {
                    throw new Error(marker(tx, 'step 2 failed (loading the preview page), status ' + r.status));
                });
                return r.text();
            })
            .then(function (html) {
                if (html.indexOf('class="release-page') === -1) {
                    throw new Error(marker(html, 'an unexpected page was served (cache?)'));
                }
                if (iframe) iframe.srcdoc = html.replace('<head>', '<head><base href="/">');
                if (tab) tab.href = '/preview-page.php?lang=' + LANG + '&x=' + Date.now();
            })
            .catch(function (err) {
                // NEVER submit the real form here: it would SAVE the album and show the releases list
                if (iframe) iframe.srcdoc = errPage(err && err.message);
            });
    });
    if (tab) tab.href = '/preview-page.php?lang=' + LANG;
})();
</script>
<?php admin_page_close();
