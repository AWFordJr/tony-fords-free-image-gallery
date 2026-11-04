<?php
$noindex = true;
$pageTitle = 'Manage albums';
$err = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $parent = (int)($_POST['parent_id'] ?? 0);
    $sort = max(-9999, min(9999, (int)($_POST['sort_order'] ?? 0)));
    if ($act === 'create' || $act === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 100) $err[] = 'Album name is required (max 100 characters).';
        if (mb_strlen($desc) > 600) $err[] = 'Description is too long (max 600).';
        if ($parent && !category_by_id($parent)) $err[] = 'Unknown parent album.';
        if ($act === 'update') {
            if (!category_by_id($id)) $err[] = 'Album not found.';
            elseif ($parent && category_would_loop($id, $parent)) $err[] = 'An album cannot be moved inside itself or its own sub-albums.';
        }
        if (!$err) {
            if ($act === 'create') {
                q('INSERT INTO categories (parent_id, name, slug, description, sort_order, created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())', [$parent ?: null, $name, unique_category_slug($name), $desc, $sort]);
                flash('ok', 'Album "' . $name . '" created.');
            } else {
                $old = category_by_id($id);
                $slug = $old['name'] === $name ? $old['slug'] : unique_category_slug($name, $id);
                $cover = (int)($_POST['clear_cover'] ?? 0) === 1 ? null : $old['cover_image_id'];
                q('UPDATE categories SET parent_id = ?, name = ?, slug = ?, description = ?, sort_order = ?, cover_image_id = ? WHERE id = ?', [$parent ?: null, $name, $slug, $desc, $sort, $cover, $id]);
                flash('ok', 'Album saved.');
            }
            redirect('admin/categories');
        }
    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $c = category_by_id($id);
        if ($c) {
            // sub-albums and pictures move up one level so nothing is lost
            q('UPDATE categories SET parent_id = ? WHERE parent_id = ?', [$c['parent_id'], $id]);
            q('UPDATE images SET category_id = ? WHERE category_id = ?', [$c['parent_id'], $id]);
            q('DELETE FROM categories WHERE id = ?', [$id]);
            flash('ok', 'Album "' . $c['name'] . '" deleted. Its pictures and sub-albums moved up one level.');
        }
        redirect('admin/categories');
    }
}
$flat = category_tree_flat();
?>
<div class="admin-shell">
<?= admin_nav('categories') ?>
<div class="admin-body">
  <h1 class="page-title">Albums &amp; sub-albums</h1>
  <?php if ($err): ?><div class="alert error"><?php foreach ($err as $x): ?><div><?= e($x) ?></div><?php endforeach; ?></div><?php endif; ?>

  <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h2>New album</h2>
    <div class="grid3">
      <label class="field">Name<input name="name" required maxlength="100" placeholder="Nature"></label>
      <label class="field">Inside<select name="parent_id"><?= category_options(null, [], '— Top level —') ?></select></label>
      <label class="field">Order<input type="number" name="sort_order" value="0" min="-9999" max="9999"></label>
      <label class="field span3">Description (optional)<input name="description" maxlength="600"></label>
    </div>
    <button class="btn primary" type="submit">Create album</button>
  </form>

  <h2>Your albums <small class="muted">(<?= count($flat) ?>)</small></h2>
  <?php if (!$flat): ?><div class="empty"><div class="empty-ico">🗂️</div><p>No albums yet. Create your first one above - nest them as deep as you like.</p></div><?php endif; ?>
  <?php foreach ($flat as $c): $exclude = category_descendant_ids($c['id']); ?>
    <details class="cat-row" style="--depth:<?= (int)$c['depth'] ?>">
      <summary>
        <span class="cat-name"><?= str_repeat('<i class="indent"></i>', (int)$c['depth']) ?>
          <?php if ($c['cover']): ?><img class="cat-cover" src="<?= e(upload_url($c['cover']['thumb_path'])) ?>" alt=""><?php else: ?><span class="cat-cover empty">📁</span><?php endif; ?>
          <strong><?= e($c['name']) ?></strong> <small class="muted">/<?= e($c['slug']) ?> · <?= (int)$c['count'] ?> pics</small></span>
        <a class="btn small" href="<?= e(category_url($c)) ?>">View</a>
      </summary>
      <form method="post" class="form cat-edit"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="grid3">
          <label class="field">Name<input name="name" value="<?= e($c['name']) ?>" required maxlength="100"></label>
          <label class="field">Inside<select name="parent_id"><?= category_options($c['parent_id'], $exclude, '— Top level —') ?></select></label>
          <label class="field">Order<input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>" min="-9999" max="9999"></label>
          <label class="field span3">Description<input name="description" value="<?= e($c['description']) ?>" maxlength="600"></label>
        </div>
        <?php if ($c['cover_image_id']): ?><label class="check"><input type="checkbox" name="clear_cover" value="1"> Remove the chosen cover (use the newest picture instead)</label><?php endif; ?>
        <p class="muted small">Tip: to pick a cover, open any picture in this album → Edit → “Use as the cover of its album”.</p>
        <div class="row"><button class="btn primary small" type="submit">Save</button></div>
      </form>
      <form method="post" class="inline-form" data-confirm="Delete this album? Its pictures and sub-albums will move up one level."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn danger small" type="submit">Delete album</button></form>
    </details>
  <?php endforeach; ?>
</div></div>
