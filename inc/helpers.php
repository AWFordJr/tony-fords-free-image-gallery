<?php
declare(strict_types=1);

/* ---------- boot ---------- */
function app_boot(): void
{
    mb_internal_encoding('UTF-8');
    if (defined('TFG_DEBUG') && TFG_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }
    date_default_timezone_set('UTC');
    send_security_headers();
    start_session();
    $tz = (string)setting('timezone', 'UTC');
    if (in_array($tz, DateTimeZone::listIdentifiers(), true)) date_default_timezone_set($tz);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function send_security_headers(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; font-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('tfg_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    // If the server's default session folder is missing or not writable (common on small hosts), use a private temp folder.
    $sp = session_save_path();
    $sp = $sp === '' ? (string)ini_get('session.save_path') : $sp;
    if (($p = strrpos($sp, ';')) !== false) $sp = substr($sp, $p + 1);
    if (!is_dir($sp) || !is_writable($sp)) {
        $alt = rtrim(sys_get_temp_dir(), '/\\') . '/tfg_sessions_' . substr(md5(__DIR__), 0, 8);
        if (!is_dir($alt)) @mkdir($alt, 0700, true);
        if (is_dir($alt) && is_writable($alt)) session_save_path($alt);
    }
    if (!@session_start()) {
        error_log('TFG: session_start failed - check session.save_path');
    }
}

/* ---------- escaping & output ---------- */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Visible credit with Tony's email and sites. The legal wording stays in TFG_CREDIT. */
function credit_html(): string
{
    return e("Tony Ford's Free Image Gallery by Anthony W. Ford, Jr. (Tony Ford)")
        . ' - <a href="mailto:awfordjr@gmail.com">awfordjr@gmail.com</a>'
        . ' · <a href="https://TonyFord.us" rel="noopener noreferrer">TonyFord.us</a>'
        . ' · <a href="https://TonyFord.vip" rel="noopener noreferrer">TonyFord.vip</a>';
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

/* ---------- database ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_TIMEOUT => 8,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (Throwable $e) {
            error_log('TFG DB connect failed: ' . $e->getMessage());
            if (!headers_sent()) { http_response_code(503); header('Content-Type: text/html; charset=utf-8'); header('Retry-After: 60'); }
            $why = extension_loaded('pdo_mysql') ? 'The gallery could not connect to its database. Check the DB_ settings in <code>config.php</code> and make sure MySQL/MariaDB is running.'
                : 'The PHP extension <code>pdo_mysql</code> is not enabled on this server. Ask your host to enable it (or enable it in php.ini).';
            exit('<!doctype html><meta charset="utf-8"><title>Gallery unavailable</title><body style="font:16px system-ui;background:#0e1220;color:#e8ecf8;display:grid;place-items:center;min-height:100vh;margin:0"><div style="max-width:560px;padding:24px"><h1>Gallery temporarily unavailable</h1><p>' . $why . '</p><p style="opacity:.6;font-size:.85em">Turn on <code>TFG_DEBUG</code> in config.php for technical details in the server error log.</p></div></body>');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function q_row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function q_val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

/** Escape a user string for use inside a LIKE pattern. */
function like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/* ---------- settings ---------- */
function settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        foreach (q_all('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache;
}
function setting(string $k, $default = '')
{
    $s = settings_all();
    return array_key_exists($k, $s) ? $s[$k] : $default;
}
function set_setting(string $k, string $v): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
    settings_all(true);
}
function default_settings(): array
{
    return [
        'site_title' => "Tony Ford's Free Image Gallery",
        'tagline' => 'Share your pictures with the world.',
        'mode' => 'single',                 // single | open
        'per_page' => '30',
        'max_upload_mb' => '15',
        'max_pixels_mp' => '60',
        'daily_upload_limit' => '0',
        'moderation' => '0',
        'allow_comments' => '1',
        'allow_guest_likes' => '1',
        'pretty_urls' => '1',
        'theme' => 'classic-dark',
        'allow_visitor_themes' => '1',
        'effects_default' => '1',
        'layout' => 'justified',
        'slideshow_seconds' => '4',
        'show_exif' => '1',
        'allow_download' => '1',
    ];
}

/* ---------- URLs ---------- */
function asset(string $p): string
{
    $f = ROOT . '/assets/' . ltrim($p, '/');
    $v = is_file($f) ? filemtime($f) : TFG_VERSION;
    return base_path() . '/assets/' . ltrim($p, '/') . '?v=' . $v;
}
function upload_url(string $rel): string
{
    return base_path() . '/uploads/' . ltrim($rel, '/');
}
/** Route URL, e.g. url('category/nature') or url('search', ['q'=>'x']) */
function url(string $route = '', array $query = []): string
{
    $route = trim($route, '/');
    $pretty = setting('pretty_urls', '1') === '1';
    if ($pretty) {
        $u = base_path() . '/' . $route;
        if ($route === '') $u = base_path() . '/';
        if ($query) $u .= '?' . http_build_query($query);
        return $u;
    }
    $params = [];
    if ($route !== '') $params['r'] = $route;
    $params += $query;
    return base_path() . '/index.php' . ($params ? '?' . http_build_query($params) : '');
}
function abs_url(string $route = '', array $query = []): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . $host . url($route, $query);
}
function redirect(string $route = '', array $query = []): never
{
    header('Location: ' . url($route, $query));
    exit;
}
function redirect_to(string $location): never
{
    header('Location: ' . $location);
    exit;
}
/** Only allow same-site relative redirects. */
function safe_next(?string $next): string
{
    $next = (string)$next;
    if ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\')
        && !preg_match('/[\x00-\x1f]/', $next)) {
        return $next;
    }
    return url('');
}
function current_path_url(): string
{
    return (string)($_SERVER['REQUEST_URI'] ?? url(''));
}

/* ---------- flash ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}
function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || $t === '' || !hash_equals(csrf_token(), $t)) {
        if (is_api()) json_out(['ok' => false, 'error' => 'Security token expired. Reload the page and try again.'], 419);
        http_response_code(419);
        render_error(419, 'Security token expired', 'Your session token did not match. Go back, reload the page and try again.');
    }
}
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        if (is_api()) json_out(['ok' => false, 'error' => 'POST required'], 405);
        http_response_code(405);
        render_error(405, 'Method not allowed', 'This action must be sent as a form.');
    }
    csrf_check();
}
function is_api(): bool { return defined('TFG_API'); }

/* ---------- misc helpers ---------- */
function slugify(string $s, int $max = 80): string
{
    $s = trim($s);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') $s = $t;
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if (strlen($s) > $max) $s = rtrim(substr($s, 0, $max), '-');
    return $s;
}
function human_size(int $b): string
{
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0; $f = (float)$b;
    while ($f >= 1024 && $i < 4) { $f /= 1024; $i++; }
    return ($i === 0 ? (string)(int)$f : number_format($f, 1)) . ' ' . $u[$i];
}
function ago(string $dt): string
{
    $t = strtotime($dt . ' UTC');
    if (!$t) return $dt;
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return (int)($d / 60) . ' min ago';
    if ($d < 86400) return (int)($d / 3600) . ' h ago';
    if ($d < 86400 * 30) return (int)($d / 86400) . ' d ago';
    return date('M j, Y', $t);
}
function fmt_date(string $dt, string $f = 'M j, Y'): string
{
    $t = strtotime($dt . ' UTC');
    return $t ? date($f, $t) : $dt;
}
function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}
function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '') return 0;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}
function server_upload_limit(): int
{
    $a = ini_bytes((string)ini_get('upload_max_filesize'));
    $b = ini_bytes((string)ini_get('post_max_size'));
    $c = array_filter([$a, $b]);
    return $c ? min($c) : 0;
}
function max_upload_bytes(): int
{
    $mine = max(1, (int)setting('max_upload_mb', '15')) * 1048576;
    $srv = server_upload_limit();
    return $srv > 0 ? min($mine, $srv) : $mine;
}
function pagination_info(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / max(1, $perPage)));
    $page = max(1, min($page, $pages));
    return ['total' => $total, 'per' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}
function current_page_num(): int
{
    $p = $_GET['page'] ?? 1;
    return is_numeric($p) ? max(1, (int)$p) : 1;
}
/** Build a pagination URL for the current route preserving query string. */
function page_url(string $route, array $query, int $page): string
{
    if ($page > 1) $query['page'] = $page; else unset($query['page']);
    return url($route, $query);
}

/* ---------- rendering ---------- */
function view(string $page, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $file = ROOT . '/pages/' . $page . '.php';
    if (!is_file($file)) { render_error(404, 'Not found', 'That page does not exist.'); }
    $__page = $page;
    ob_start();
    include $file;
    $content = ob_get_clean();
    include ROOT . '/pages/layout.php';
    exit;
}
function render_error(int $code, string $title, string $msg = ''): never
{
    http_response_code($code);
    $pageTitle = $title;
    ob_start();
    echo '<section class="center-card"><h1>' . e($title) . '</h1><p class="muted">' . e($msg) . '</p><p><a class="btn" href="' . e(url('')) . '">Back to the gallery</a></p></section>';
    $content = ob_get_clean();
    $__page = 'error';
    if (defined('TFG_INSTALLING')) { echo $content; exit; }
    include ROOT . '/pages/layout.php';
    exit;
}
function not_found(): never { render_error(404, 'Not found', 'We could not find what you were looking for.'); }
