<?php
$tag = q_row('SELECT * FROM tags WHERE slug = ?', [$slug]);
if (!$tag) not_found();
$sort = current_sort();
$where = 'i.id IN (SELECT image_id FROM image_tags WHERE tag_id = ?)';
$total = count_images($where, [$tag['id']]);
$p = pagination_info($total, per_page(), current_page_num());
$images = fetch_images($where, [$tag['id']], $sort, per_page(), $p['offset']);
$pageTitle = '#' . $tag['name'];
$canonical = abs_url('tag/' . $tag['slug']);
?>
<?= breadcrumbs([['Home', url('')], ['Tags', url('tags')], ['#' . $tag['name'], null]]) ?>
<h1 class="page-title">#<?= e($tag['name']) ?></h1>
<p class="muted small"><?= number_format($total) ?> picture<?= $total === 1 ? '' : 's' ?></p>
<?= sort_bar('tag/' . $tag['slug'], [], $sort) ?>
<?= gallery_html($images, 'No pictures with this tag.') ?>
<?= pagination_html($p, 'tag/' . $tag['slug'], $sort !== 'new' ? ['sort' => $sort] : []) ?>
