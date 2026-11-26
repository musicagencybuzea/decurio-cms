<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/ui.php';
require_login();

// ----- Filters (GET) -----
$periods = [7, 31, 90, 365, 1095];
$days = in_array((int)($_GET['days'] ?? 31), $periods, true) ? (int)$_GET['days'] : 31;
$scope = (int)($_GET['album'] ?? 0);
$scope = $scope > 0 ? $scope : 0;

$albums = db()->query('SELECT id, title, release_number FROM albums ORDER BY sort_order ASC, title ASC')->fetchAll();
$albumMap = [];
foreach ($albums as $a) $albumMap[(int)$a['id']] = $a;

$since = date('Y-m-d H:i:s', time() - $days * 86400);

// Shared WHERE: period + optional release scope
$con = ['ts >= ?']; $par = [$since];
if ($scope > 0) { $con[] = 'album_id = ?'; $par[] = $scope; }
$where = 'WHERE ' . implode(' AND ', $con);

function q_count(string $where, array $par, string $extra): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM link_clicks $where $extra");
    $st->execute($par);
    return (int)$st->fetchColumn();
}

function q_rows(string $select, string $where, array $par, string $group, string $order, int $limit = 0): array
{
    $sql = "SELECT $select FROM link_clicks $where GROUP BY $group ORDER BY $order";
    if ($limit > 0) $sql .= " LIMIT $limit";
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

$totalAll = q_count($where, $par, '');
$totalMain = q_count($where, $par, "AND source='main'");
$totalRel = q_count($where, $par, "AND source='release'");

// Platform breakdown, split by source
$byPlatform = q_rows(
    "platform, source, COUNT(*) AS c",
    $where, $par, 'platform, source', 'c DESC'
);
// Combined per-platform totals (both pages summed — Spotify vs the label’s own website, etc.)
$allPlatforms = q_rows(
    "platform, COUNT(*) AS c",
    $where, $par, 'platform', 'c DESC'
);
$maxAll = 0;
foreach ($allPlatforms as $r) $maxAll = max($maxAll, (int)$r['c']);
$mainStats = [];
$relStats = [];
$maxMain = 0; $maxRel = 0;
foreach ($byPlatform as $r) {
    if ($r['source'] === 'main') { $mainStats[] = $r; $maxMain = max($maxMain, (int)$r['c']); }
    else { $relStats[] = $r; $maxRel = max($maxRel, (int)$r['c']); }
}

// Per-release ranking (which release got the most clicks overall)
$topReleases = q_rows(
    "album_id, COUNT(*) AS c",
    $where . ' AND album_id IS NOT NULL',
    $par, 'album_id', 'c DESC', 8
);

// When a single release is selected: detailed per-link view (platform + action + position)
$linkDetails = [];
$maxDetail = 0;
if ($scope > 0) {
    $linkDetails = q_rows(
        "platform, action_word, position, source, COUNT(*) AS c",
        $where, $par, 'platform, action_word, position, source', 'c DESC'
    );
    foreach ($linkDetails as $r) $maxDetail = max($maxDetail, (int)$r['c']);
}
$anyData = $totalAll > 0;

function bars_for(array $rows, int $max): string
{
    if (!$rows) return '';
    $out = '<div class="stat-block">';
    foreach ($rows as $r) {
        $c = (int)$r['c'];
        $w = $max > 0 ? max(2, (int)round($c / $max * 100)) : 2;
        $name = $r['platform'] !== '' ? $r['platform'] : '?';
        $out .= '<div class="sbar"><div class="sbar-row">'
            . '<span class="sbar-name">' . e($name) . '</span>'
            . '<span class="sbar-count">' . $c . '</span></div>'
            . '<div class="sbar-track"><div class="sbar-fill" style="width:' . $w . '%"></div></div></div>';
    }
    $out .= '</div>';
    return $out;
}
?>
<?php admin_page_open(t('nav.stats'), 'stats'); ?>
<h1><?= e(t('stats.title')) ?></h1>
<p class="subtitle"><?= e(t('stats.subtitle')) ?></p>

<form class="stats-filter" method="get" action="stats.php">
    <label>
        <span><?= e(t('stats.period')) ?></span>
        <select name="days">
            <option value="7"<?= $days === 7 ? ' selected' : '' ?>><?= e(t('stats.period.week')) ?></option>
            <option value="31"<?= $days === 31 ? ' selected' : '' ?>><?= e(t('stats.period.month')) ?></option>
            <option value="90"<?= $days === 90 ? ' selected' : '' ?>><?= e(t('stats.period.quarter')) ?></option>
            <option value="365"<?= $days === 365 ? ' selected' : '' ?>><?= e(t('stats.period.year')) ?></option>
            <option value="1095"<?= $days === 1095 ? ' selected' : '' ?>><?= e(t('stats.period.3y')) ?></option>
        </select>
    </label>
    <label>
        <span><?= e(t('stats.scope')) ?></span>
        <select name="album">
            <option value="0"<?= $scope === 0 ? ' selected' : '' ?>><?= e(t('stats.all_releases')) ?></option>
            <?php foreach ($albums as $a): ?>
                <option value="<?= (int)$a['id'] ?>"<?= $scope === (int)$a['id'] ? ' selected' : '' ?>><?= e($a['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit"><?= e(t('stats.show')) ?></button>
</form>

<?php if (!$anyData): ?>
<p class="notice"><?= e(t('stats.no_data')) ?></p>
<?php else: ?>

<div class="cards">
    <div class="card"><span class="label"><?= e(t('stats.clicks_total')) ?></span><p class="num"><?= $totalAll ?></p></div>
    <div class="card"><span class="label"><?= e(t('stats.main_page')) ?></span><p class="num"><?= $totalMain ?></p></div>
    <div class="card"><span class="label"><?= e(t('stats.release_page')) ?></span><p class="num"><?= $totalRel ?></p></div>
</div>

<div class="cols2">
    <section class="stat-section">
        <h2><?= e(t('stats.by_platform_main')) ?></h2>
        <?= bars_for($mainStats, $maxMain) ?: '<p class="small">' . e(t('stats.no_data')) . '</p>' ?>
    </section>
    <section class="stat-section">
        <h2><?= e(t('stats.by_platform_release')) ?></h2>
        <?= bars_for($relStats, $maxRel) ?: '<p class="small">' . e(t('stats.no_data')) . '</p>' ?>
    </section>
</div>

<section class="stat-section">
    <h2><?= e(t('stats.by_platform_all')) ?></h2>
    <?= bars_for($allPlatforms, $maxAll) ?: '<p class="small">' . e(t('stats.no_data')) . '</p>' ?>
</section>

<?php if ($scope === 0 && $topReleases): ?>
<section class="stat-section">
    <h2><?= e(t('stats.top_releases')) ?></h2>
    <div class="stat-block">
        <?php $maxTop = max(array_map(fn($r) => (int)$r['c'], $topReleases)); foreach ($topReleases as $r): $a = $albumMap[(int)$r['album_id']] ?? null; $c = (int)$r['c']; $w = $maxTop > 0 ? max(2, (int)round($c / $maxTop * 100)) : 2; ?>
        <div class="sbar">
            <div class="sbar-row">
                <span class="sbar-name"><?= $a ? e($a['title']) : '#' . (int)$r['album_id'] ?></span>
                <span class="sbar-count"><?= $c ?></span>
            </div>
            <div class="sbar-track"><div class="sbar-fill" style="width:<?= $w ?>%"></div></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($scope > 0 && $linkDetails): ?>
<section class="stat-section">
    <h2><?= e(t('stats.per_link')) ?></h2>
    <table class="list">
        <thead><tr>
            <th><?= e(t('stats.platform')) ?></th>
            <th><?= e(t('stats.action')) ?></th>
            <th><?= e(t('stats.source')) ?></th>
            <th><?= e(t('stats.position')) ?></th>
            <th><?= e(t('stats.clicks_total')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($linkDetails as $r): ?>
            <tr>
                <td><?= e($r['platform'] !== '' ? $r['platform'] : '?') ?></td>
                <td><?= e((string)($r['action_word'] ?? '')) ?></td>
                <td><?= e($r['source'] === 'main' ? t('stats.main_page') : t('stats.release_page')) ?></td>
                <td><?= $r['position'] !== null ? '#' . (int)$r['position'] : '–' ?></td>
                <td><strong><?= (int)$r['c'] ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
<?php endif; ?>

<?php admin_page_close(); ?>
