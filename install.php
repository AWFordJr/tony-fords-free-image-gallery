<?php
/**
 * Tony Ford's Free Image Gallery - Web Installer (entry point)
 * by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com
 * Free for non-commercial use only. See LICENSE.txt.
 *
 * This tiny file is deliberately written in old-style PHP so that even a very old server can
 * show a friendly message instead of a cryptic "syntax error". The real installer is inc/installer.php.
 */
$tfgProblems = array();
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $tfgProblems[] = 'This server runs PHP ' . PHP_VERSION . ' but the gallery needs <strong>PHP 8.0 or newer</strong>. '
        . 'In your hosting control panel (cPanel &rarr; "Select PHP Version" / "MultiPHP Manager") choose PHP 8.1 or newer, then reload this page.';
}
foreach (array('mbstring' => 'mbstring', 'session' => 'session', 'pdo' => 'PDO') as $ext => $label) {
    if (!extension_loaded($ext)) {
        $tfgProblems[] = 'The PHP extension <strong>' . $label . '</strong> is not enabled. Enable it in your hosting control panel (or php.ini), then reload this page.';
    }
}
if ($tfgProblems) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Server check - Tony Ford\'s Free Image Gallery</title>'
        . '<body style="font:16px/1.5 system-ui,Arial,sans-serif;background:#0e1220;color:#e8ecf8;margin:0;display:grid;place-items:center;min-height:100vh">'
        . '<div style="max-width:620px;padding:24px"><h1 style="margin-top:0">Almost there - your server needs a small change</h1><ul>';
    foreach ($tfgProblems as $p) echo '<li style="margin:10px 0">' . $p . '</li>';
    echo '</ul><p style="opacity:.6;font-size:.85em">Tony Ford\'s Free Image Gallery by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com</p></div></body>';
    exit;
}
require __DIR__ . '/inc/installer.php';
