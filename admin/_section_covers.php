<?php
declare(strict_types=1);

/** Covers sub-tab of the library (included from library.php). */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $st = db()->prepare('SELECT file_path FROM covers WHERE id=?');
        $st->execute([$id]);
        $path = $st->fetchColumn();
        if ($path) {
            $abs = ROOT . '/uploads/covers/' . basename((string)$path);
            if (is_file($abs)) @unlink($abs);
        }
        db()->prepare('DELETE FROM covers WHERE id=?')->execute([$id]);
        redirect('library.php?section=covers&saved=1');
    }
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') fail(t('covers.err.name'));
        $f = $_FILES['file'];
        if ($f['size'] > 8 * 1024 * 1024) fail(t('stickers.err.too_large'));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) fail(t('stickers.err.image'));
        $dir = ROOT . '/uploads/covers';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) fail(t('stickers.err.save'));
        db()->prepare('INSERT INTO covers(name,file_path) VALUES(?,?)')->execute([$name, 'uploads/covers/' . $filename]);
        redirect('library.php?section=covers&saved=1');
    }
}
try {
    $covers = db()->query('SELECT * FROM covers ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    $covers = [];
    echo '<p class="notice error">Import database/migration-v17-covers.sql first (covers table missing).</p>';
}
?>
<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('library.saved')) ?></p><?php endif; ?>

<div class="panel">
    <h2><?= e(t('covers.add')) ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="cname"><?= e(t('covers.name')) ?> *</label>
        <input id="cname" name="name" required maxlength="120">
        <label for="cfile"><?= e(t('stickers.image')) ?> *</label>
        <input id="cfile" type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
        <p class="form-actions"><button type="submit"><?= e(t('covers.add')) ?></button></p>
    </form>
</div>

<h2><?= e(t('covers.existing')) ?></h2>
<?php if (!$covers): ?><p class="small">&mdash;</p><?php endif; ?>
<div class="sticker-grid">
    <?php foreach ($covers as $c): ?>
    <div class="sticker-card">
        <img src="../<?= e($c['file_path']) ?>" alt="<?= e($c['name']) ?>">
        <p class="small"><?= e($c['name']) ?></p>
        <form method="post" onsubmit="return confirm('<?= e(t('covers.confirm_delete')) ?>')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
            <button type="submit"><?= e(t('action.delete')) ?></button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
