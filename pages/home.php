<?php
$sort = current_sort();
$perPage = per_page();
$total = count_images('1=1', []);
$p = pagination_info($total, $perPage, current_page_num());
$images = fetch_images('1=1', [], $sort, $perPage, $p['offset']);
$top = category_children(null);
$top = array_values(array_filter($top, fn($c) => $c['count'] > 0 || can_upload()));
$pageTitle = $p['page'] > 1 ? 'Page ' . $p['page'] : '';
$pageDesc = (string)setting('tagline', '');
$canonical = abs_url('', $p['page'] > 1 ? ['page' => $p['page']] : []);
$stats = [
    'images' => (int)q_val("SELECT COUNT(*) FROM images WHERE status='published'"),
    'albums' => count(categories_all()),
    'people' => (int)q_val("SELECT COUNT(DISTINCT user_id) FROM images WHERE status='published'"),
];
?>
<section class="hero">
  <img class="hero-avatar" src="<?= e(asset('img/logo.png')) ?>" alt="Tony Ford" width="112" height="112">
  <div class="hero-text">
    <h1><?= e(setting('site_title')) ?></h1>
    <p><?= e(setting('tagline')) ?></p>
    <div class="hero-stats">
      <span><strong><?= number_format($stats['images']) ?></strong> pictures</span>
      <span><strong><?= number_format($stats['albums']) ?></strong> albums</span>
      <span><strong><?= number_format($stats['people']) ?></strong> <?= $stats['people'] === 1 ? 'photographer' : 'photographers' ?></span>
    </div>
    <div class="hero-cta">
      <?php if (can_upload()): ?><a class="btn primary" href="<?= e(url('upload')) ?>">⬆ Upload pictures</a>
      <?php elseif (!current_user() && registration_open()): ?><a class="btn primary" href="<?= e(url('register')) ?>">Join &amp; share your pictures</a><?php endif; ?>
      <a class="btn" href="<?= e(url('categories')) ?>">Browse albums</a>
    </div>
  </div>
</section>

<?php if ($top): ?>
<section class="block">
  <div class="block-head"><h2>Albums</h2><a href="<?= e(url('categories')) ?>">See all →</a></div>
  <?= album_cards_html(array_slice($top, 0, 8)) ?>
</section>
<?php endif; ?>

<section class="block">
  <div class="block-head"><h2>Latest pictures</h2></div>
  <?= sort_bar('', [], $sort) ?>
  <?= gallery_html($images, can_upload() ? 'No pictures yet - upload your first one!' : 'No pictures have been shared yet.') ?>
  <?= pagination_html($p, '', $sort !== 'new' ? ['sort' => $sort] : []) ?>
</section>
