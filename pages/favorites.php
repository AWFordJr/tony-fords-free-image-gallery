<?php
$noindex = true;
$pageTitle = 'Favorites';
$v = voter_id();
$images = []; $total = 0;
$p = pagination_info(0, per_page(), 1);
if ($v !== null) {
    $where = 'i.id IN (SELECT image_id FROM likes WHERE voter = ?)';
    $total = count_images($where, [$v]);
    $p = pagination_info($total, per_page(), current_page_num());
    $images = fetch_images($where, [$v], 'new', per_page(), $p['offset']);
}
?>
<?= breadcrumbs([['Home', url('')], ['Favorites', null]]) ?>
<h1 class="page-title">♥ Your favorites</h1>
<?php if (!current_user()): ?><p class="muted small">Saved in this browser<?= registration_open() ? ' - <a href="' . e(url('register')) . '">sign up</a> to keep them on every device' : '' ?>.</p><?php endif; ?>
<?= gallery_html($images, 'Tap the heart on any picture to save it here.') ?>
<?= pagination_html($p, 'favorites', []) ?>
