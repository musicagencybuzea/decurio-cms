<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete_id'])) {
        db()->prepare('DELETE FROM platforms WHERE id=?')->execute([(int)$_POST['delete_id']]);
        redirect('platforms.php?saved=1');
    }
    if (isset($_POST['new_name'])) {
        $name = trim((string)$_POST['new_name']);
        if ($name === '') fail(t('platforms.err.name'));
        $max = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM platforms')->fetchColumn();
        db()->prepare('INSERT INTO platforms(name,preferred,custom,has_api,active,sort_order) VALUES(?,?,?,?,1,?)')
            ->execute([$name, isset($_POST['new_preferred']) ? 1 : 0, isset($_POST['new_custom']) ? 1 : 0, isset($_POST['new_has_api']) ? 1 : 0, $max + 10]);
        redirect('platforms.php?saved=1');
    }
    if (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $name = trim((string)$_POST['name']);
        if ($name === '') fail(t('platforms.err.name'));
        db()->prepare('UPDATE platforms SET name=?, preferred=?, custom=?, has_api=?, active=? WHERE id=?')
            ->execute([$name, isset($_POST['preferred']) ? 1 : 0, isset($_POST['custom']) ? 1 : 0, isset($_POST['has_api']) ? 1 : 0, isset($_POST['active']) ? 1 : 0, $id]);
        redirect('platforms.php?saved=1');
    }
}
$platforms = db()->query('SELECT * FROM platforms ORDER BY sort_order, name')->fetchAll();

admin_page_open(t('platforms.title'), 'platforms');
?>
<h1><?= e(t('platforms.title')) ?></h1>
<p class="subtitle"><?= e(t('platforms.subtitle')) ?></p>
<?php if (isset($_GET['saved'])): ?><p class="notice success"><?= e(t('platforms.saved')) ?></p><?php endif; ?>

<div class="panel">
    <h2><?= e(t('platforms.add')) ?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="new_name"><?= e(t('platforms.name')) ?> *</label>
        <input id="new_name" name="new_name" required maxlength="80">
        <p class="check-row"><input type="checkbox" id="new_preferred" name="new_preferred" value="1"><label class="inline" for="new_preferred"><?= e(t('platforms.preferred')) ?></label></p>
        <p class="check-row"><input type="checkbox" id="new_custom" name="new_custom" value="1"><label class="inline" for="new_custom"><?= e(t('platforms.custom')) ?></label></p>
        <p class="check-row"><input type="checkbox" id="new_has_api" name="new_has_api" value="1"><label class="inline" for="new_has_api"><?= e(t('platforms.has_api')) ?></label></p>
        <p class="form-actions"><button type="submit"><?= e(t('platforms.add')) ?></button></p>
    </form>
</div>

<h2><?= e(t('platforms.existing')) ?></h2>
<table>
    <?php foreach ($platforms as $p): ?>
    <tr>
        <td colspan="2">
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="edit_id" value="<?= (int)$p['id'] ?>">
                <div class="inline-form">
                    <input name="name" value="<?= e($p['name']) ?>" maxlength="80" style="max-width:260px">
                    <p class="check-row"><input type="checkbox" id="pref_<?= (int)$p['id'] ?>" name="preferred" value="1" <?= !empty($p['preferred']) ? 'checked' : '' ?>><label class="inline" for="pref_<?= (int)$p['id'] ?>"><?= e(t('platforms.preferred')) ?></label>
                    <input type="checkbox" id="cust_<?= (int)$p['id'] ?>" name="custom" value="1" <?= !empty($p['custom']) ? 'checked' : '' ?>><label class="inline" for="cust_<?= (int)$p['id'] ?>"><?= e(t('platforms.custom')) ?></label>
                    <input type="checkbox" id="api_<?= (int)$p['id'] ?>" name="has_api" value="1" <?= !empty($p['has_api']) ? 'checked' : '' ?>><label class="inline" for="api_<?= (int)$p['id'] ?>"><?= e(t('platforms.has_api')) ?></label>
                    <input type="checkbox" id="act_<?= (int)$p['id'] ?>" name="active" value="1" <?= !empty($p['active']) ? 'checked' : '' ?>><label class="inline" for="act_<?= (int)$p['id'] ?>"><?= e(t('platforms.active')) ?></label></p>
                    <p class="small"><?= !empty($p['has_api']) ? '✓ ' . e(t('find.btn')) . ' ✓' : '' ?></p>
                    <button type="submit"><?= e(t('albums.save')) ?></button>
                </div>
            </form>
        </td>
        <td class="actions">
            <form method="post" onsubmit="return confirm('<?= e(t('platforms.confirm_delete')) ?>')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
                <button type="submit"><?= e(t('action.delete')) ?></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php admin_page_close();
