<?php
declare(strict_types=1);

/** All categories with rolled-up counts and covers. Cached per request. */
function categories_all(bool $reload = false): array
{
    static $cache = null;
    if ($cache !== null && !$reload) return $cache;
    $rows = q_all('SELECT * FROM categories ORDER BY sort_order, name');
    $by = [];
    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $r['parent_id'] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
        $r['own_count'] = 0; $r['count'] = 0; $r['cover'] = null; $r['cover_id'] = 0;
        $by[$r['id']] = $r;
    }
    foreach (q_all("SELECT category_id, COUNT(*) n, MAX(id) mid FROM images WHERE status = 'published' AND category_id IS NOT NULL GROUP BY category_id") as $r) {
        $cid = (int)$r['category_id'];
        if (!isset($by[$cid])) continue;
        $by[$cid]['own_count'] = (int)$r['n'];
        $by[$cid]['cover_id'] = (int)$r['mid'];
    }
    // explicit covers
    $covers = [];
    $ids = array_values(array_filter(array_map(fn($c) => (int)$c['cover_image_id'], $by)));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q_all("SELECT id, thumb_path, color FROM images WHERE id IN ($in) AND status = 'published'", $ids) as $r) $covers[(int)$r['id']] = $r;
    }
    // latest published image per category (for auto covers)
    $latest = [];
    $mids = array_values(array_filter(array_map(fn($c) => $c['cover_id'], $by)));
    if ($mids) {
        $in = implode(',', array_fill(0, count($mids), '?'));
        foreach (q_all("SELECT id, thumb_path, color FROM images WHERE id IN ($in)", $mids) as $r) $latest[(int)$r['id']] = $r;
    }
    // children map + roll-up
    $children = [];
    foreach ($by as $id => $c) $children[(int)($c['parent_id'] ?? 0)][] = $id;
    $roll = function (int $id) use (&$roll, &$by, &$children, $covers, $latest): array {
        $c = &$by[$id];
        $count = $c['own_count'];
        $bestId = $c['cover_id'];
        foreach ($children[$id] ?? [] as $ch) {
            [$cc, $cid] = $roll($ch);
            $count += $cc;
            if ($cid > $bestId) $bestId = $cid;
        }
        $c['count'] = $count;
        $c['cover_id'] = $bestId;
        $explicit = (int)$c['cover_image_id'];
        if ($explicit && isset($covers[$explicit])) $c['cover'] = $covers[$explicit];
        elseif ($bestId && isset($latest[$bestId])) $c['cover'] = $latest[$bestId];
        elseif ($bestId) {
            $r = q_row('SELECT id, thumb_path, color FROM images WHERE id = ?', [$bestId]);
            $c['cover'] = $r;
        }
        return [$count, $bestId];
    };
    foreach ($children[0] ?? [] as $rid) $roll($rid);
    // orphans (should not exist) are ignored
    $cache = $by;
    return $cache;
}
function category_by_id(int $id): ?array { return categories_all()[$id] ?? null; }
function category_by_slug(string $slug): ?array
{
    foreach (categories_all() as $c) if ($c['slug'] === $slug) return $c;
    return null;
}
function category_children(?int $parent): array
{
    return array_values(array_filter(categories_all(), fn($c) => $c['parent_id'] === $parent));
}
function category_descendant_ids(int $id): array
{
    $out = [$id];
    foreach (category_children($id) as $c) $out = array_merge($out, category_descendant_ids($c['id']));
    return $out;
}
function category_trail(int $id): array
{
    $all = categories_all();
    $trail = [];
    $guard = 0;
    while (isset($all[$id]) && $guard++ < 50) {
        array_unshift($trail, $all[$id]);
        $id = $all[$id]['parent_id'] ?? 0;
        if (!$id) break;
    }
    return $trail;
}
function category_url(array $c): string { return url('category/' . $c['slug']); }
function category_path_label(int $id, string $sep = ' › '): string
{
    return implode($sep, array_map(fn($c) => $c['name'], category_trail($id)));
}
/** Flat list ordered as a tree with depth, for <select> menus. */
function category_tree_flat(?int $parent = null, int $depth = 0, array $exclude = []): array
{
    $out = [];
    foreach (category_children($parent) as $c) {
        if (in_array($c['id'], $exclude, true)) continue;
        $c['depth'] = $depth;
        $out[] = $c;
        $out = array_merge($out, category_tree_flat($c['id'], $depth + 1, $exclude));
    }
    return $out;
}
function category_options(?int $selected = null, array $exclude = [], string $blank = '— No category —'): string
{
    $h = '<option value="0">' . e($blank) . '</option>';
    foreach (category_tree_flat(null, 0, $exclude) as $c) {
        $h .= '<option value="' . $c['id'] . '"' . ($selected === $c['id'] ? ' selected' : '') . '>'
            . str_repeat('— ', $c['depth']) . e($c['name']) . '</option>';
    }
    return $h;
}
function unique_category_slug(string $name, ?int $ignoreId = null): string
{
    $base = slugify($name, 60) ?: 'album';
    $slug = $base; $n = 2;
    while (true) {
        $row = q_val('SELECT id FROM categories WHERE slug = ?' . ($ignoreId ? ' AND id <> ' . (int)$ignoreId : ''), [$slug]);
        if (!$row) return $slug;
        $slug = $base . '-' . $n++;
    }
}
/** Would making $parent the parent of $id create a loop? */
function category_would_loop(int $id, int $parent): bool
{
    return in_array($parent, category_descendant_ids($id), true);
}
