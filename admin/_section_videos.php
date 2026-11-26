<?php
declare(strict_types=1);

/** Videos sub-tab of the library (included from library.php) — Canvas/background clips. */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $st = db()->prepare('SELECT file_path FROM videos WHERE id=?');
        $st->execute([$id]);
        $path = $st->fetchColumn();
        if ($path) {
            $abs = ROOT . '/uploads/videos/' . basename((string)$path);
            if (is_file($abs)) @unlink($abs);
            // keep albums pointing at it but disable the background gracefully (render falls back to noir)
            db()->prepare('UPDATE albums SET video_id=NULL WHERE video_id=?')->execute([$id]);
        }
        db()->prepare('DELETE FROM videos WHERE id=?')->execute([$id]);
        redirect('library.php?section=videos&saved=1');
    }
    if (isset($_POST['rename_id'], $_POST['new_name'])) {
        $nn = trim((string)$_POST['new_name']);
        if ($nn !== '') db()->prepare('UPDATE videos SET name=? WHERE id=?')->execute([$nn, (int)$_POST['rename_id']]);
        redirect('library.php?section=videos&saved=1');
    }
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') fail(t('videos.err.name'));
        $f = $_FILES['file'];
        if ($f['size'] > 48 * 1024 * 1024) fail(t('videos.err.too_large'));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!isset($allowed[$mime])) fail(t('videos.err.video'));
        $dir = ROOT . '/uploads/videos';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) fail(t('videos.err.save'));
        db()->prepare('INSERT INTO videos(name,file_path) VALUES(?,?)')->execute([$name, 'uploads/videos/' . $filename]);
        redirect('library.php?section=videos&saved=1');
    }
}
try {
    $videos = db()->query('SELECT * FROM videos ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    $videos = [];
    echo '<p class="notice error">Import database/migration-v16-design.sql first (videos table missing).</p>';
}
?>
<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('library.saved')) ?></p><?php endif; ?>

<div class="panel">
    <h2><?= e(t('videos.add')) ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="vname"><?= e(t('videos.name')) ?> *</label>
        <input id="vname" name="name" required maxlength="120">
        <label for="vfile"><?= e(t('videos.file')) ?> * (mp4 / webm, max 48 MB)</label>
        <input id="vfile" type="file" name="file" accept="video/mp4,video/webm" required>
        <p class="form-actions"><button type="submit"><?= e(t('videos.add')) ?></button></p>
    </form>
</div>

<h2><?= e(t('videos.existing')) ?></h2>
<?php if (!$videos): ?><p class="small">&mdash;</p><?php endif; ?>
<div class="sticker-grid">
    <?php foreach ($videos as $v): ?>
    <div class="sticker-card video-card">
        <video src="../<?= e($v['file_path']) ?>" controls preload="metadata" muted></video>
        <form method="post" class="rename-row">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="rename_id" value="<?= (int)$v['id'] ?>">
            <input type="text" name="new_name" value="<?= e($v['name']) ?>" maxlength="120">
            <button type="submit"><?= e(t('library.rename')) ?></button>
        </form>
        <form method="post" onsubmit="return confirm('<?= e(t('videos.confirm_delete')) ?>')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="delete_id" value="<?= (int)$v['id'] ?>">
            <button type="submit"><?= e(t('action.delete')) ?></button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
