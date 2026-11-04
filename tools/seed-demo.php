<?php
/**
 * Tony Ford's Free Image Gallery - optional demo content
 * by Anthony W. Ford, Jr. (Tony Ford) - awfordjr@gmail.com
 * Free for non-commercial use only. See LICENSE.txt.
 *
 * Fills a freshly installed gallery with example categories, sub-categories and
 * ORIGINAL abstract pictures that are drawn right here with PHP's GD library
 * (no photos, nothing copyrighted). Handy for trying out the themes.
 *
 * Run from the command line, in the gallery folder:    php tools/seed-demo.php
 * Delete the demo pictures any time in Admin -> Pictures.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('TFG_INSTALLING', true);   // we boot by hand below (no web session needed)
define('TFG_SEED', true);
$root = dirname(__DIR__);
if (!is_file($root . '/config.php')) { fwrite(STDERR, "config.php not found - run the web installer first.\n"); exit(1); }
require $root . '/config.php';
require $root . '/inc/bootstrap.php';
mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

try {
    $admin = (q_all("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")[0] ?? null);
} catch (Throwable $e) { fwrite(STDERR, "Could not read the database: " . $e->getMessage() . "\n"); exit(1); }
if (!$admin) { fwrite(STDERR, "No admin account found.\n"); exit(1); }
if ((int)q_val('SELECT COUNT(*) FROM images') > 0 && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "This gallery already has pictures. Run with --force to add the demo content anyway.\n"); exit(1);
}

mt_srand(20260611);

function hex2rgb(string $h): array { return [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))]; }
function mix(array $a, array $b, float $t): array { return [(int)($a[0] + ($b[0] - $a[0]) * $t), (int)($a[1] + ($b[1] - $a[1]) * $t), (int)($a[2] + ($b[2] - $a[2]) * $t)]; }
function col($im, array $c, int $alpha = 0) { return imagecolorallocatealpha($im, $c[0], $c[1], $c[2], $alpha); }

function vgrad($im, int $w, int $h, array $top, array $bot, int $y0 = 0, int $y1 = -1): void
{
    if ($y1 < 0) $y1 = $h;
    for ($y = $y0; $y < $y1; $y++) {
        $c = mix($top, $bot, ($y - $y0) / max(1, $y1 - $y0 - 1));
        imageline($im, 0, $y, $w, $y, col($im, $c));
    }
}
function glow($im, int $cx, int $cy, int $r, array $c, int $maxAlpha = 70): void
{
    for ($i = $r; $i > 0; $i -= max(1, intdiv($r, 40))) {
        $a = (int)(127 - (127 - $maxAlpha) * (1 - $i / $r) ** 2);
        imagefilledellipse($im, $cx, $cy, $i * 2, $i * 2, col($im, $c, min(127, max(0, $a))));
    }
}
function stars($im, int $w, int $h, int $n, int $maxY): void
{
    for ($i = 0; $i < $n; $i++) {
        $x = mt_rand(0, $w); $y = mt_rand(0, $maxY); $b = mt_rand(150, 255);
        imagesetpixel($im, $x, $y, col($im, [$b, $b, 255], mt_rand(0, 40)));
        if (mt_rand(0, 12) === 0) imagefilledellipse($im, $x, $y, 3, 3, col($im, [255, 255, 255], 60));
    }
}
function ridge($im, int $w, int $h, int $base, int $amp, array $c, float $rough): void
{
    $pts = [0, $h];
    $y = $base; $ph = mt_rand(0, 1000) / 100;
    for ($x = 0; $x <= $w; $x += 6) {
        $yy = $base - (sin($x / $w * 5 + $ph) * 0.5 + sin($x / $w * 13 + $ph * 2) * 0.25 + (mt_rand(-100, 100) / 100) * $rough) * $amp;
        $pts[] = $x; $pts[] = (int)$yy;
    }
    $pts[] = $w; $pts[] = $h;
    imagefilledpolygon($im, $pts, col($im, $c));
}

/** Each painter returns a GD image. */
$painters = [
    'mountains' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        vgrad($im, $w, $h, hex2rgb($p[0]), hex2rgb($p[1]));
        glow($im, (int)($w * mt_rand(25, 75) / 100), (int)($h * .42), (int)($h * .35), hex2rgb($p[2]), 60);
        $sun = hex2rgb($p[2]); imagefilledellipse($im, (int)($w * .5), (int)($h * .38), (int)($h * .16), (int)($h * .16), col($im, $sun, 10));
        for ($i = 0; $i < 4; $i++) ridge($im, $w, $h, (int)($h * (0.55 + $i * 0.11)), (int)($h * (0.20 - $i * 0.03)), mix(hex2rgb($p[3]), [8, 10, 18], $i / 3), 0.35);
        return $im;
    },
    'ocean' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        $hz = (int)($h * .5);
        vgrad($im, $w, $h, hex2rgb($p[0]), hex2rgb($p[1]), 0, $hz);
        vgrad($im, $w, $h, hex2rgb($p[2]), hex2rgb($p[3]), $hz, $h);
        glow($im, (int)($w * .5), $hz, (int)($h * .3), hex2rgb($p[1]), 70);
        for ($k = 0; $k < 22; $k++) {
            $y = $hz + (int)(($k / 22) ** 1.6 * ($h - $hz));
            $amp = 2 + $k * 0.7; $per = 40 + $k * 14; $c = mix(hex2rgb($p[1]), hex2rgb($p[3]), $k / 22);
            for ($x = 0; $x < $w; $x += 3) imageline($im, $x, (int)($y + sin($x / $per + $k) * $amp), $x + 3, (int)($y + sin(($x + 3) / $per + $k) * $amp), col($im, $c, 85));
        }
        return $im;
    },
    'forest' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        vgrad($im, $w, $h, hex2rgb($p[0]), hex2rgb($p[1]));
        glow($im, (int)($w * .7), (int)($h * .25), (int)($h * .5), hex2rgb($p[2]), 75);
        for ($layer = 0; $layer < 4; $layer++) {
            $c = mix(hex2rgb($p[3]), [4, 10, 8], $layer / 3);
            $n = 10 + $layer * 6;
            for ($i = 0; $i < $n; $i++) {
                $x = mt_rand(-20, $w + 20); $th = (int)($h * (0.25 + $layer * 0.08 + mt_rand(0, 20) / 100)); $bw = (int)($w / $n * 1.8);
                $base = (int)($h * (0.72 + $layer * 0.07));
                for ($t = 0; $t < 4; $t++) {
                    $yt = $base - $th + (int)($t * $th / 4.2);
                    imagefilledpolygon($im, [$x, $yt, $x - (int)($bw * (0.3 + $t * 0.25)), $yt + (int)($th / 3.3), $x + (int)($bw * (0.3 + $t * 0.25)), $yt + (int)($th / 3.3)], col($im, $c));
                }
            }
            imagefilledrectangle($im, 0, (int)($h * (0.9 + $layer * 0.02)), $w, $h, col($im, $c));
        }
        return $im;
    },
    'nebula' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, col($im, hex2rgb($p[0])));
        for ($i = 0; $i < 9; $i++) glow($im, mt_rand(0, $w), mt_rand(0, $h), (int)(min($w, $h) * mt_rand(25, 60) / 100), hex2rgb($p[1 + $i % 3]), mt_rand(88, 108));
        stars($im, $w, $h, (int)($w * $h / 2800), $h);
        return $im;
    },
    'aurora' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        vgrad($im, $w, $h, hex2rgb($p[0]), hex2rgb($p[1]));
        stars($im, $w, $h, (int)($w * $h / 3500), (int)($h * .7));
        for ($b = 0; $b < 3; $b++) {
            $c = hex2rgb($p[2 + $b % 2]); $ph = mt_rand(0, 600) / 100; $yb = (int)($h * (0.35 + $b * 0.08));
            for ($x = 0; $x < $w; $x += 2) {
                $y = $yb + sin($x / $w * 6 + $ph) * $h * .08 + sin($x / $w * 15 + $ph) * $h * .03;
                $len = (int)($h * (0.25 + 0.12 * sin($x / $w * 9 + $ph * 2)));
                for ($j = 0; $j < $len; $j += 3) imagefilledrectangle($im, $x, (int)($y - $j), $x + 1, (int)($y - $j) + 3, col($im, $c, 126 - (int)(38 * (1 - $j / $len) ** 1.4 )));
            }
        }
        ridge($im, $w, $h, (int)($h * .9), (int)($h * .08), [4, 6, 12], 0.5);
        return $im;
    },
    'geometry' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        vgrad($im, $w, $h, hex2rgb($p[0]), hex2rgb($p[1]));
        for ($i = 0; $i < 26; $i++) {
            $c = hex2rgb($p[2 + $i % (count($p) - 2)]); $x = mt_rand(0, $w); $y = mt_rand(0, $h); $s = mt_rand((int)($h * .06), (int)($h * .3));
            if ($i % 3 === 0) imagefilledellipse($im, $x, $y, $s, $s, col($im, $c, mt_rand(60, 100)));
            elseif ($i % 3 === 1) imagefilledrectangle($im, $x, $y, $x + $s, $y + (int)($s * .6), col($im, $c, mt_rand(70, 105)));
            else imagefilledpolygon($im, [$x, $y, $x + $s, $y + (int)($s / 3), $x + (int)($s / 2), $y + $s], col($im, $c, mt_rand(60, 100)));
        }
        return $im;
    },
    'skyline' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        vgrad($im, $w, $h, hex2rgb($p[0]), hex2rgb($p[1]));
        glow($im, (int)($w * .5), (int)($h * .78), (int)($h * .55), hex2rgb($p[2]), 80);
        stars($im, $w, $h, 60, (int)($h * .4));
        for ($layer = 0; $layer < 2; $layer++) {
            $x = 0;
            while ($x < $w) {
                $bw = mt_rand((int)($w * .03), (int)($w * .08)); $bh = (int)($h * (0.18 + mt_rand(0, 38) / 100 - $layer * .08));
                $c = $layer ? mix(hex2rgb($p[3]), hex2rgb($p[1]), .5) : hex2rgb($p[3]);
                imagefilledrectangle($im, $x, $h - $bh, $x + $bw, $h, col($im, $c));
                for ($wy = $h - $bh + 8; $wy < $h - 8; $wy += 11) for ($wx = $x + 5; $wx < $x + $bw - 5; $wx += 9) {
                    if (mt_rand(0, 100) < ($layer ? 18 : 34)) imagefilledrectangle($im, $wx, $wy, $wx + 3, $wy + 5, col($im, hex2rgb($p[4 + mt_rand(0, 1)]), mt_rand(0, 40)));
                }
                $x += $bw + mt_rand(0, 4);
            }
        }
        return $im;
    },
    'waves' => function (int $w, int $h, array $p) {
        $im = imagecreatetruecolor($w, $h);
        vgrad($im, $w, $h, hex2rgb($p[0]), hex2rgb($p[1]));
        for ($k = 0; $k < 14; $k++) {
            $c = mix(hex2rgb($p[2]), hex2rgb($p[3]), $k / 13); $ph = $k * .5; $amp = $h * (0.04 + $k * .006); $yb = $h * (0.2 + $k * .055);
            $pts = [0, $h];
            for ($x = 0; $x <= $w; $x += 8) { $pts[] = $x; $pts[] = (int)($yb + sin($x / $w * (3 + $k * .3) + $ph) * $amp); }
            $pts[] = $w; $pts[] = $h;
            imagefilledpolygon($im, $pts, col($im, $c, 78));
        }
        return $im;
    },
];

$palettes = [
    'sunset'   => ['#1b1245', '#e0516b', '#ffd27a', '#2b1840'],
    'alpine'   => ['#10243f', '#8fb7d6', '#fff1c9', '#1d3347'],
    'dusk'     => ['#2a1459', '#ff7a59', '#ffd89b', '#1c1033'],
    'emerald'  => ['#07211c', '#1f7a5c', '#c8ff8a', '#0f3d31'],
    'misty'    => ['#14232e', '#6f8f99', '#e9f1d0', '#1e3a3a'],
    'lagoon'   => ['#0b3b5a', '#7fe3e0', '#06607a', '#021e3a'],
    'coral'    => ['#ff9f7a', '#ffe3b0', '#0a6d8f', '#032b45'],
    'violet'   => ['#070216', '#7b2cff', '#ff3fa4', '#27d6ff'],
    'ember'    => ['#0a0306', '#ff4d1a', '#ffb42e', '#a3123a'],
    'ice'      => ['#030a1a', '#36a9ff', '#aef3ff', '#7e8bff'],
    'borealis' => ['#02060f', '#0a2a3a', '#38ffb0', '#8a5cff'],
    'candy'    => ['#1a1033', '#3a1d73', '#ff6ec7', '#5be7ff'],
    'neon'     => ['#12002b', '#ff2e97', '#ff9d2e', '#0d0221', '#fff27a', '#7df9ff'],
    'noir'     => ['#05060d', '#1a2145', '#4a5bd6', '#0b0f24', '#ffd76a', '#ffffff'],
    'tropic'   => ['#04182a', '#0f4d73', '#22c3a6', '#ffcf4a'],
];

// category tree => list of [title, painter, palette, w, h, tags]
$tree = [
    ['Nature', 'Mountains, oceans and forests, painted in code.', [
        ['Mountains', 'Peaks at sunrise, dusk and in the mist.', [
            ['Golden Ridge', 'mountains', 'sunset', 1600, 1000, 'mountains, sunrise, sunset'],
            ['Blue Alps', 'mountains', 'alpine', 1500, 1000, 'mountains, snow, blue'],
            ['Dusk Over The Range', 'mountains', 'dusk', 1600, 900, 'mountains, dusk, purple'],
            ['Quiet Valley', 'mountains', 'misty', 1000, 1400, 'mountains, mist, valley'],
            ['Last Light', 'mountains', 'sunset', 1200, 1200, 'mountains, sunset, square'],
        ]],
        ['Ocean', 'Water, waves and endless horizons.', [
            ['Lagoon Morning', 'ocean', 'lagoon', 1600, 1000, 'ocean, lagoon, calm'],
            ['Coral Horizon', 'ocean', 'coral', 1700, 900, 'ocean, coral, horizon'],
            ['Deep Blue', 'waves', 'tropic', 1400, 1000, 'ocean, waves, deep'],
            ['Tide Lines', 'waves', 'lagoon', 1000, 1300, 'waves, tide, blue'],
        ]],
        ['Forest', 'Pines, fog and golden light.', [
            ['Emerald Pines', 'forest', 'emerald', 1500, 1000, 'forest, pines, green'],
            ['Misty Woods', 'forest', 'misty', 1100, 1500, 'forest, mist, woods'],
            ['Evening Grove', 'forest', 'dusk', 1600, 1000, 'forest, evening, grove'],
        ]],
    ]],
    ['Cosmos', 'Nebulae, auroras and the night sky.', [
        ['Nebulae', 'Clouds of colour among the stars.', [
            ['Violet Nebula', 'nebula', 'violet', 1600, 1000, 'space, nebula, violet'],
            ['Ember Cloud', 'nebula', 'ember', 1400, 1000, 'space, nebula, fire'],
            ['Frozen Veil', 'nebula', 'ice', 1600, 900, 'space, nebula, ice'],
            ['Candy Galaxy', 'nebula', 'candy', 1000, 1000, 'space, galaxy, pink'],
        ]],
        ['Aurora', 'Northern lights over dark ridges.', [
            ['Borealis One', 'aurora', 'borealis', 1600, 1000, 'aurora, night, green'],
            ['Borealis Two', 'aurora', 'borealis', 1100, 1500, 'aurora, night, portrait'],
            ['Polar Night', 'aurora', 'ice', 1700, 950, 'aurora, polar, blue'],
        ]],
    ]],
    ['Abstract', 'Colour, shape and motion.', [
        ['Geometry', 'Circles, squares and triangles.', [
            ['Pop Shapes', 'geometry', 'candy', 1400, 1000, 'abstract, shapes, pop'],
            ['Neon Blocks', 'geometry', 'neon', 1000, 1000, 'abstract, neon, blocks'],
            ['Quiet Forms', 'geometry', 'noir', 1500, 1000, 'abstract, minimal'],
        ]],
        ['Waves', 'Layered curves.', [
            ['Sunset Ribbons', 'waves', 'dusk', 1600, 900, 'abstract, waves, sunset'],
            ['Coral Flow', 'waves', 'coral', 1200, 1400, 'abstract, waves, coral'],
        ]],
    ]],
    ['City Lights', 'Skylines after dark.', [
        ['Neon Nights', 'Synthwave skylines.', [
            ['Midnight Avenue', 'skyline', 'neon', 1600, 1000, 'city, neon, night'],
            ['Chrome Horizon', 'skyline', 'neon', 1500, 900, 'city, synthwave, skyline'],
        ]],
        ['Blue Hour', 'Skylines at dusk.', [
            ['Downtown Dusk', 'skyline', 'noir', 1600, 1000, 'city, dusk, blue'],
            ['Quiet Streets', 'skyline', 'noir', 1000, 1300, 'city, quiet, night'],
        ]],
    ]],
];

function mkcat(string $name, string $desc, ?int $parent, int $sort): int
{
    $slug = unique_category_slug($name);
    q('INSERT INTO categories (parent_id, name, slug, description, sort_order, created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())', [$parent, $name, $slug, $desc, $sort]);
    $id = (int)db()->lastInsertId();
    categories_all(true);
    return $id;
}

$tmpDir = sys_get_temp_dir() . '/tfg-seed-' . bin2hex(random_bytes(4));
@mkdir($tmpDir, 0700, true);
$total = 0; $firstOfCat = [];
$sort = 0;
foreach ($tree as [$topName, $topDesc, $subs]) {
    $topId = mkcat($topName, $topDesc, null, $sort++);
    foreach ($subs as [$subName, $subDesc, $pics]) {
        $subId = mkcat($subName, $subDesc, $topId, $sort++);
        foreach ($pics as [$title, $painter, $pal, $w, $h, $tags]) {
            $im = $painters[$painter]($w, $h, $palettes[$pal]);
            $f = $tmpDir . '/' . slugify($title) . '.jpg';
            imagejpeg($im, $f, 90);
            unset($im);
            $r = process_upload(['name' => basename($f), 'tmp_name' => $f, 'error' => UPLOAD_ERR_OK, 'size' => filesize($f)], $admin, [
                'title' => $title, 'category_id' => $subId, 'tags' => parse_tags($tags . ', demo'),
                'description' => "A demo picture drawn with PHP (GD) - \"$title\". Replace it with your own photos any time.",
            ]);
            @unlink($f);
            if (!$r['ok']) { fwrite(STDERR, "  ! $title: {$r['error']}\n"); continue; }
            $total++;
            $firstOfCat[$subId] ??= $r['id'];
            $firstOfCat[$topId] ??= $r['id'];
            // spread dates over the last month, add a few views and likes
            q('UPDATE images SET created_at = UTC_TIMESTAMP() - INTERVAL ? HOUR, views = ? WHERE id = ?', [$total * 17, mt_rand(5, 400), $r['id']]);
            $nl = mt_rand(0, 9);
            for ($k = 0; $k < $nl; $k++) q('INSERT IGNORE INTO likes (image_id, voter, user_id, created_at) VALUES (?,?,NULL,UTC_TIMESTAMP())', [$r['id'], 'demo' . bin2hex(random_bytes(6))]);
            q('UPDATE images SET likes_count = (SELECT COUNT(*) FROM likes WHERE image_id = ?) WHERE id = ?', [$r['id'], $r['id']]);
            echo "  + $title\n";
        }
    }
}
foreach ($firstOfCat as $cid => $iid) q('UPDATE categories SET cover_image_id = ? WHERE id = ?', [$iid, $cid]);
@rmdir($tmpDir);
echo "\nDone - added $total demo pictures in " . count($firstOfCat) . " categories.\n";
