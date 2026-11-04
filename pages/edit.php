<?php
$noindex = true;
$img = fetch_image($id);
if (!$img) not_found();
if (!can_edit_image($img)) { require_login(); render_error(403, 'Not allowed', 'You can only edit your own pictures.'); }
$pageTitle = 'Edit: ' . $img['title'];
$err = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $cat = (int)($_POST['category_id'] ?? 0);
    if ($title === '' || mb_strlen($title) > 150) $err[] = 'Title is required (max 150 characters).';
    if (mb_strlen($desc) > 5000) $err[] = 'Description is too long.';
    if ($cat && !category_by_id($cat)) $err[] = 'Unknown album.';
    $status = $img['status'];
    if (is_admin() && in_array($_POST['status'] ?? '', ['published', 'pending', 'hidden'], true)) $status = $_POST['status'];
    elseif (!is_admin() && in_array($_POST['status'] ?? '', ['published', 'hidden'], true) && $img['status'] !== 'pending') $status = $_POST['status'];
    if (!$err) {
        q('UPDATE images SET title = ?, slug = ?, description = ?, category_id = ?, status = ? WHERE id = ?',
            [$title, slugify($title, 60), $desc, $cat ?: null, $status, $img['id']]);
        save_tags((int)$img['id'], parse_tags((string)($_POST['tags'] ?? '')));
        if (isset($_POST['make_cover']) && $cat) {
            if (is_admin()) q('UPDATE categories SET cover_image_id = ? WHERE id = ?', [$img['id'], $cat]);
        }
        flash('ok', 'Picture updated.');
        $img = fetch_image($id);
        redirect_to(image_url($img));
    }
}
$tagStr = implode(', ', array_column(image_tags((int)$img['id']), 'name'));
?>
<?= breadcrumbs([['Home', url('')], [$img['title'], image_url($img)], ['Edit', null]]) ?>
<h1 class="page-title">Edit picture</h1>
<?php if ($err): ?><div class="alert error"><?php foreach ($err as $x): ?><div><?= e($x) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="edit-layout">
  <a href="<?= e(image_url($img)) ?>" class="edit-thumb" style="--c:<?= e($img['color']) ?>"><img src="<?= e(thumb_url($img)) ?>" alt="<?= e($img['title']) ?>"></a>
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <label class="field">Title<input name="title" value="<?= e($_POST['title'] ?? $img['title']) ?>" required maxlength="150"></label>
    <label class="field">Description<textarea name="description" rows="5" maxlength="5000"><?= e($_POST['description'] ?? $img['description']) ?></textarea></label>
    <label class="field">Album<select name="category_id"><?= category_options($_POST['category_id'] ?? $img['category_id'] ? (int)($_POST['category_id'] ?? $img['category_id']) : null, [], '— No album —') ?></select></label>
    <label class="field">Tags<input name="tags" value="<?= e($_POST['tags'] ?? $tagStr) ?>" maxlength="300"></label>
    <label class="field">Visibility
      <select name="status">
        <?php if (is_admin() || $img['status'] === 'pending'): ?><option value="pending" <?= $img['status'] === 'pending' ? 'selected' : '' ?>>Pending review</option><?php endif; ?>
        <option value="published" <?= $img['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="hidden" <?= $img['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
      </select></label>
    <?php if (is_admin()): ?><label class="check"><input type="checkbox" name="make_cover" value="1"> Use as the cover of its album</label><?php endif; ?>
    <div class="row"><button class="btn primary" type="submit">Save changes</button> <a class="btn" href="<?= e(image_url($img)) ?>">Cancel</a></div>
  </form>
</div>
