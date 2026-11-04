<?php
declare(strict_types=1);

function breadcrumbs(array $crumbs): string
{
    // crumbs: [label, url|null]
    $h = '<nav class="crumbs" aria-label="Breadcrumb"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
    $n = count($crumbs);
    foreach ($crumbs as $i => [$label, $href]) {
        $h .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        if ($href && $i < $n - 1) $h .= '<a itemprop="item" href="' . e($href) . '"><span itemprop="name">' . e($label) . '</span></a>';
        else $h .= '<span itemprop="name" aria-current="page">' . e($label) . '</span>';
        $h .= '<meta itemprop="position" content="' . ($i + 1) . '"></li>';
    }
    return $h . '</ol></nav>';
}

function layout_toolbar(): string
{
    return '<div class="layout-switch" role="group" aria-label="Layout">'
        . '<button type="button" data-layout="justified" title="Justified rows">▤</button>'
        . '<button type="button" data-layout="masonry" title="Masonry columns">▥</button>'
        . '<button type="button" data-layout="grid" title="Square grid">▦</button></div>';
}

function sort_bar(string $route, array $query, string $current, array $options = ['new' => 'Newest', 'popular' => 'Most liked', 'views' => 'Most viewed', 'old' => 'Oldest']): string
{
    $h = '<div class="toolbar"><div class="chips">';
    foreach ($options as $k => $label) {
        $qq = $query; unset($qq['page']);
        if ($k !== 'new') $qq['sort'] = $k; else unset($qq['sort']);
        $h .= '<a class="chip' . ($k === $current ? ' on' : '') . '" href="' . e(url($route, $qq)) . '">' . e($label) . '</a>';
    }
    return $h . '</div>' . layout_toolbar() . '</div>';
}
function current_sort(): string
{
    $s = $_GET['sort'] ?? 'new';
    return in_array($s, ['new', 'old', 'popular', 'views'], true) ? $s : 'new';
}

function gallery_html(array $images, string $empty = 'Nothing here yet.'): string
{
    if (!$images) return '<div class="empty"><div class="empty-ico">🖼️</div><p>' . e($empty) . '</p></div>';
    $liked = array_flip(liked_ids(array_column($images, 'id')));
    $layout = in_array(setting('layout', 'justified'), ['justified', 'masonry', 'grid'], true) ? setting('layout', 'justified') : 'justified';
    $h = '<div class="gallery ' . e($layout) . '" data-gallery>';
    foreach ($images as $i) {
        $ar = max(0.2, min(5.0, $i['width'] / max(1, $i['height'])));
        $isLiked = isset($liked[$i['id']]);
        $pend = $i['status'] !== 'published' ? '<span class="badge ' . e($i['status']) . '">' . e(ucfirst($i['status'])) . '</span>' : '';
        $h .= '<figure class="g-item" style="--ar:' . number_format($ar, 4, '.', '') . ';--g:' . (int)round($ar * 100) . ';--c:' . e($i['color']) . '">'
            . '<a class="g-link" href="' . e(image_url($i)) . '" data-id="' . (int)$i['id'] . '" data-full="' . e(display_url($i)) . '"'
            . ' data-title="' . e($i['title']) . '" data-w="' . (int)$i['width'] . '" data-h="' . (int)$i['height'] . '" data-likes="' . (int)$i['likes_count'] . '"'
            . ' data-liked="' . ($isLiked ? 1 : 0) . '">'
            . '<img src="' . e(thumb_url($i)) . '" width="' . (int)$i['width'] . '" height="' . (int)$i['height'] . '" alt="' . e($i['title']) . '" loading="lazy" decoding="async">'
            . $pend
            . '<figcaption class="g-over"><span class="g-title">' . e($i['title']) . '</span><span class="g-meta">by ' . e($i['author']) . '</span></figcaption></a>'
            . '<button type="button" class="g-like' . ($isLiked ? ' on' : '') . '" data-like="' . (int)$i['id'] . '" aria-label="Like" aria-pressed="' . ($isLiked ? 'true' : 'false') . '"><span class="heart">' . ($isLiked ? '♥' : '♡') . '</span> <span class="n">' . (int)$i['likes_count'] . '</span></button>'
            . '</figure>';
    }
    return $h . '</div>';
}

function album_cards_html(array $cats): string
{
    if (!$cats) return '';
    $h = '<div class="albums">';
    foreach ($cats as $c) {
        $h .= '<a class="album" href="' . e(category_url($c)) . '">';
        if ($c['cover']) {
            $h .= '<span class="album-cover" style="--c:' . e($c['cover']['color']) . '"><img src="' . e(upload_url($c['cover']['thumb_path'])) . '" alt="" loading="lazy" decoding="async"></span>';
        } else {
            $h .= '<span class="album-cover empty">📁</span>';
        }
        $kids = count(category_children($c['id']));
        $h .= '<span class="album-info"><strong>' . e($c['name']) . '</strong><small>' . (int)$c['count'] . ' picture' . ($c['count'] == 1 ? '' : 's')
            . ($kids ? ' · ' . $kids . ' sub-album' . ($kids == 1 ? '' : 's') : '') . '</small></span></a>';
    }
    return $h . '</div>';
}

function pagination_html(array $p, string $route, array $query): string
{
    if ($p['pages'] <= 1) return '';
    $cur = $p['page']; $last = $p['pages'];
    $nums = array_unique(array_filter([1, 2, $cur - 2, $cur - 1, $cur, $cur + 1, $cur + 2, $last - 1, $last], fn($n) => $n >= 1 && $n <= $last));
    sort($nums);
    $h = '<nav class="pager" aria-label="Pagination">';
    $h .= $cur > 1 ? '<a rel="prev" href="' . e(page_url($route, $query, $cur - 1)) . '">‹ Prev</a>' : '<span class="dis">‹ Prev</span>';
    $prev = 0;
    foreach ($nums as $n) {
        if ($prev && $n > $prev + 1) $h .= '<span class="gap">…</span>';
        $h .= $n === $cur ? '<span class="cur" aria-current="page">' . $n . '</span>' : '<a href="' . e(page_url($route, $query, $n)) . '">' . $n . '</a>';
        $prev = $n;
    }
    $h .= $cur < $last ? '<a rel="next" href="' . e(page_url($route, $query, $cur + 1)) . '">Next ›</a>' : '<span class="dis">Next ›</span>';
    return $h . '</nav>';
}

function tags_cloud_html(array $tags): string
{
    $h = '<div class="tag-cloud">';
    foreach ($tags as $t) $h .= '<a class="tag" href="' . e(url('tag/' . $t['slug'])) . '">#' . e($t['name']) . (isset($t['n']) ? ' <small>' . (int)$t['n'] . '</small>' : '') . '</a>';
    return $h . '</div>';
}

/** Show a standard "listing" page body (title, optional description, gallery, pager). */
function per_page(): int { return max(6, min(120, (int)setting('per_page', '30'))); }

function admin_nav(string $active): string
{
    $items = ['dashboard' => ['📊', 'Dashboard'], 'images' => ['🖼️', 'Pictures'], 'categories' => ['🗂️', 'Albums'], 'users' => ['👥', 'Users'], 'themes' => ['🎨', 'Themes'], 'settings' => ['⚙️', 'Settings']];
    $h = '<nav class="admin-nav" aria-label="Admin">';
    foreach ($items as $k => [$ico, $label]) {
        $h .= '<a class="' . ($k === $active ? 'active' : '') . '" href="' . e(url($k === 'dashboard' ? 'admin' : 'admin/' . $k)) . '"><span>' . $ico . '</span> ' . e($label) . '</a>';
    }
    return $h . '</nav>';
}
