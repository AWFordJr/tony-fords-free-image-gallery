<?php
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$out = fn(string $loc, ?string $mod = null) => print('<url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? '<lastmod>' . gmdate('Y-m-d', strtotime($mod . ' UTC')) . '</lastmod>' : '') . "</url>\n");
$out(abs_url(''));
$out(abs_url('categories'));
$out(abs_url('popular'));
$out(abs_url('about'));
foreach (categories_all() as $c) $out(abs_url('category/' . $c['slug']));
foreach (q_all("SELECT id, slug, created_at FROM images WHERE status = 'published' ORDER BY id DESC LIMIT 5000") as $r) {
    $out(abs_url('image/' . $r['id'] . ($r['slug'] !== '' ? '-' . $r['slug'] : '')), $r['created_at']);
}
echo '</urlset>';
