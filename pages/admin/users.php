<?php
$noindex = true;
$pageTitle = 'Manage users';
$me = current_user();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');
    $uid = (int)($_POST['id'] ?? 0);
    $t = q_row('SELECT * FROM users WHERE id = ?', [$uid]);
    $admins = (int)q_val("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'");
    if ($act !== 'create' && !$t) { flash('error', 'User not found.'); }
    elseif ($act === 'update') {
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $status = ($_POST['status'] ?? '') === 'banned' ? 'banned' : 'active';
        if ($t['id'] == $me['id'] && ($role !== 'admin' || $status !== 'active')) flash('error', 'You cannot demote or ban yourself.');
        elseif ($t['role'] === 'admin' && $t['status'] === 'active' && ($role !== 'admin' || $status !== 'active') && $admins <= 1) flash('error', 'There must be at least one active admin.');
        else { q('UPDATE users SET role = ?, status = ? WHERE id = ?', [$role, $status, $uid]); flash('ok', 'User updated.'); }
    } elseif ($act === 'password') {
        $np = (string)($_POST['new_password'] ?? '');
        if (($pp = password_problem($np)) !== null) flash('error', $pp);
        else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($np, PASSWORD_DEFAULT), $uid]); flash('ok', 'Password reset for ' . $t['username'] . '.'); }
    } elseif ($act === 'delete') {
        if ($t['id'] == $me['id']) flash('error', 'You cannot delete yourself.');
        elseif ($t['role'] === 'admin' && $admins <= 1) flash('error', 'There must be at least one admin.');
        else {
            foreach (q_all('SELECT * FROM images WHERE user_id = ?', [$uid]) as $im) delete_image($im);
            q('DELETE FROM users WHERE id = ?', [$uid]);
            flash('ok', 'User ' . $t['username'] . ' and their pictures were deleted.');
        }
    } elseif ($act === 'create') {
        $un = trim((string)($_POST['username'] ?? '')); $em = trim((string)($_POST['email'] ?? '')); $pw = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $pp = password_problem($pw);
        if (!valid_username($un)) flash('error', 'Username must be 3-30 characters: letters, numbers, . _ -');
        elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) flash('error', 'Enter a valid email.');
        elseif ($pp) flash('error', $pp);
        elseif (q_val('SELECT 1 FROM users WHERE username = ? OR email = ?', [$un, mb_strtolower($em)])) flash('error', 'Username or email already exists.');
        else { create_user($un, $em, $pw, $role); flash('ok', 'User created.'); }
    }
    redirect('admin/users');
}
$qs = trim((string)($_GET['q'] ?? ''));
$where = '1=1'; $params = [];
if ($qs !== '') { $like = '%' . like_escape($qs) . '%'; $where = '(u.username LIKE ? OR u.email LIKE ?)'; $params = [$like, $like]; }
$total = (int)q_val("SELECT COUNT(*) FROM users u WHERE $where", $params);
$p = pagination_info($total, 40, current_page_num());
$rows = q_all("SELECT u.*, (SELECT COUNT(*) FROM images i WHERE i.user_id = u.id) AS pics FROM users u WHERE $where ORDER BY u.id DESC LIMIT 40 OFFSET " . (int)$p['offset'], $params);
?>
<div class="admin-shell">
<?= admin_nav('users') ?>
<div class="admin-body">
  <h1 class="page-title">Users</h1>
  <?php if (mode() === 'single'): ?><div class="alert info">Single Owner mode is on: only admins can post. Other accounts can log in, like and comment, but not upload.</div><?php endif; ?>
  <form class="toolbar" method="get" action="<?= e(url('admin/users')) ?>">
    <?php if (setting('pretty_urls', '1') !== '1'): ?><input type="hidden" name="r" value="admin/users"><?php endif; ?>
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search username or email" maxlength="100"><button class="btn small">Search</button>
    <span class="muted small"><?= number_format($total) ?> user(s)</span>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>User</th><th>Email</th><th>Pics</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('user/' . $r['username'])) ?>"><strong><?= e($r['username']) ?></strong></a></td>
        <td class="small"><?= e($r['email']) ?></td>
        <td><?= (int)$r['pics'] ?></td>
        <td><select name="role" form="u<?= (int)$r['id'] ?>"><option value="user" <?= $r['role'] === 'user' ? 'selected' : '' ?>>user</option><option value="admin" <?= $r['role'] === 'admin' ? 'selected' : '' ?>>admin</option></select></td>
        <td><select name="status" form="u<?= (int)$r['id'] ?>"><option value="active" <?= $r['status'] === 'active' ? 'selected' : '' ?>>active</option><option value="banned" <?= $r['status'] === 'banned' ? 'selected' : '' ?>>banned</option></select></td>
        <td class="small"><?= e(ago($r['created_at'])) ?></td>
        <td class="row">
          <button class="btn small" name="action" value="update" form="u<?= (int)$r['id'] ?>">Save</button>
          <details class="pw"><summary class="btn small">Password</summary>
            <span class="pwbox"><input type="password" name="new_password" form="u<?= (int)$r['id'] ?>" placeholder="New password" minlength="8" autocomplete="new-password"><button class="btn small" name="action" value="password" form="u<?= (int)$r['id'] ?>">Reset</button></span></details>
          <?php if ((int)$r['id'] !== (int)$me['id']): ?><button class="btn danger small" name="action" value="delete" form="u<?= (int)$r['id'] ?>" data-confirm="Delete this user and ALL their pictures?">Delete</button><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php foreach ($rows as $r): ?><form method="post" id="u<?= (int)$r['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"></form><?php endforeach; ?>
  <?= pagination_html($p, 'admin/users', array_filter(['q' => $qs])) ?>

  <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h2>Add a user</h2>
    <div class="grid3">
      <label class="field">Username<input name="username" required pattern="[A-Za-z0-9_.\-]{3,30}"></label>
      <label class="field">Email<input type="email" name="email" required></label>
      <label class="field">Password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label class="field">Role<select name="role"><option value="user">user</option><option value="admin">admin</option></select></label>
    </div>
    <button class="btn primary" type="submit">Create user</button>
  </form>
</div></div>
