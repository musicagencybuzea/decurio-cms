<?php
declare(strict_types=1);

/**
 * Statistics beacon (v1.4 phase 2).
 * Public endpoint: records an anonymous click on a streaming link.
 * NO IP, NO user-agent, NO cookies — GDPR-safe by design.
 * Usage: navigator.sendBeacon('click.php?album=12&src=release&platform=Spotify&action=PLAY&pos=1')
 */
require __DIR__ . '/app/bootstrap.php';

http_response_code(204);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

$albumId = (int)($_GET['album'] ?? 0);
if ($albumId < 0 || $albumId > 4294967295) $albumId = 0;
if ($albumId > 0) {
    try {
        $q = db()->prepare('SELECT id FROM albums WHERE id=?');
        $q->execute([$albumId]);
        if (!$q->fetch()) $albumId = 0;
    } catch (Throwable $e) {
        return;
    }
}

$source = ($_GET['src'] ?? '') === 'release' ? 'release' : 'main';
$platform = mb_substr(trim((string)($_GET['platform'] ?? 'unknown')), 0, 80);
if ($platform === '') $platform = 'unknown';
$action = mb_substr(trim((string)($_GET['action'] ?? '')), 0, 30);
if (mb_strlen($action) > 12) $action = ''; // custom actions are capped at 12 chars upstream
$position = (int)($_GET['pos'] ?? 0);
if ($position < 1 || $position > 200) $position = null;

try {
    $st = db()->prepare(
        'INSERT INTO link_clicks (album_id, source, platform, action_word, position) VALUES (?,?,?,?,?)'
    );
    $st->execute([
        $albumId > 0 ? $albumId : null,
        $source,
        $platform,
        $action !== '' ? $action : null,
        $position,
    ]);
} catch (Throwable $e) {
    // silently ignore (table missing before migration, transient error…)
}
