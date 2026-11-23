<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $st = db()->prepare('SELECT image_path FROM stickers WHERE id=?');
        $st->execute([$id]);
        $path = $st->fetchColumn();
        if ($path) {
            $base = basename((string)$path);
            $abs = ROOT . '/uploads/stickers/' . $base;
            if (is_file($abs)) @unlink($abs);
        }
        db()->prepare('DELETE FROM stickers WHERE id=?')->execute([$id]);
        redirect('stickers.php?saved=1');
    }
    if (isset($_FILES['file'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') fail(t('stickers.err.name'));
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) fail(t('stickers.err.upload'));
        if ($f['size'] > 2 * 1024 * 1024) fail(t('stickers.err.too_large'));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) fail(t('stickers.err.image'));
        $dir = ROOT . '/uploads/stickers';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) fail(t('stickers.err.save'));
        db()->prepare('INSERT INTO stickers(name,image_path) VALUES(?,?)')->execute([$name, 'uploads/stickers/' . $filename]);
        redirect('stickers.php?saved=1');
    }
}
$stickers = db()->query('SELECT * FROM stickers ORDER BY name')->fetchAll();

admin_page_open(t('stickers.title'), 'stickers');
?>
<h1><?= e(t('stickers.title')) ?></h1>
<p class="subtitle"><?= e(t('stickers.subtitle')) ?></p>
<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('library.saved')) ?></p><?php endif; ?>

<div class="panel">
    <h2><?= e(t('stickers.add')) ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="name"><?= e(t('stickers.name')) ?> *</label>
        <input id="name" name="name" required maxlength="120">
        <label for="file"><?= e(t('stickers.image')) ?> *</label>
        <input id="file" type="file" name="file" accept="image/png,image/jpeg,image/webp" required>
        <p class="form-actions"><button type="submit"><?= e(t('stickers.add')) ?></button></p>
    </form>
</div>

<h2><?= e(t('stickers.existing')) ?></h2>
<?php if (!$stickers): ?><p class="small">—</p><?php endif; ?>
<div class="sticker-grid">
    <?php foreach ($stickers as $s): ?>
    <div class="sticker-card">
        <img src="../<?= e($s['image_path']) ?>" alt="<?= e($s['name']) ?>">
        <p class="small"><?= e($s['name']) ?></p>
        <form method="post" onsubmit="return confirm('<?= e(t('stickers.confirm_delete')) ?>')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
            <button type="submit"><?= e(t('action.delete')) ?></button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php admin_page_close();
