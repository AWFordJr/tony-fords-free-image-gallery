<?php
$noindex = true;
$pageTitle = 'Log in';
if (current_user()) redirect('');
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
$err = null; $name = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $name = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (login_throttled($name)) {
        $err = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $row = q_row('SELECT * FROM users WHERE username = ? OR email = ?', [$name, mb_strtolower($name)]);
        // always run a hash check so timing does not reveal whether the account exists
        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok = password_verify($pass, $hash) && $row;
        if ($ok && $row['status'] !== 'active') { $err = 'This account has been disabled.'; }
        elseif ($ok) {
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
            }
            login_user($row);
            flash('ok', 'Welcome back, ' . $row['display_name'] . '!');
            redirect_to(safe_next($next));
        } else {
            login_fail($name);
            $err = 'Wrong username or password.';
        }
    }
}
?>
<section class="center-card">
  <h1>Log in</h1>
  <?php if ($err): ?><div class="alert error"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="form" autocomplete="on">
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <label class="field">Username or email<input name="username" value="<?= e($name) ?>" required autofocus autocomplete="username" maxlength="190"></label>
    <label class="field">Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn primary big" type="submit">Log in</button>
  </form>
  <?php if (registration_open()): ?><p class="muted">New here? <a href="<?= e(url('register')) ?>">Create an account</a></p>
  <?php else: ?><p class="muted small">This gallery is in Single Owner mode - only the owner logs in. Everyone else can browse freely.</p><?php endif; ?>
</section>
