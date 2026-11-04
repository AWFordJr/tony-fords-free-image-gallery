<?php
$noindex = true;
$pageTitle = 'Sign up';
if (current_user()) redirect('');
if (!registration_open()) {
    render_error(403, 'Registration is closed', 'This gallery is in Single Owner mode, so only the owner posts pictures. You can still browse, like and share.');
}
$err = []; $v = ['username' => '', 'email' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $v['username'] = trim((string)($_POST['username'] ?? ''));
    $v['email'] = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!empty($_POST['website'])) { redirect(''); } // honeypot
    if (!valid_username($v['username'])) $err[] = 'Username must be 3-30 characters: letters, numbers, . _ -';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) $err[] = 'Enter a valid email address.';
    if (($pp = password_problem($pass)) !== null) $err[] = $pp;
    if ($pass !== (string)($_POST['password2'] ?? '')) $err[] = 'The passwords do not match.';
    if (!$err) {
        if (q_val('SELECT 1 FROM users WHERE username = ?', [$v['username']])) $err[] = 'That username is taken.';
        if (q_val('SELECT 1 FROM users WHERE email = ?', [mb_strtolower($v['email'])])) $err[] = 'That email is already registered.';
    }
    if (!$err && (int)q_val('SELECT COUNT(*) FROM users WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)') >= 50) $err[] = 'Too many sign-ups right now. Please try again later.';
    if (!$err) {
        try {
            $id = create_user($v['username'], $v['email'], $pass, 'user');
            $row = q_row('SELECT * FROM users WHERE id = ?', [$id]);
            login_user($row);
            flash('ok', 'Welcome, ' . $row['username'] . '! Your account is ready - you can start sharing pictures.');
            redirect('upload');
        } catch (PDOException $e) {
            $err[] = 'That username or email is already taken.';
        }
    }
}
?>
<section class="center-card">
  <h1>Create your account</h1>
  <p class="muted">Join to upload, like and comment.</p>
  <?php if ($err): ?><div class="alert error"><?php foreach ($err as $x): ?><div><?= e($x) ?></div><?php endforeach; ?></div><?php endif; ?>
  <form method="post" class="form" autocomplete="on">
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <label class="field">Username<input name="username" value="<?= e($v['username']) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.\-]{3,30}" autocomplete="username"></label>
    <label class="field">Email<input type="email" name="email" value="<?= e($v['email']) ?>" required maxlength="190" autocomplete="email"></label>
    <label class="field">Password (8+ characters)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <label class="field">Repeat password<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    <button class="btn primary big" type="submit">Sign up</button>
  </form>
  <p class="muted">Already registered? <a href="<?= e(url('login')) ?>">Log in</a></p>
</section>
