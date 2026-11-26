<?php
declare(strict_types=1);
// 🩺 debug.php — PAGE DE DIAGNOSTIC TEMPORAIRE (decurio CMS)
// Ouvre https://links.decur.io/debug.php dans le navigateur.
// Copie-colle TOUT le résultat dans la conversation.
// ⚠️ SUPPRIME CE FICHIER SUR LE SERVEUR just après (voir FileZilla).
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><meta charset="utf-8">
<title>Diagnostic decurio</title>
<body style="font-family:sans-serif;background:#f0f0f0;color:#141414;padding:24px;max-width:900px">
<h1>🩺 Diagnostic decurio</h1>
<pre style="font-family:monospace;background:#ffffff;border:1px solid #d6b399;padding:16px;white-space:pre-wrap">
<?php
echo 'PHP version : ' . PHP_VERSION . "\n\n";

// 1) Fichiers présents sur le serveur ?
echo "=== FICHIERS ===\n";
$files = [
    'app/bootstrap.php', 'app/i18n.php', 'app/translations.php', 'app/ui.php',
    'app/linkfinder.php', 'app/release-page.php',
    'index.php', '.htaccess',
    'admin/login.php', 'admin/dashboard.php', 'admin/albums.php', 'admin/preview.php',
    'admin/releases.php', 'admin/library.php', 'admin/settings.php',
    'admin/_section_platforms.php', 'admin/_section_stickers.php', 'admin/_section_music.php',
];
foreach ($files as $f) {
    if (is_file(__DIR__ . '/' . $f)) {
        echo "  OK        $f  (" . filesize(__DIR__ . '/' . $f) . " octets)\n";
    } else {
        echo "  MANQUANT  $f\n";
    }
}
echo "\n";

// 2) Chargement des fichiers partagés (l'admin et le site public les utilisent tous)
echo "=== CHARGEMENT DES MODULES ===\n";
require_once __DIR__ . '/app/i18n.php';
echo "  app/i18n.php        : OK\n";
try {
    $tx = require __DIR__ . '/app/translations.php';
    if (is_array($tx)) {
        echo '  app/translations.php: OK — langues : ' . implode(', ', array_keys($tx)) . "\n";
        foreach ($tx as $lang => $arr) {
            echo '      * ' . $lang . ' : ' . count($arr) . " mots\n";
        }
    } else {
        echo "  ⚠️ app/translations.php a renvoyé un contenu inattendu\n";
    }
} catch (Throwable $e) {
    echo '  ❌ app/translations.php : ' . get_class($e) . ' ligne ' . $e->getLine() . ' : ' . $e->getMessage() . "\n";
}
try {
    $_SERVER['SCRIPT_NAME'] = '/diagnostic';
    require __DIR__ . '/app/ui.php';
    echo "  app/ui.php          : OK\n";
} catch (Throwable $e) {
    echo '  ❌ app/ui.php : ' . get_class($e) . ' ligne ' . $e->getLine() . ' : ' . $e->getMessage() . "\n";
}
// La version de ui.php est-elle la bonne (v1.4) ?
echo "  ui.php version      : "
    . (defined('LINK_ACTIONS') ? 'NOUVELLE (v1.4) ✔' : 'ANCIENNE ❌ (fichier démodé sur le serveur !)') . "\n";
echo "\n";

// 3) Base de données : connexion + tiroirs de la table albums
echo "=== BASE DE DONNÉES ===\n";
try {
    $config = require __DIR__ . '/app/config/config.php';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "  Connexion base      : OK\n";
    $cols = $pdo->query('SHOW COLUMNS FROM albums')->fetchAll(PDO::FETCH_COLUMN);
    echo "  Table 'albums'      : " . count($cols) . " colonnes\n";
    foreach (['release_date', 'extra_socials_json', 'publish_at', 'release_number', 'subtitle'] as $need) {
        echo '      * ' . str_pad($need, 22) . (in_array($need, $cols, true) ? "présent ✔" : "❌ MANQUANT") . "\n";
    }
    if (!in_array('extra_socials_json', $cols, true)) {
        echo "\n  👉 ACTION : dans phpMyAdmin, exécute :\n"
            . "     ALTER TABLE albums ADD COLUMN extra_socials_json TEXT NULL DEFAULT NULL AFTER socials_json;\n";
    }
    $n = $pdo->query('SELECT COUNT(*) FROM albums')->fetchColumn();
    echo "  Albums en base      : $n\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "  Tables              : " . implode(', ', $tables) . "\n";
} catch (Throwable $e) {
    echo '  ❌ ' . get_class($e) . ' : ' . $e->getMessage() . "\n";
}
?>
</pre>
<p>✅ C'est tout ! Maintenant : <strong>copie tout le texte ci-dessus</strong>, et <strong>supprime debug.php</strong> du serveur (FileZilla : clic droit → Delete).</p>
</body>
