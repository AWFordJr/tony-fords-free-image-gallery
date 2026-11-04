<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $loaded = false, $user = null;
    if (!$loaded) {
        $loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if ($id) {
            $u = q_row('SELECT * FROM users WHERE id = ?', [(int)$id]);
            if ($u && $u['status'] === 'active') $user = $u;
            else unset($_SESSION['uid']);
        }
    }
    return $user;
}
function reset_current_user_cache(): void { /* static cache lives per request; nothing to do */ }

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}
function mode(): string { return setting('mode', 'single') === 'open' ? 'open' : 'single'; }
function registration_open(): bool { return mode() === 'open'; }

/** May the current user post images? */
function can_upload(): bool
{
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    return mode() === 'open';
}
function can_edit_image(array $img): bool
{
    $u = current_user();
    if (!$u) return false;
    return $u['role'] === 'admin' || (int)$img['user_id'] === (int)$u['id'];
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (is_api()) json_out(['ok' => false, 'error' => 'Please log in first.'], 401);
        flash('info', 'Please log in to continue.');
        redirect('login', ['next' => current_path_url()]);
    }
    return $u;
}
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        if (is_api()) json_out(['ok' => false, 'error' => 'Admins only.'], 403);
        render_error(403, 'Admins only', 'You do not have permission to view this page.');
    }
    return $u;
}
function require_uploader(): array
{
    $u = require_login();
    if (!can_upload()) {
        if (is_api()) json_out(['ok' => false, 'error' => 'Uploading is limited to the gallery owner.'], 403);
        render_error(403, 'Uploads are owner-only', 'This gallery is in Single Owner mode, so only the owner can post pictures. Everyone else can browse and like.');
    }
    return $u;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE users SET last_login = UTC_TIMESTAMP() WHERE id = ?', [$u['id']]);
    if (valid_theme($u['theme'] ?? null) && setting('allow_visitor_themes', '1') === '1') {
        setcookie('tfg_theme', $u['theme'], theme_cookie_opts());
    }
}
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}
function theme_cookie_opts(): array
{
    return ['expires' => time() + 86400 * 365, 'path' => base_path() . '/', 'secure' => is_https(), 'httponly' => false, 'samesite' => 'Lax'];
}

/* ---- brute-force throttle ---- */
function login_throttled(string $name): bool
{
    q('DELETE FROM login_attempts WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
    $n = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE) AND (ip = ? OR username = ?)', [client_ip(), mb_strtolower($name)]);
    return $n >= 8;
}
function login_fail(string $name): void
{
    q('INSERT INTO login_attempts (ip, username, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [client_ip(), mb_strtolower(mb_substr($name, 0, 60))]);
}

/* ---- validation ---- */
function valid_username(string $u): bool { return (bool)preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $u); }
function password_problem(string $p): ?string
{
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (strlen($p) > 200) return 'Password is too long.';
    return null;
}
function create_user(string $username, string $email, string $password, string $role = 'user'): int
{
    q('INSERT INTO users (username, email, password_hash, display_name, role, status, theme, created_at)
       VALUES (?, ?, ?, ?, ?, "active", NULL, UTC_TIMESTAMP())',
        [$username, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), $username, $role]);
    return (int)db()->lastInsertId();
}
