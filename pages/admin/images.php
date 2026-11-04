<?php
$noindex = true;
$pageTitle = 'Moderate pictures';
$me = current_user();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $act = (string)($_POST['bulk'] ?? '');
    if (!$ids) flash('error', 'Select at least one picture.');
    elseif (in_array($act, ['published', 'hidden', 'pending'], true)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        q("UPDATE images SET status = ? WHERE id IN ($in)", array_merge([$act], $ids));
        flash('ok', count($ids) . ' picture(s) set to ' . $act . '.');
    } elseif ($act === 'delete') {
        $n = 0;
        foreach ($ids as $id) { $im = q_row('SELECT * FROM images WHERE id = ?', [$id]); if ($im) { delete_image($im); $n++; } }
        flash('ok', "$n picture(s) deleted.");
    } elseif ($act === 'move') {
        $cat = (int)($_POST['move_to'] ?? 0);
        if ($cat && !category_by_id($cat)) flash('error', 'Unknown album.');
        else {
            $in = implode(',', array_fill(0, count($ids), '?'));
            q("UPDATE images SET category_id = ? WHERE id IN ($in)", array_merge([$cat ?: null], $ids));
            flash('ok', count($ids) . ' picture(s) moved.');
        }
    }
    redirect('admin/images');
}
$status = in_array($_GET['status'] ?? '', ['published', 'pending', 'hidden'], true) ? $_GET['status'] : '';
$qs = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if ($status) { $where[] = 'i.status = ?'; $params[] = $status; }
if ($qs !== '') { $like = '%' . like_escape($qs) . '%'; $where[] = '(i.title LIKE ? OR u.username LIKE ?)'; array_push($params, $like, $like); }
$w = implode(' AND ', $where);
$total = (int)q_val("SELECT COUNT(*) FROM images i JOIN users u ON u.id = i.user_id WHERE $w", $params);
$p = pagination_info($total, 40, current_page_num());
$rows = q_all("SELECT i.id, i.title, i.slug, i.thumb_path, i.status, i.created_at, i.filesize, i.views, i.likes_count, i.category_id, u.username FROM images i JOIN users u ON u.id = i.user_id WHERE $w ORDER BY i.id DESC LIMIT 40 OFFSET " . (int)$p['offset'], $params);
$q2 = array_filter(['status' => $status, 'q' => $qs]);
?>
<div class="admin-shell">
<?= admin_nav('images') ?>
<div class="admin-body">
  <h1 class="page-title">Pictures</h1>
  <form class="toolbar" method="get" action="<?= e(url('admin/images')) ?>">
    <?php if (setting('pretty_urls', '1') !== '1'): ?><input type="hidden" name="r" value="admin/images"><?php endif; ?>
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search title or user" maxlength="100">
    <select name="status"><option value="">All statuses</option><?php foreach (['published', 'pending', 'hidden'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <button class="btn small" type="submit">Filter</button>
    <span class="muted small"><?= number_format($total) ?> picture(s)</span>
  </form>
  <form method="post" id="bulk-form">
    <?= csrf_field() ?>
    <div class="toolbar bulk">
      <label class="check"><input type="checkbox" data-check-all> Select all</label>
      <select name="bulk"><option value="">Bulk action…</option><option value="published">Publish / approve</option><option value="hidden">Hide</option><option value="pending">Mark pending</option><option value="move">Move to album →</option><option value="delete">Delete</option></select>
      <select name="move_to"><?= category_options(null, [], '— No album —') ?></select>
      <button class="btn small" type="submit" data-confirm-if="delete" data-confirm="Really delete the selected pictures? This cannot be undone.">Apply</button>
    </div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th></th><th>Picture</th><th>Owner</th><th>Album</th><th>Status</th><th>Stats</th><th>Added</th><th></th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="Select"></td>
          <td><a class="mini" href="<?= e(image_url($r)) ?>"><img src="<?= e(thumb_url($r)) ?>" alt="" loading="lazy"><span><strong><?= e($r['title']) ?></strong><small><?= e(human_size((int)$r['filesize'])) ?></small></span></a></td>
          <td><a href="<?= e(url('user/' . $r['username'])) ?>">@<?= e($r['username']) ?></a></td>
          <td><?= $r['category_id'] ? e(category_path_label((int)$r['category_id'])) : '<span class="muted">-</span>' ?></td>
          <td><span class="badge <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td class="small">👁 <?= number_format((int)$r['views']) ?> · ♥ <?= (int)$r['likes_count'] ?></td>
          <td class="small"><?= e(ago($r['created_at'])) ?></td>
          <td><a class="btn small" href="<?= e(url('edit/' . $r['id'])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="muted">No pictures found.</td></tr><?php endif; ?>
      </tbody></table></div>
  </form>
  <?= pagination_html($p, 'admin/images', $q2) ?>
</div></div>
