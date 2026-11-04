<?php
$sort = in_array($_GET['sort'] ?? '', ['views', 'popular'], true) ? $_GET['sort'] : 'popular';
$total = count_images('1=1', []);
$p = pagination_info($total, per_page(), current_page_num());
$images = fetch_images('1=1', [], $sort, per_page(), $p['offset']);
$pageTitle = 'Popular pictures';
$canonical = abs_url('popular');
?>
<?= breadcrumbs([['Home', url('')], ['Popular', null]]) ?>
<h1 class="page-title">Popular pictures</h1>
<?= sort_bar('popular', [], $sort, ['popular' => 'Most liked', 'views' => 'Most viewed']) ?>
<?= gallery_html($images, 'Nothing popular yet.') ?>
<?= pagination_html($p, 'popular', ['sort' => $sort]) ?>
