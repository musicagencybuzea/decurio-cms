<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        http_response_code(422);
        exit(t('err.title_required'));
    }
    $artists = trim($_POST['artists'] ?? '');
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $order = (int)($_POST['sort_order'] ?? 0);
    $links = [];
    $names = $_POST['link_name'] ?? [];
    $urls = $_POST['link_url'] ?? [];
    foreach ($names as $i => $name) {
        $url = trim($urls[$i] ?? '');
        if (trim($name) !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $links[] = ['name' => trim($name), 'url' => $url];
        }
    }
    $cover = '';
    if (!empty($_FILES['cover']['name'])) {
        $f = $_FILES['cover'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            http_response_code(422);
            exit(t('err.upload'));
        }
        if ($f['size'] > 8 * 1024 * 1024) {
            http_response_code(422);
            exit(t('err.too_large'));
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            http_response_code(422);
            exit(t('err.format'));
        }
        $dir = ROOT . '/uploads/albums';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) {
            http_response_code(500);
            exit(t('err.save_cover'));
        }
        $cover = 'uploads/albums/' . $filename;
    }
    if ($id) {
        $stmt = db()->prepare('SELECT cover_path FROM albums WHERE id=?');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if (!$cover) $cover = $old;
        $q = db()->prepare('UPDATE albums SET title=?,artists=?,cover_path=?,links_json=?,status=?,sort_order=?,updated_at=NOW() WHERE id=?');
        $q->execute([$title, $artists, $cover, json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status, $order, $id]);
    } else {
        $q = db()->prepare('INSERT INTO albums(title,artists,cover_path,links_json,status,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())');
        $q->execute([$title, $artists, $cover, json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status, $order]);
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
if (isset($_POST['delete_id'])) {
    csrf_check();
    db()->prepare('DELETE FROM albums WHERE id=?')->execute([(int)$_POST['delete_id']]);
    redirect('albums.php');
}

$albums = db()->query('SELECT * FROM albums ORDER BY sort_order,created_at DESC')->fetchAll();
$links = $edit['links'] ?? [['name' => 'Spotify', 'url' => '']];

admin_page_open(t('albums.manage'), 'albums');
?>
<h1><?= e(t('albums.manage')) ?></h1>
<p class="subtitle"><?= e(t('albums.subtitle')) ?></p>

<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('albums.saved')) ?></p><?php endif; ?>

<div class="panel">
    <h2><?= e($edit ? t('albums.edit') : t('albums.add')) ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <label for="title"><?= e(t('albums.field.title')) ?> *</label>
        <input id="title" name="title" required value="<?= e($edit['title'] ?? '') ?>">
        <label for="artists"><?= e(t('albums.field.artists')) ?></label>
        <input id="artists" name="artists" value="<?= e($edit['artists'] ?? '') ?>">
        <label for="cover"><?= e(t('albums.field.cover')) ?></label>
        <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($edit['cover_path'])): ?>
            <p class="small"><?= e(t('albums.field.cover_current')) ?>: <?= e($edit['cover_path']) ?></p>
        <?php endif; ?>
        <label><?= e(t('albums.field.links')) ?></label>
        <div id="links">
            <?php foreach ($links as $l): ?>
                <div class="links">
                    <input name="link_name[]" placeholder="<?= e(t('link.placeholder.name')) ?>" value="<?= e($l['name'] ?? '') ?>">
                    <input name="link_url[]" placeholder="https://…" value="<?= e($l['url'] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="addLink()"><?= e(t('albums.field.add_link')) ?></button>
        <label for="sort_order"><?= e(t('albums.field.order')) ?></label>
        <input id="sort_order" type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? 0) ?>">
        <label for="status"><?= e(t('albums.field.status')) ?></label>
        <select id="status" name="status">
            <option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>><?= e(t('albums.status.draft')) ?></option>
            <option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= e(t('albums.status.published')) ?></option>
        </select>
        <p class="form-actions">
            <button type="submit"><?= e(t('albums.save')) ?></button>
            <?php if ($edit): ?><a class="btn" href="albums.php"><?= e(t('albums.cancel')) ?></a><?php endif; ?>
        </p>
    </form>
</div>

<h2><?= e(t('albums.existing')) ?></h2>
<table>
    <tr>
        <th><?= e(t('table.title')) ?></th>
        <th><?= e(t('table.artists')) ?></th>
        <th><?= e(t('table.status')) ?></th>
        <th><?= e(t('table.actions')) ?></th>
    </tr>
    <?php foreach ($albums as $a): ?>
    <tr>
        <td><?= e($a['title']) ?></td>
        <td><?= e($a['artists']) ?></td>
        <td class="status"><?= e($a['status'] === 'published' ? t('albums.status.published') : t('albums.status.draft')) ?></td>
        <td class="actions">
            <a class="rowlink" href="?edit=<?= $a['id'] ?>"><?= e(t('action.edit')) ?></a> ·
            <form method="post" onsubmit="return confirm('<?= e(t('albums.confirm_delete')) ?>')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                <button type="submit"><?= e(t('action.delete')) ?></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<script>
const LINK_NAME_PLACEHOLDER = <?= json_encode(t('link.placeholder.name'), JSON_UNESCAPED_UNICODE) ?>;
function addLink() {
    const row = document.createElement('div');
    row.className = 'links';
    row.innerHTML = '<input name="link_name[]" placeholder="' + LINK_NAME_PLACEHOLDER + '"><input name="link_url[]" placeholder="https://…">';
    document.getElementById('links').appendChild(row);
}
</script>
<?php admin_page_close();
