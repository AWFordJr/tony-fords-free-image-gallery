<?php
/**
 * Tony Ford's Free Image Gallery - Web Installer
 * by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com
 * Free for non-commercial use only. See LICENSE.txt.
 */
declare(strict_types=1);
define('TFG_INSTALLING', 1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/schema.php';

mb_internal_encoding('UTF-8');
send_security_headers();
start_session();

$configFile = ROOT . '/config.php';
$installFile = ROOT . '/install.php';
$base = base_path();

/** Page shell for the installer */
function inst_page(string $title, string $body, string $bodyClass = ''): never
{
    $b = base_path();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en" data-theme="classic-dark"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . e($title) . ' - Tony Ford\'s Free Image Gallery</title>'
        . '<link rel="icon" href="' . e($b) . '/assets/favicon.ico">'
        . '<link rel="stylesheet" href="' . e($b) . '/assets/css/app.css"><link rel="stylesheet" href="' . e($b) . '/assets/css/themes.css">'
        . '</head><body class="install ' . e($bodyClass) . '"><div class="install-wrap">'
        . '<header class="install-head"><img src="' . e($b) . '/assets/img/logo-128.png" alt="Tony Ford" width="84" height="84">'
        . '<div><h1>Tony Ford\'s Free Image Gallery</h1><p class="muted">Installer &middot; version ' . e(TFG_VERSION) . '</p></div></header>'
        . $body
        . '<footer class="install-foot muted">' . credit_html() . '</footer></div></body></html>';
    exit;
}

/* ---------- already installed? ---------- */
if (is_file($configFile)) {
    inst_page('Already installed', '<section class="card"><h2>This gallery is already installed 🎉</h2>'
        . '<p>The installer is locked because <code>config.php</code> exists.</p>'
        . '<p class="warn-box"><strong>For safety, delete <code>install.php</code> from your server.</strong></p>'
        . '<p><a class="btn" href="' . e($base) . '/">Open the gallery</a></p></section>');
}

/* ---------- requirement checks ---------- */
/** Really try to write a file (is_writable() lies on some hosts). */
function can_write_dir(string $dir): bool
{
    if (!is_dir($dir)) return false;
    $f = rtrim($dir, '/\\') . '/.tfg_write_test_' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($f, 'ok') === 2;
    if ($ok) @unlink($f);
    return $ok;
}

function install_checks(): array
{
    $c = [];
    $c[] = ['PHP 8.0 or newer', PHP_VERSION_ID >= 80000, 'Found ' . PHP_VERSION . (PHP_VERSION_ID >= 80000 ? '' : ' - choose PHP 8.1+ in your hosting control panel'), true];
    $why = [
        'pdo_mysql' => 'Missing - enable the "pdo_mysql" PHP extension (cPanel: Select PHP Version → Extensions; Ubuntu: apt install php-mysql)',
        'gd' => 'Missing - enable the "gd" PHP extension (needed for thumbnails; Ubuntu: apt install php-gd)',
        'fileinfo' => 'Missing - enable the "fileinfo" PHP extension (needed to safely check uploads)',
        'mbstring' => 'Missing - enable the "mbstring" PHP extension',
        'session' => 'Missing - enable the "session" PHP extension',
    ];
    foreach (['pdo_mysql' => 'PDO MySQL driver', 'gd' => 'GD image library', 'fileinfo' => 'Fileinfo (safe MIME checks)', 'mbstring' => 'Multibyte strings', 'session' => 'Sessions'] as $ext => $label) {
        $has = extension_loaded($ext);
        $c[] = [$label, $has, $has ? 'Loaded' : $why[$ext], true];
    }
    $webp = function_exists('imagewebp');
    $c[] = ['GD WebP support (smaller thumbnails)', $webp, $webp ? 'Available' : 'Not available - JPEG thumbnails will be used instead', false];
    $exif = function_exists('exif_read_data');
    $c[] = ['EXIF extension (camera info)', $exif, $exif ? 'Available' : 'Not available - EXIF details will be skipped', false];
    $wr = can_write_dir(ROOT);
    $c[] = ['Folder writable (to create config.php)', $wr, $wr ? 'Writable' : 'Not writable - no problem: the installer will show you config.php to paste in by hand (or chmod this folder 755/775 and reload)', false];
    foreach (['o', 'm', 't'] as $d) if (!is_dir(UPLOAD_DIR . "/$d")) @mkdir(UPLOAD_DIR . "/$d", 0755, true);
    $uw = can_write_dir(UPLOAD_DIR) && can_write_dir(UPLOAD_DIR . '/o') && can_write_dir(UPLOAD_DIR . '/m') && can_write_dir(UPLOAD_DIR . '/t');
    $c[] = ['uploads/ folder writable', $uw, $uw ? 'Writable' : 'Not writable - set the uploads/ folder (and everything inside it) to permission 755 or 775 (FTP: right-click → File permissions), or ask your host to make it writable by PHP', true];
    $sess = session_status() === PHP_SESSION_ACTIVE;
    $c[] = ['Sessions can be stored', $sess, $sess ? 'OK' : 'PHP could not start a session (the server\'s session folder is not writable) - ask your host to fix session.save_path', true];
    return $c;
}

/** Turn a raw PDO error into plain English. */
function friendly_db_error(Throwable $e, array $v): string
{
    $m = $e->getMessage();
    $code = $e instanceof PDOException && isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    if ($code === 0 && preg_match('/\[(\d{4})\]/', $m, $mm)) $code = (int)$mm[1];
    switch ($code) {
        case 1045: return 'The database refused that username or password. Double-check "Database user" and "Database password" (they are case-sensitive).';
        case 1044: case 1142: return 'That database user is not allowed to use the database "' . $v['db_name'] . '". Grant it privileges in your hosting panel (MySQL Databases → Add user to database).';
        case 1049: return 'The database "' . $v['db_name'] . '" does not exist and the user is not allowed to create it. Create an empty database in your hosting panel first, then try again.';
        case 2002: case 2003: case 2006: return 'Could not reach the database server at "' . $v['db_host'] . ':' . $v['db_port'] . '". Check the host and port (usually "localhost" and 3306) and make sure MySQL/MariaDB is running.';
        case 2005: return 'The database host "' . $v['db_host'] . '" was not found. Check the spelling (most hosts use "localhost").';
        case 1071: case 1709: return 'This database server is too old or uses an unsupported setting (index too long). MySQL 5.7+ or MariaDB 10.3+ is required.';
    }
    return 'Database error: ' . $m;
}

$checks = install_checks();
$blocking = array_filter($checks, fn($c) => !$c[1] && $c[3]);

$errors = [];
$tokenBad = false;
$tablesFound = [];
$v = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'site_title' => "Tony Ford's Free Image Gallery", 'mode' => 'single', 'admin_user' => '', 'admin_email' => '',
    'timezone' => (string)(ini_get('date.timezone') ?: 'UTC'),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tok = $_POST['csrf'] ?? '';
    if (!is_string($tok) || $tok === '' || !hash_equals(csrf_token(), $tok)) {
        $errors[] = 'Your page expired (security token mismatch). Nothing was changed - please fill in the form again and press Install.';
        $_POST = [];
        $tokenBad = true;
    }
    foreach (['db_host', 'db_port', 'db_name', 'db_user', 'site_title', 'mode', 'admin_user', 'admin_email', 'timezone'] as $k) $v[$k] = trim((string)($_POST[$k] ?? $v[$k]));
    $v['db_pass'] = (string)($_POST['db_pass'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminPass2 = (string)($_POST['admin_pass2'] ?? '');

    if ($blocking) $errors[] = 'Please fix the failed requirements above first.';
    if (!preg_match('/^[A-Za-z0-9._:\-]{1,190}$/', $v['db_host'])) $errors[] = 'Database host looks invalid.';
    if (!ctype_digit($v['db_port']) || (int)$v['db_port'] < 1 || (int)$v['db_port'] > 65535) $errors[] = 'Database port must be a number.';
    if (!preg_match('/^[A-Za-z0-9_$]{1,64}$/', $v['db_name'])) $errors[] = 'Database name may only contain letters, numbers, _ and $.';
    if ($v['db_user'] === '') $errors[] = 'Database user is required.';
    if ($v['site_title'] === '' || mb_strlen($v['site_title']) > 100) $errors[] = 'Site title is required (max 100 characters).';
    if (!in_array($v['mode'], ['single', 'open'], true)) $errors[] = 'Choose a gallery mode.';
    if (!valid_username($v['admin_user'])) $errors[] = 'Admin username must be 3-30 characters: letters, numbers, . _ -';
    if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid admin email address.';
    if (($p = password_problem($adminPass)) !== null) $errors[] = $p;
    if ($adminPass !== $adminPass2) $errors[] = 'The two passwords do not match.';
    if (!in_array($v['timezone'], DateTimeZone::listIdentifiers(), true)) $v['timezone'] = 'UTC';

    if ($tokenBad) $errors = array_slice($errors, 0, 1);
    $pdo = null;
    $wipe = isset($_POST['wipe']) && $_POST['wipe'] === '1' && trim((string)($_POST['wipe_confirm'] ?? '')) === 'ERASE';
    if (!$errors) {
        try {
            $opts = [PDO::ATTR_TIMEOUT => 6, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
            try {
                $pdo = new PDO("mysql:host={$v['db_host']};port={$v['db_port']};dbname={$v['db_name']};charset=utf8mb4", $v['db_user'], $v['db_pass'], $opts);
            } catch (PDOException $e1) {
                if ((int)($e1->errorInfo[1] ?? 0) !== 1049) throw $e1;
                // the database does not exist yet - try to create it
                $tmp = new PDO("mysql:host={$v['db_host']};port={$v['db_port']};charset=utf8mb4", $v['db_user'], $v['db_pass'], $opts);
                try {
                    $tmp->exec('CREATE DATABASE `' . $v['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                } catch (PDOException $e2) {
                    throw $e1;   // "database does not exist and cannot be created"
                }
                $pdo = new PDO("mysql:host={$v['db_host']};port={$v['db_port']};dbname={$v['db_name']};charset=utf8mb4", $v['db_user'], $v['db_pass'], $opts);
            }
            $ver = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
            $maria = stripos($ver, 'mariadb') !== false;
            if (preg_match('/^(\d+)\.(\d+)/', $ver, $vm)) {
                $okv = $maria ? ((int)$vm[1] > 10 || ((int)$vm[1] === 10 && (int)$vm[2] >= 3)) : ((int)$vm[1] > 5 || ((int)$vm[1] === 5 && (int)$vm[2] >= 7));
                if (!$okv) throw new DomainException('Your database server (' . $ver . ') is too old. MySQL 5.7+ or MariaDB 10.3+ is required.');
            }
            $existing = [];
            foreach (INSTALL_TABLES as $t) if ($pdo->query("SHOW TABLES LIKE '" . $t . "'")->fetch()) $existing[] = $t;
            if ($existing) {
                if ($wipe) {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    foreach ($existing as $t) $pdo->exec('DROP TABLE `' . $t . '`');
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    $existing = [];
                } else {
                    $tablesFound = $existing;
                    $errors[] = 'That database already contains gallery tables (' . implode(', ', $existing) . '). Nothing was changed. Use a different empty database - or, if you really want to start over, tick "Erase and reinstall" below.';
                    $pdo = null;
                }
            }
        } catch (DomainException $e) {
            $errors[] = $e->getMessage();
            $pdo = null;
        } catch (Throwable $e) {
            error_log('TFG installer DB error: ' . $e->getMessage());
            $errors[] = friendly_db_error($e, $v);
            $pdo = null;
        }
    }

    if (!$errors && $pdo) {
        try {
            foreach (schema_statements() as $sql) $pdo->exec($sql);
            $st = $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, role, status, created_at) VALUES (?,?,?,?, "admin", "active", UTC_TIMESTAMP())');
            $st->execute([$v['admin_user'], mb_strtolower($v['admin_email']), password_hash($adminPass, PASSWORD_DEFAULT), $v['admin_user']]);
            $settings = default_settings();
            $settings['site_title'] = $v['site_title'];
            $settings['mode'] = $v['mode'];
            $settings['timezone'] = $v['timezone'];
            $settings['pretty_urls'] = '0';
            $ins = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            foreach ($settings as $k => $val) $ins->execute([$k, $val]);

            foreach (['o', 'm', 't'] as $d) if (!is_dir(UPLOAD_DIR . "/$d")) @mkdir(UPLOAD_DIR . "/$d", 0755, true);

            $cfg = "<?php\n// Generated by the Tony Ford's Free Image Gallery installer on " . gmdate('Y-m-d H:i') . " UTC\n"
                . "// Keep this file private. Do not share it.\n"
                . 'define(\'DB_HOST\', ' . var_export($v['db_host'], true) . ");\n"
                . 'define(\'DB_PORT\', ' . var_export($v['db_port'], true) . ");\n"
                . 'define(\'DB_NAME\', ' . var_export($v['db_name'], true) . ");\n"
                . 'define(\'DB_USER\', ' . var_export($v['db_user'], true) . ");\n"
                . 'define(\'DB_PASS\', ' . var_export($v['db_pass'], true) . ");\n"
                . "// Only set this if the gallery lives behind a proxy / unusual path, e.g. '/gallery'\n"
                . "define('BASE_URL_OVERRIDE', '');\n"
                . "// Set to true while troubleshooting, then back to false\n"
                . "define('TFG_DEBUG', false);\n";
            $written = @file_put_contents($configFile, $cfg, LOCK_EX) !== false;
            if ($written) @chmod($configFile, 0644);

            if (!$written) {
                inst_page('Almost done', '<section class="card"><h2>Almost done - save one file</h2>'
                    . '<p>The database is ready, but I could not write <code>config.php</code> (folder not writable). '
                    . 'Create a file named <code>config.php</code> next to <code>install.php</code> containing exactly this, then open the gallery:</p>'
                    . '<textarea readonly rows="16" class="code">' . e($cfg) . '</textarea>'
                    . '<p class="warn-box">Then delete <code>install.php</code>. Admin login: <strong>' . e($v['admin_user']) . '</strong></p>'
                    . '<p><a class="btn" href="' . e($base) . '/">Open the gallery</a></p></section>');
            }

            // Pretty URL probe (needs mod_rewrite / the bundled .htaccess)
            $pretty = false;
            $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $probe = @file_get_contents(abs_probe_url(), false, $ctx);
            if ($probe === 'tfg-ok') $pretty = true;
            $pdo->prepare('UPDATE settings SET v = ? WHERE k = "pretty_urls"')->execute([$pretty ? '1' : '0']);

            // Lock the installer
            $locked = false;
            if (@rename($installFile, ROOT . '/install.php.locked')) $locked = true;

            unset($_SESSION['csrf']);
            inst_page('Installed', '<section class="card"><h2>🎉 Installed! Welcome, ' . e($v['admin_user']) . '</h2>'
                . '<p>Mode: <strong>' . ($v['mode'] === 'single' ? 'Single owner (only you post, everyone else views)' : 'Open registration (anyone can register and share)') . '</strong>. You can change it any time in <em>Admin &rarr; Settings</em>.</p>'
                . '<p>Pretty URLs: <strong>' . ($pretty ? 'on' : 'off (fallback links are used; enable in Admin if your server supports rewrite rules)') . '</strong></p>'
                . ($locked
                    ? '<p class="ok-box">The installer locked itself (renamed to <code>install.php.locked</code>). You can delete that file.</p>'
                    : '<p class="warn-box"><strong>Delete <code>install.php</code> now.</strong> It refuses to run again while config.php exists, but there is no reason to keep it.</p>')
                . '<p><a class="btn" href="' . e($base) . '/login">Log in to your gallery</a></p></section>');
        } catch (Throwable $e) {
            error_log('TFG installer failed: ' . $e->getMessage());
            $errors[] = 'Setup failed part-way: ' . friendly_db_error($e, $v) . ' The tables created so far are harmless; fix the problem and press Install again (tick "Erase and reinstall" if it says tables already exist).';
        }
    }
}

function abs_probe_url(): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) $host = 'localhost';
    return $scheme . '://' . $host . base_path() . '/__probe';
}

/* ---------- form ---------- */
ob_start();
?>
<section class="card">
  <h2>1 &middot; Server check</h2>
  <ul class="checks">
  <?php foreach ($checks as $c): ?>
    <li class="<?= $c[1] ? 'ok' : ($c[3] ? 'bad' : 'warn') ?>"><span><?= $c[1] ? '✔' : ($c[3] ? '✖' : '!') ?></span> <strong><?= e($c[0]) ?></strong> <small><?= e($c[2]) ?></small></li>
  <?php endforeach; ?>
  </ul>
</section>

<?php if ($errors): ?>
  <div class="alert error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" class="card form" autocomplete="off">
  <?= csrf_field() ?>
  <h2>2 &middot; Choose how your gallery works</h2>
  <div class="mode-cards">
    <label class="mode-card">
      <input type="radio" name="mode" value="single" <?= $v['mode'] === 'single' ? 'checked' : '' ?>>
      <span class="mc-body"><span class="mc-icon">👤</span><strong>Single owner</strong>
      <small>Only <em>you</em> post pictures. Everybody else can just browse and like. Perfect for a personal portfolio.</small></span>
    </label>
    <label class="mode-card">
      <input type="radio" name="mode" value="open" <?= $v['mode'] === 'open' ? 'checked' : '' ?>>
      <span class="mc-body"><span class="mc-icon">👥</span><strong>Open registration</strong>
      <small>Anyone can sign up and share their own pictures. A community gallery. You stay the admin and can moderate.</small></span>
    </label>
  </div>
  <p class="muted small">You can switch modes later in Admin &rarr; Settings.</p>

  <h2>3 &middot; Database</h2>
  <div class="grid2">
    <label class="field">Database host<input name="db_host" value="<?= e($v['db_host']) ?>" required></label>
    <label class="field">Port<input name="db_port" value="<?= e($v['db_port']) ?>" required inputmode="numeric"></label>
    <label class="field">Database name<input name="db_name" value="<?= e($v['db_name']) ?>" required placeholder="tony_gallery"></label>
    <label class="field">Database user<input name="db_user" value="<?= e($v['db_user']) ?>" required></label>
    <label class="field span2">Database password<input type="password" name="db_pass" value="" autocomplete="new-password"></label>
  </div>
  <p class="muted small">Use an empty database. If it doesn't exist and your user may create databases, it will be created.</p>

  <h2>4 &middot; Your site &amp; admin account</h2>
  <div class="grid2">
    <label class="field span2">Site title<input name="site_title" value="<?= e($v['site_title']) ?>" required maxlength="100"></label>
    <label class="field">Admin username<input name="admin_user" value="<?= e($v['admin_user']) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.\-]{3,30}"></label>
    <label class="field">Admin email<input type="email" name="admin_email" value="<?= e($v['admin_email']) ?>" required></label>
    <label class="field">Password (8+ characters)<input type="password" name="admin_pass" required minlength="8" autocomplete="new-password"></label>
    <label class="field">Repeat password<input type="password" name="admin_pass2" required minlength="8" autocomplete="new-password"></label>
    <label class="field span2">Time zone
      <select name="timezone"><?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option<?= $tz === $v['timezone'] ? ' selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></label>
  </div>
  <?php if ($tablesFound): ?>
  <div class="warn-box">
    <label><input type="checkbox" name="wipe" value="1"> <strong>Erase and reinstall</strong> - delete the old gallery tables in this database (users, pictures records, albums, likes, comments) and start fresh. Uploaded files in <code>uploads/</code> are not touched.</label>
    <label class="field">Type <code>ERASE</code> to confirm<input name="wipe_confirm" autocomplete="off" placeholder="ERASE"></label>
  </div>
  <?php endif; ?>
  <p><button class="btn primary big" type="submit"<?= $blocking ? ' disabled' : '' ?>>Install my gallery</button></p>
</form>
<?php
inst_page('Install', (string)ob_get_clean());
