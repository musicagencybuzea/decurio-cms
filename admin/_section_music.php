<?php
declare(strict_types=1);

/** Music sub-tab of the library (included from library.php). */

/** Is ffmpeg usable on this hosting? (optional: files can be pre-cut manually) */
function ffmpeg_available(): bool
{
    static $available = null;
    if ($available === null) {
        $available = false;
        // exec() and friends are often disabled on shared hosting - never call them blind.
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
            @exec('command -v ffmpeg 2>/dev/null', $out, $code);
            $available = $code === 0 && !empty($out[0]);
        }
    }
    return $available;
}

/** Trim a freshly uploaded audio file to 30 s (stream copy, no re-encoding) if needed. */
function maybe_trim_audio(string $absPath): void
{
    if (!ffmpeg_available()) return;
    if (filesize($absPath) < 300000) return; // already short, leave it alone
    $tmp = $absPath . '.tmp';
    $cmd = 'ffmpeg -y -i ' . escapeshellarg($absPath) . ' -t 30 -c copy ' . escapeshellarg($tmp) . ' 2>/dev/null';
    @exec($cmd, $o, $code);
    if ($code === 0 && is_file($tmp) && filesize($tmp) > 1000) {
        rename($tmp, $absPath);
    } else {
        @unlink($tmp);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $st = db()->prepare('SELECT file_path FROM tracks WHERE id=?');
        $st->execute([$id]);
        $path = $st->fetchColumn();
        if ($path) {
            $abs = ROOT . '/' . ltrim((string)$path, '/');
            if (is_file($abs)) @unlink($abs);
        }
        db()->prepare('DELETE FROM tracks WHERE id=?')->execute([$id]);
        redirect('library.php?section=music&saved=1');
    }
    if (isset($_POST['rename_id'], $_POST['new_name'])) {
        $nn = trim((string)$_POST['new_name']);
        if ($nn !== '') db()->prepare('UPDATE tracks SET name=? WHERE id=?')->execute([$nn, (int)$_POST['rename_id']]);
        redirect('library.php?section=music&saved=1');
    }
    if (isset($_FILES['file'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') fail(t('music.err.name'));
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) fail(t('music.err.upload'));
        if ($f['size'] > 20 * 1024 * 1024) fail(t('music.err.too_large'));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $allowed = ['audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg'];
        if (!isset($allowed[$mime])) fail(t('music.err.format'));
        $dir = ROOT . '/uploads/music';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($f['tmp_name'], $target)) fail(t('music.err.save'));
        if (ffmpeg_available()) maybe_trim_audio($target);
        db()->prepare('INSERT INTO tracks(name,file_path) VALUES(?,?)')->execute([$name, 'uploads/music/' . $filename]);
        redirect('library.php?section=music&saved=1');
    }
}
$tracks = db()->query('SELECT * FROM tracks ORDER BY name')->fetchAll();
$ffmpeg = ffmpeg_available();
?>
<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('library.saved')) ?></p><?php endif; ?>
<p class="small"><?= e($ffmpeg ? t('music.ffmpeg_yes') : t('music.ffmpeg_no')) ?> <?= e(t('music.trim_note')) ?></p>

<div class="panel">
    <h2><?= e(t('music.add')) ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="name"><?= e(t('music.track')) ?> *</label>
        <input id="name" name="name" required maxlength="255">
        <label for="file"><?= e(t('music.file')) ?> *</label>
        <input id="file" type="file" name="file" accept="audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg" required>
        <p class="form-actions"><button type="submit"><?= e(t('music.add')) ?></button></p>
    </form>
</div>

<h2><?= e(t('music.existing')) ?></h2>
<?php if (!$tracks): ?><p class="small">&mdash;</p><?php endif; ?>
<table>
    <?php foreach ($tracks as $tr): ?>
    <tr>
        <td>
            <form method="post" class="rename-row">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="rename_id" value="<?= (int)$tr['id'] ?>">
                <input type="text" name="new_name" value="<?= e($tr['name']) ?>" maxlength="255">
                <button type="submit"><?= e(t('library.rename')) ?></button>
            </form>
        </td>
        <td><audio controls preload="none" src="../<?= e($tr['file_path']) ?>"></audio></td>
        <td class="actions">
            <form method="post" onsubmit="return confirm('<?= e(t('music.confirm_delete')) ?>')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="delete_id" value="<?= (int)$tr['id'] ?>">
                <button type="submit"><?= e(t('action.delete')) ?></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
