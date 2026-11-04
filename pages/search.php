<?php
$qs = trim((string)($_GET['q'] ?? ''));
$qs = mb_substr(preg_replace('/\s+/u', ' ', $qs) ?? '', 0, 100);
$sort = current_sort();
$noindex = true;
$pageTitle = $qs !== '' ? 'Search: ' . $qs : 'Search';
$images = []; $total = 0; $cats = [];
$p = pagination_info(0, per_page(), 1);
if ($qs !== '') {
    // every word must match title, description, tag or album name
    $where = []; $params = [];
    foreach (array_slice(explode(' ', $qs), 0, 6) as $w) {
        $like = '%' . like_escape($w) . '%';
        $where[] = '(i.title LIKE ? OR i.description LIKE ? OR u.username LIKE ? OR c.name LIKE ? OR i.id IN (SELECT it.image_id FROM image_tags it JOIN tags t ON t.id = it.tag_id WHERE t.name LIKE ?))';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    [$vis, $vp] = visible_sql();
    $total = (int)q_val('SELECT COUNT(*) FROM images i JOIN users u ON u.id = i.user_id LEFT JOIN categories c ON c.id = i.category_id WHERE ' . $vis . ' AND (' . $w . ')', array_merge($vp, $params));
    $p = pagination_info($total, per_page(), current_page_num());
    $order = match ($sort) { 'old' => 'i.id ASC', 'popular' => 'i.likes_count DESC, i.id DESC', 'views' => 'i.views DESC, i.id DESC', default => 'i.id DESC' };
    $images = q_all('SELECT ' . IMG_SELECT . ' ' . IMG_FROM . " WHERE $vis AND ($w) ORDER BY $order LIMIT " . (int)$p['per'] . ' OFFSET ' . (int)$p['offset'], array_merge($vp, $params));
    foreach (categories_all() as $c) if (stripos($c['name'], $qs) !== false) $cats[] = $c;
}
?>
<?= breadcrumbs([['Home', url('')], ['Search', null]]) ?>
<h1 class="page-title">Search</h1>
<form class="search-big" action="<?= e(url('search')) ?>" method="get">
  <?php if (setting('pretty_urls', '1') !== '1'): ?><input type="hidden" name="r" value="search"><?php endif; ?>
  <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search titles, descriptions, tags, albums, people…" maxlength="100" autofocus>
  <button class="btn primary" type="submit">Search</button>
</form>
<?php if ($qs !== ''): ?>
  <p class="muted"><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($qs) ?>”</p>
  <?php if ($cats): ?><h2 class="h3">Matching albums</h2><?= album_cards_html($cats) ?><?php endif; ?>
  <?= sort_bar('search', ['q' => $qs], $sort) ?>
  <?= gallery_html($images, 'No pictures matched your search.') ?>
  <?= pagination_html($p, 'search', ['q' => $qs] + ($sort !== 'new' ? ['sort' => $sort] : [])) ?>
<?php endif; ?>
