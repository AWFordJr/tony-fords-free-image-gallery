<?php
$noindex = true;
$pageTitle = 'My account';
$me = require_login();
$err = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'profile') {
        $dn = trim((string)($_POST['display_name'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($dn === '' || mb_strlen($dn) > 60) $err[] = 'Display name is required (max 60 characters).';
        if (mb_strlen($bio) > 500) $err[] = 'Bio is too long (max 500).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $err[] = 'Enter a valid email.';
        elseif (q_val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [mb_strtolower($email), $me['id']])) $err[] = 'That email is already in use.';
        if (!$err) {
            q('UPDATE users SET display_name = ?, bio = ?, email = ? WHERE id = ?', [$dn, $bio, mb_strtolower($email), $me['id']]);
            flash('ok', 'Profile saved.');
            redirect('account');
        }
    } elseif ($act === 'password') {
        $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) $err[] = 'Your current password is wrong.';
        if (($pp = password_problem($new)) !== null) $err[] = $pp;
        if ($new !== (string)($_POST['new2'] ?? '')) $err[] = 'The new passwords do not match.';
        if (!$err) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('ok', 'Password changed.');
            redirect('account');
        }
    } elseif ($act === 'delete_account') {
        if ($me['role'] === 'admin') { $err[] = 'The admin account cannot be deleted here. Ask another admin or change its role first.'; }
        elseif (!password_verify((string)($_POST['confirm_pass'] ?? ''), $me['password_hash'])) $err[] = 'Wrong password - account not deleted.';
        else {
            foreach (q_all('SELECT * FROM images WHERE user_id = ?', [$me['id']]) as $im) delete_image($im);
            q('DELETE FROM users WHERE id = ?', [$me['id']]);
            logout_user(); start_session();
            flash('ok', 'Your account and pictures were deleted.');
            redirect('');
        }
    }
    $me = current_user() ?? $me;
}
$mine = (int)q_val('SELECT COUNT(*) FROM images WHERE user_id = ?', [$me['id']]);
?>
<?= breadcrumbs([['Home', url('')], ['Account', null]]) ?>
<h1 class="page-title">My account</h1>
<?php if ($err): ?><div class="alert error"><?php foreach ($err as $x): ?><div><?= e($x) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="two-col">
  <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2>Profile</h2>
    <label class="field">Username<input value="<?= e($me['username']) ?>" disabled></label>
    <label class="field">Display name<input name="display_name" value="<?= e($me['display_name']) ?>" required maxlength="60"></label>
    <label class="field">Email<input type="email" name="email" value="<?= e($me['email']) ?>" required maxlength="190"></label>
    <label class="field">Bio<textarea name="bio" rows="3" maxlength="500"><?= e($me['bio']) ?></textarea></label>
    <button class="btn primary" type="submit">Save profile</button>
    <p class="muted small"><?= $mine ?> picture<?= $mine === 1 ? '' : 's' ?> · <a href="<?= e(url('user/' . $me['username'])) ?>">view my page</a></p>
  </form>
  <form method="post" class="card form" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2>Change password</h2>
    <label class="field">Current password<input type="password" name="current" required autocomplete="current-password"></label>
    <label class="field">New password<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
    <label class="field">Repeat new password<input type="password" name="new2" required minlength="8" autocomplete="new-password"></label>
    <button class="btn primary" type="submit">Change password</button>
  </form>
</div>
<?php if ($me['role'] !== 'admin'): ?>
<details class="card danger-zone"><summary>Delete my account</summary>
  <form method="post" class="form" data-confirm="This permanently deletes your account and all your pictures. Continue?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_account">
    <p class="muted">This removes your account and every picture you uploaded. It cannot be undone.</p>
    <label class="field">Confirm with your password<input type="password" name="confirm_pass" required autocomplete="current-password"></label>
    <button class="btn danger" type="submit">Delete everything</button>
  </form>
</details>
<?php endif; ?>
