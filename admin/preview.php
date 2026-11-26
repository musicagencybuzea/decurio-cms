<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

/**
 * Live preview: stores exactly what is currently in the form (saved or not) in the
 * session, then redirects to ../?preview — index.php renders the release page from
 * this snapshot. Uploads that were chosen but not yet saved are stored as
 * preview files so they appear in the preview too.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: albums.php');
    exit;
}
csrf_check();
header('Cache-Control: no-store, max-age=0'); // the preview must never come from a cache

$p = collect_album_post(true);

$cover = '';
if (!empty($_FILES['cover']['name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
    // keep unsaved uploads in a dedicated preview slot so nothing pollutes real storage
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cover']['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext && $_FILES['cover']['size'] <= 8388608) {
        $dir = ROOT . '/uploads/albums';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = 'preview_' . session_id() . '.' . $ext;
        if (move_uploaded_file($_FILES['cover']['tmp_name'], $dir . '/' . $name)) {
            $cover = 'uploads/albums/' . $name;
        }
    }
}
// replace an old preview file when a new one comes in
foreach (glob(ROOT . '/uploads/albums/preview_' . session_id() . '.*') ?: [] as $old) {
    if ($cover !== '' && basename($old) !== basename($cover) && is_file($old)) @unlink($old);
}

$_SESSION['preview_snapshot'] = [
    'id' => (int)($_POST['id'] ?? 0) ?: 0,
    'title' => $p['title'],
    'subtitle' => $p['subtitle'],
    'artists' => $p['artists'],
    'is_single' => $p['is_single'],
    'release_number' => $p['release_number'],
    'release_date' => $p['release_date'],
    'cover_path' => $cover,
    'links_json' => json_encode($p['links'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    'socials_json' => json_encode($p['socials'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    'extra_socials_json' => json_encode($p['extras'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null,
    'sticker_slots_json' => $p['stickers_on'] ? json_encode($p['slots']) : null,
    'bg_preset' => $p['bg'],
    'video_id' => save_video_upload() ?: ($p['video_id'] ?? null),
    'box_color' => $p['box_color'],
    'box_transparent' => $p['box_transparent'],
    'link_text' => $p['link_text'] !== '' ? $p['link_text'] : null,
    'status' => 'draft',
    'publish_at' => null,
];

header('Location: ../index.php?preview=1&lang=' . lang());
exit;
