<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Rows reordering (drag & drop)
    if (isset($_POST['reorder_ids'])) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$_POST['reorder_ids']))));
        $q = db()->prepare('UPDATE albums SET sort_order=? WHERE id=?');
        foreach ($ids as $i => $id) $q->execute([$i * 10, $id]);
        redirect('releases.php?saved=1');
    }

    // Deletion
    if (isset($_POST['delete_id'])) {
        db()->prepare('DELETE FROM albums WHERE id=?')->execute([(int)$_POST['delete_id']]);
        redirect('releases.php');
    }
}

$now = date('Y-m-d H:i:s');
$albums = db()->prepare('SELECT * FROM albums ORDER BY sort_order ASC, created_at DESC');
$albums->execute();
$albums = $albums->fetchAll();

admin_page_open(t('releases.title'), 'releases');
?>
<h1><?= e(t('releases.title')) ?></h1>
<p class="subtitle"><?= e(t('releases.subtitle')) ?></p>

<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('albums.saved')) ?></p><?php endif; ?>

<p class="small"><?= e(t('albums.hint.reorder')) ?></p>
<table>
    <tbody id="release-rows">
    <?php foreach ($albums as $a):
        $status = $a['status'];
        $live = $status === 'published' || ($status === 'scheduled' && $a['publish_at'] !== null && strtotime((string)$a['publish_at']) <= time());
        $pageUrl = $live && ($a['release_number'] ?? '') !== '' ? release_url($a) : '';
        ?>
    <tr data-id="<?= (int)$a['id'] ?>">
        <td class="drag-cell">&#8942;&#8942;</td>
        <td class="status"><?= e($a['release_number'] ?? '') ?></td>
        <td>
            <?php if (!empty($a['cover_path'])): ?><img class="row-cover" src="../<?= e($a['cover_path']) ?>" alt=""><?php endif; ?>
            <?= e($a['title']) ?>
            <?php if (!empty($a['subtitle'])): ?><br><span class="small"><?= e($a['subtitle']) ?></span><?php endif; ?>
        </td>
        <td class="small"><?= e($a['artists']) ?></td>
        <td class="status">
            <?= e($status === 'published' ? t('albums.status.published') : ($status === 'scheduled' ? t('albums.status.scheduled') : t('albums.status.draft'))) ?>
            <?php if ($status === 'scheduled' && $a['publish_at'] !== null): ?><br><span class="small"><?= e(substr((string)$a['publish_at'], 0, 16)) ?></span><?php endif; ?>
        </td>
        <td class="actions">
            <a class="rowlink" href="albums.php?edit=<?= (int)$a['id'] ?>"><?= e(t('action.edit')) ?></a>
            <?php if ($pageUrl !== ''): ?>
                &middot; <a class="rowlink" href="../<?= e(ltrim($pageUrl, '/')) ?>" target="_blank" rel="noopener"><?= e($pageUrl) ?></a>
            <?php else: ?>
                &middot; <a class="rowlink" href="../?preview=<?= (int)$a['id'] ?>&lang=<?= e(lang()) ?>" target="_blank" rel="noopener"><?= e(t('albums.preview')) ?> &nearr;</a>
            <?php endif; ?>
            &middot;
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
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

// Reordering: drag & drop, with up/down fallback if the CDN is unreachable
function submitOrder() {
    const ids = [...document.querySelectorAll('#release-rows tr')].map(tr => tr.dataset.id);
    const f = document.createElement('form');
    f.method = 'post';
    f.innerHTML = '<input name="csrf" value="' + CSRF + '"><input name="reorder_ids" value="' + ids.join(',') + '">';
    document.body.appendChild(f); f.submit();
}
if (window.Sortable) {
    new Sortable(document.getElementById('release-rows'), { handle: '.drag-cell', animation: 150, onEnd: submitOrder });
} else {
    document.querySelectorAll('#release-rows tr').forEach(tr => {
        const cell = tr.querySelector('.drag-cell');
        const up = document.createElement('button'); up.type = 'button'; up.textContent = '\u2191';
        const down = document.createElement('button'); down.type = 'button'; down.textContent = '\u2193';
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
</script>
<?php admin_page_close();
