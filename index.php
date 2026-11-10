<?php
/**
 * Tony Ford's Free Image Gallery - front controller
 * by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com
 * Free for non-commercial use only. See LICENSE.txt.
 */
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$route = (string)($_GET['r'] ?? '');
$route = trim(rawurldecode($route), '/');
if (strlen($route) > 200 || preg_match('/[\x00-\x1f\\\\]/', $route) || str_contains($route, '..')) not_found();
$parts = $route === '' ? [] : explode('/', $route);
$first = $parts[0] ?? '';
$arg = $parts[1] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) { http_response_code(405); exit; }

switch ($first) {
    case '__probe':
        header('Content-Type: text/plain'); echo 'tfg-ok'; exit;
    case '':
        view('home');
    case 'categories':
        view('categories');
    case 'category':
        if ($arg === '' || count($parts) > 2) not_found();
        view('category', ['slug' => $arg]);
    case 'image':
        if (!preg_match('/^(\d{1,10})(?:-[a-z0-9-]*)?$/', $arg, $m) || count($parts) > 2) not_found();
        view('image', ['id' => (int)$m[1]]);
    case 'tags':
        view('tags');
    case 'tag':
        if ($arg === '' || count($parts) > 2) not_found();
        view('tag', ['slug' => $arg]);
    case 'search':
        view('search');
    case 'about':
        view('about');
    case 'popular':
        view('popular');
    case 'favorites':
        view('favorites');
    case 'user':
        if ($arg === '' || count($parts) > 2) not_found();
        view('user', ['username' => $arg]);
    case 'login':
        view('login');
    case 'register':
        view('register');
    case 'logout':
        require_post(); logout_user(); start_session(); flash('ok', 'You have been logged out.'); redirect('');
    case 'upload':
        view('upload');
    case 'edit':
        if (!ctype_digit($arg) || count($parts) > 2) not_found();
        view('edit', ['id' => (int)$arg]);
    case 'account':
        view('account');
    case 'admin':
        $sub = $arg === '' ? 'dashboard' : $arg;
        if (!in_array($sub, ['dashboard', 'images', 'categories', 'users', 'settings', 'themes'], true) || count($parts) > 2) not_found();
        require_admin();
        view('admin/' . $sub);
    case 'api':
        define('TFG_API', 1);
        if (!in_array($arg, ['upload', 'like', 'theme'], true) || count($parts) > 2) json_out(['ok' => false, 'error' => 'Unknown endpoint'], 404);
        require ROOT . '/pages/api/' . $arg . '.php';
        exit;
    case 'robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /login\nDisallow: /register\nDisallow: /upload\nSitemap: " . abs_url('sitemap.xml') . "\n";
        exit;
    case 'sitemap.xml':
        require ROOT . '/pages/sitemap.php';
        exit;
    default:
        not_found();
}
