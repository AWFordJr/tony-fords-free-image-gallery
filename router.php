<?php
// Router for PHP's built-in web server (handy for local testing):
//   php -S 127.0.0.1:8080 router.php
// Real sites should use Apache or nginx (see README).
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);
if (str_contains($path, "\0") || str_contains($path, '..')) { http_response_code(400); exit; }
$file = __DIR__ . $path;
if ($path === '/install.php' && is_file($file)) { return false; }   // the installer (it renames itself when done)
if ($path === '/index.php') { return false; }
if ($path !== '/' && is_file($file)) {
    // Only static files from assets/ and uploads/ may be served; never scripts or dot-files.
    $base = basename($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $ok = preg_match('#^/(assets|uploads)/#', $path) && $base[0] !== '.'
        && !in_array($ext, ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phps', 'htaccess', 'cgi', 'pl', 'py', 'sh'], true);
    if (!$ok) { http_response_code(404); exit; }
    return false; // serve static file
}
$_GET['r'] = trim($path, '/');
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
