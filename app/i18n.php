<?php
declare(strict_types=1);

const SUPPORTED_LANGS = ['en', 'de', 'fr'];

function lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    $requested = $_GET['lang'] ?? '';
    if (in_array($requested, SUPPORTED_LANGS, true)) {
        $_SESSION['lang'] = $requested;
    }
    $lang = $_SESSION['lang'] ?? 'en';
    if (!in_array($lang, SUPPORTED_LANGS, true)) $lang = 'en';
    return $lang;
}

function lang_url(string $target): string
{
    $qs = $_GET;
    unset($qs['edit'], $qs['delete']);
    $qs['lang'] = $target;
    return htmlspecialchars(basename($_SERVER['SCRIPT_NAME']) . '?' . http_build_query($qs), ENT_QUOTES, 'UTF-8');
}

function t(string $key): string
{
    static $dict = null;
    $dict ??= require __DIR__ . '/translations.php';
    $l = lang();
    return $dict[$l][$key] ?? $dict['en'][$key] ?? $key;
}
