<?php
/**
 * Tony Ford's Free Image Gallery
 * by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com
 * Free for non-commercial use only. See LICENSE.txt.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Tony Ford\'s Free Image Gallery needs PHP 8.0 or newer.');
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('TFG_VERSION', '1.0.0');
define('ROOT', dirname(__DIR__));
define('UPLOAD_DIR', ROOT . '/uploads');
define('TFG_CREDIT', "Tony Ford's Free Image Gallery by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com");

/** Base path of the install (works in a sub-folder too). */
function base_path(): string
{
    if (defined('BASE_URL_OVERRIDE') && BASE_URL_OVERRIDE !== '') {
        return rtrim((string)BASE_URL_OVERRIDE, '/');
    }
    $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return $d === '/' || $d === '.' ? '' : rtrim($d, '/');
}

if (!defined('TFG_INSTALLING')) {
    if (!is_file(ROOT . '/config.php')) {
        if (is_file(ROOT . '/install.php')) {
            header('Location: ' . base_path() . '/install.php');
            exit;
        }
        http_response_code(503);
        exit('<!doctype html><meta charset="utf-8"><title>Not set up</title><body style="font:16px system-ui;padding:40px;background:#0e1220;color:#e8ecf8"><h1>Gallery is not set up</h1><p><code>config.php</code> is missing. Upload <code>install.php</code> again (from the download) to run the installer, or restore your <code>config.php</code>.</p></body>');
    }
    require ROOT . '/config.php';
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/themes.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/images.php';
require __DIR__ . '/categories.php';
require __DIR__ . '/ui.php';

if (!defined('TFG_INSTALLING')) {
    app_boot();
}
