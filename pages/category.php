<?php
$cat = category_by_slug($slug);
if (!$cat) not_found();
$sort = current_sort();
$ids = category_descendant_ids($cat['id']);
$in = implode(',', array_map('intval', $ids));
$where = "i.category_id IN ($in)";
$total = count_images($where, []);
$p = pagination_info($total, per_page(), current_page_num());
$images = fetch_images($where, [], $sort, per_page(), $p['offset']);
$children = category_children($cat['id']);
$trail = category_trail($cat['id']);
$crumbs = [['Home', url('')], ['Albums', url('categories')]];
foreach ($trail as $t) $crumbs[] = [$t['name'], category_url($t)];
$pageTitle = $cat['name'] . ($p['page'] > 1 ? ' · Page ' . $p['page'] : '');
$pageDesc = $cat['description'] !== '' ? $cat['description'] : 'Pictures in ' . $cat['name'];
$canonical = abs_url('category/' . $cat['slug'], $p['page'] > 1 ? ['page' => $p['page']] : []);
if ($images) $ogImage = (is_https() ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) . thumb_url($images[0]);
?>
<?= breadcrumbs($crumbs) ?>
<header class="cat-head">
  <h1 class="page-title"><?= e($cat['name']) ?></h1>
  <?php if ($cat['description'] !== ''): ?><p class="muted"><?= e($cat['description']) ?></p><?php endif; ?>
  <p class="muted small"><?= number_format($total) ?> picture<?= $total === 1 ? '' : 's' ?><?= count($ids) > 1 ? ' (including sub-albums)' : '' ?>
    <?php if (can_upload()): ?> · <a href="<?= e(url('upload', ['cat' => $cat['id']])) ?>">Upload here</a><?php endif; ?>
    <?php if (is_admin()): ?> · <a href="<?= e(url('admin/categories')) ?>">Manage albums</a><?php endif; ?></p>
</header>
<?php if ($children): ?>
  <section class="block"><div class="block-head"><h2>Sub-albums</h2></div><?= album_cards_html($children) ?></section>
<?php endif; ?>
<section class="block">
  <?= sort_bar('category/' . $cat['slug'], [], $sort) ?>
  <?= gallery_html($images, 'No pictures in this album yet.') ?>
  <?= pagination_html($p, 'category/' . $cat['slug'], $sort !== 'new' ? ['sort' => $sort] : []) ?>
</section>
