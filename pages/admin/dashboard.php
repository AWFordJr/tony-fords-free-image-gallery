<?php
$noindex = true;
$pageTitle = 'Admin dashboard';
$st = [
    'images' => (int)q_val('SELECT COUNT(*) FROM images'),
    'pending' => (int)q_val("SELECT COUNT(*) FROM images WHERE status = 'pending'"),
    'users' => (int)q_val('SELECT COUNT(*) FROM users'),
    'albums' => (int)q_val('SELECT COUNT(*) FROM categories'),
    'tags' => (int)q_val('SELECT COUNT(*) FROM tags'),
    'likes' => (int)q_val('SELECT COUNT(*) FROM likes'),
    'comments' => (int)q_val('SELECT COUNT(*) FROM comments'),
    'views' => (int)q_val('SELECT COALESCE(SUM(views),0) FROM images'),
    'bytes' => (int)q_val('SELECT COALESCE(SUM(filesize),0) FROM images'),
    'today' => (int)q_val('SELECT COUNT(*) FROM images WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 1 DAY)'),
];
// uploads per day for the last 14 days
$days = [];
for ($i = 13; $i >= 0; $i--) $days[gmdate('Y-m-d', time() - $i * 86400)] = 0;
foreach (q_all('SELECT DATE(created_at) d, COUNT(*) n FROM images WHERE created_at >= (UTC_DATE() - INTERVAL 13 DAY) GROUP BY DATE(created_at)') as $r) {
    if (isset($days[$r['d']])) $days[$r['d']] = (int)$r['n'];
}
$maxDay = max(1, max($days));
$topViewed = q_all("SELECT id, title, slug, thumb_path, views, likes_count, color FROM images WHERE status='published' ORDER BY views DESC, id DESC LIMIT 5");
$topLiked = q_all("SELECT id, title, slug, thumb_path, views, likes_count, color FROM images WHERE status='published' ORDER BY likes_count DESC, id DESC LIMIT 5");
$recent = q_all('SELECT i.id, i.title, i.slug, i.thumb_path, i.status, i.created_at, u.username FROM images i JOIN users u ON u.id = i.user_id ORDER BY i.id DESC LIMIT 6');
$newUsers = q_all('SELECT username, role, created_at FROM users ORDER BY id DESC LIMIT 6');
$warnings = [];
if (is_file(ROOT . '/install.php')) $warnings[] = 'install.php is still on your server. It is locked, but you should delete it.';
if (!function_exists('imagewebp')) $warnings[] = 'This PHP has no WebP support in GD - JPEG thumbnails are being used.';
if (!is_writable(UPLOAD_DIR)) $warnings[] = 'The uploads folder is not writable.';
if (is_writable(ROOT . '/config.php')) $warnings[] = 'config.php is writable by the web server. Consider chmod 640 or 444 once everything works.';
$mini = fn(array $r) => '<a class="mini" href="' . e(image_url($r)) . '"><img src="' . e(thumb_url($r)) . '" alt="" loading="lazy"><span><strong>' . e($r['title']) . '</strong><small>👁 ' . number_format((int)$r['views']) . ' · ♥ ' . (int)$r['likes_count'] . '</small></span></a>';
?>
<div class="admin-shell">
<?= admin_nav('dashboard') ?>
<div class="admin-body">
  <h1 class="page-title">Dashboard</h1>
  <?php foreach ($warnings as $w): ?><div class="alert info">⚠ <?= e($w) ?></div><?php endforeach; ?>
  <?php if ($st['pending']): ?><div class="alert info">🕒 <?= $st['pending'] ?> picture<?= $st['pending'] === 1 ? '' : 's' ?> waiting for approval - <a href="<?= e(url('admin/images', ['status' => 'pending'])) ?>">review now</a></div><?php endif; ?>

  <div class="stat-grid">
    <div class="stat"><span class="stat-ico">🖼️</span><strong><?= number_format($st['images']) ?></strong><small>pictures (<?= $st['today'] ?> today)</small></div>
    <div class="stat"><span class="stat-ico">👥</span><strong><?= number_format($st['users']) ?></strong><small>users</small></div>
    <div class="stat"><span class="stat-ico">🗂️</span><strong><?= number_format($st['albums']) ?></strong><small>albums &amp; sub-albums</small></div>
    <div class="stat"><span class="stat-ico">👁</span><strong><?= number_format($st['views']) ?></strong><small>total views</small></div>
    <div class="stat"><span class="stat-ico">♥</span><strong><?= number_format($st['likes']) ?></strong><small>likes</small></div>
    <div class="stat"><span class="stat-ico">💬</span><strong><?= number_format($st['comments']) ?></strong><small>comments</small></div>
    <div class="stat"><span class="stat-ico">🏷️</span><strong><?= number_format($st['tags']) ?></strong><small>tags</small></div>
    <div class="stat"><span class="stat-ico">💾</span><strong><?= e(human_size($st['bytes'])) ?></strong><small>originals stored</small></div>
  </div>

  <div class="two-col">
    <section class="card">
      <h2>Uploads · last 14 days</h2>
      <div class="chart" role="img" aria-label="Uploads per day">
        <?php foreach ($days as $d => $n): ?>
          <div class="bar" title="<?= e($d) ?>: <?= $n ?>"><span style="height:<?= max(3, (int)round($n / $maxDay * 100)) ?>%"></span><small><?= e(substr($d, 8)) ?></small><em><?= $n ?></em></div>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="card">
      <h2>Mode &amp; theme</h2>
      <p>Gallery mode: <strong><?= mode() === 'single' ? 'Single owner (only admins post)' : 'Open registration (anyone can share)' ?></strong></p>
      <p>Default theme: <strong><?= e(themes()[site_theme()]['name']) ?></strong></p>
      <p class="row"><a class="btn small" href="<?= e(url('admin/settings')) ?>">Change mode</a> <a class="btn small" href="<?= e(url('admin/themes')) ?>">Change theme</a></p>
      <p class="muted small">PHP <?= e(PHP_VERSION) ?> · upload limit <?= e(human_size(max_upload_bytes())) ?> · WebP <?= function_exists('imagewebp') ? 'yes' : 'no' ?> · EXIF <?= function_exists('exif_read_data') ? 'yes' : 'no' ?></p>
    </section>
  </div>

  <div class="three-col">
    <section class="card"><h2>Most viewed</h2><?php foreach ($topViewed as $r) echo $mini($r); if (!$topViewed) echo '<p class="muted">No pictures yet.</p>'; ?></section>
    <section class="card"><h2>Most liked</h2><?php foreach ($topLiked as $r) echo $mini($r); if (!$topLiked) echo '<p class="muted">No pictures yet.</p>'; ?></section>
    <section class="card"><h2>Newest uploads</h2>
      <?php foreach ($recent as $r): ?><a class="mini" href="<?= e(image_url($r)) ?>"><img src="<?= e(thumb_url($r)) ?>" alt="" loading="lazy"><span><strong><?= e($r['title']) ?></strong><small>@<?= e($r['username']) ?> · <?= e(ago($r['created_at'])) ?><?= $r['status'] !== 'published' ? ' · ' . e($r['status']) : '' ?></small></span></a><?php endforeach; ?>
      <?php if (!$recent) echo '<p class="muted">No pictures yet.</p>'; ?>
    </section>
  </div>

  <section class="card"><h2>Newest members</h2>
    <table class="table"><thead><tr><th>User</th><th>Role</th><th>Joined</th></tr></thead><tbody>
    <?php foreach ($newUsers as $r): ?><tr><td><a href="<?= e(url('user/' . $r['username'])) ?>"><?= e($r['username']) ?></a></td><td><?= e($r['role']) ?></td><td><?= e(ago($r['created_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</div></div>
