<?php
$pageTitle = 'Albums';
$pageDesc = 'Browse all albums and sub-albums';
$canonical = abs_url('categories');
$roots = category_children(null);
function render_cat_branch(array $cat, int $depth): void {
    $kids = category_children($cat['id']);
    echo '<li><a href="' . e(category_url($cat)) . '">' . e($cat['name']) . '</a> <small class="muted">(' . (int)$cat['count'] . ')</small>';
    if ($kids) { echo '<ul>'; foreach ($kids as $k) render_cat_branch($k, $depth + 1); echo '</ul>'; }
    echo '</li>';
}
?>
<?= breadcrumbs([['Home', url('')], ['Albums', null]]) ?>
<h1 class="page-title">Albums</h1>
<?php if (!$roots): ?>
  <div class="empty"><div class="empty-ico">📁</div><p>No albums yet.<?= is_admin() ? ' <a href="' . e(url('admin/categories')) . '">Create one in the admin panel</a>.' : '' ?></p></div>
<?php else: ?>
  <?= album_cards_html($roots) ?>
  <details class="tree-box"><summary>Show full album tree</summary>
    <ul class="tree"><?php foreach ($roots as $r) render_cat_branch($r, 0); ?></ul>
  </details>
<?php endif; ?>
