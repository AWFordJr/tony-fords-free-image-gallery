<?php
declare(strict_types=1);

const ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
const THUMB_MAX = 720;
const DISPLAY_MAX = 2200;

/* ---------- URLs ---------- */
function image_url(array $i): string
{
    $s = $i['slug'] ?? '';
    return url('image/' . (int)$i['id'] . ($s !== '' ? '-' . $s : ''));
}
function thumb_url(array $i): string { return upload_url($i['thumb_path']); }
function display_url(array $i): string { return upload_url($i['display_path']); }
function original_url(array $i): string { return upload_url($i['file_path']); }

/* ---------- visibility ---------- */
/** SQL fragment (+params) limiting images to what the current visitor may see. Table alias: i */
function visible_sql(): array
{
    $u = current_user();
    if ($u && $u['role'] === 'admin') return ['1=1', []];
    if ($u) return ["(i.status = 'published' OR i.user_id = ?)", [(int)$u['id']]];
    return ["i.status = 'published'", []];
}

const IMG_SELECT = 'i.*, u.username AS username, u.display_name AS author, c.name AS category_name, c.slug AS category_slug';
const IMG_FROM = 'FROM images i JOIN users u ON u.id = i.user_id LEFT JOIN categories c ON c.id = i.category_id';

function fetch_images(string $where, array $params, string $orderKey = 'new', int $limit = 30, int $offset = 0): array
{
    [$vis, $vp] = visible_sql();
    $order = match ($orderKey) {
        'old' => 'i.id ASC',
        'popular' => 'i.likes_count DESC, i.id DESC',
        'views' => 'i.views DESC, i.id DESC',
        default => 'i.id DESC',
    };
    $sql = 'SELECT ' . IMG_SELECT . ' ' . IMG_FROM . " WHERE $vis AND ($where) ORDER BY $order LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset;
    return q_all($sql, array_merge($vp, $params));
}
function count_images(string $where, array $params): int
{
    [$vis, $vp] = visible_sql();
    return (int)q_val('SELECT COUNT(*) FROM images i WHERE ' . $vis . ' AND (' . $where . ')', array_merge($vp, $params));
}
function fetch_image(int $id): ?array
{
    [$vis, $vp] = visible_sql();
    return q_row('SELECT ' . IMG_SELECT . ' ' . IMG_FROM . " WHERE i.id = ? AND $vis", array_merge([$id], $vp));
}

/* ---------- tags ---------- */
function parse_tags(string $raw): array
{
    $out = [];
    foreach (preg_split('/[,\n;]+/', $raw) ?: [] as $t) {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags($t)) ?? '');
        $t = ltrim($t, '#');
        if ($t === '' || mb_strlen($t) > 40) continue;
        $slug = slugify($t, 50);
        if ($slug === '') continue;
        $out[$slug] = $t;
        if (count($out) >= 15) break;
    }
    return $out; // slug => name
}
function save_tags(int $imageId, array $tags): void
{
    q('DELETE FROM image_tags WHERE image_id = ?', [$imageId]);
    foreach ($tags as $slug => $name) {
        q('INSERT INTO tags (name, slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)', [$name, $slug]);
        $tid = (int)db()->lastInsertId();
        q('INSERT IGNORE INTO image_tags (image_id, tag_id) VALUES (?, ?)', [$imageId, $tid]);
    }
    q('DELETE FROM tags WHERE id NOT IN (SELECT tag_id FROM image_tags)');
}
function image_tags(int $imageId): array
{
    return q_all('SELECT t.name, t.slug FROM tags t JOIN image_tags it ON it.tag_id = t.id WHERE it.image_id = ? ORDER BY t.name', [$imageId]);
}

/* ---------- likes / favourites ---------- */
function voter_id(bool $create = false): ?string
{
    $u = current_user();
    if ($u) return 'u' . (int)$u['id'];
    if (setting('allow_guest_likes', '1') !== '1') return null;
    $tok = $_COOKIE['tfg_g'] ?? '';
    if (!is_string($tok) || !preg_match('/^[a-f0-9]{32}$/', $tok)) {
        if (!$create) return null;
        $tok = bin2hex(random_bytes(16));
        setcookie('tfg_g', $tok, ['expires' => time() + 86400 * 730, 'path' => base_path() . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['tfg_g'] = $tok;
    }
    return 'g' . $tok;
}
function liked_ids(array $imageIds): array
{
    $v = voter_id();
    if ($v === null || !$imageIds) return [];
    $imageIds = array_map('intval', $imageIds);
    $in = implode(',', array_fill(0, count($imageIds), '?'));
    $rows = q_all("SELECT image_id FROM likes WHERE voter = ? AND image_id IN ($in)", array_merge([$v], $imageIds));
    return array_column($rows, 'image_id');
}
function toggle_like(int $imageId): array
{
    $v = voter_id(true);
    if ($v === null) return ['ok' => false, 'error' => 'Likes are limited to registered users.'];
    $u = current_user();
    $has = q_val('SELECT 1 FROM likes WHERE image_id = ? AND voter = ?', [$imageId, $v]);
    if ($has) {
        q('DELETE FROM likes WHERE image_id = ? AND voter = ?', [$imageId, $v]);
        $liked = false;
    } else {
        q('INSERT IGNORE INTO likes (image_id, voter, user_id, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$imageId, $v, $u ? $u['id'] : null]);
        $liked = true;
    }
    $n = (int)q_val('SELECT COUNT(*) FROM likes WHERE image_id = ?', [$imageId]);
    q('UPDATE images SET likes_count = ? WHERE id = ?', [$n, $imageId]);
    return ['ok' => true, 'liked' => $liked, 'count' => $n];
}

/* ---------- storage ---------- */
function safe_upload_path(string $rel): ?string
{
    $rel = str_replace('\\', '/', $rel);
    if ($rel === '' || str_contains($rel, '..') || $rel[0] === '/' || !preg_match('#^[a-z]/[0-9]{4}/[0-9]{2}/[a-f0-9]{32}\.[a-z0-9]{3,4}$#', $rel)) return null;
    $full = UPLOAD_DIR . '/' . $rel;
    $dir = realpath(dirname($full));
    $root = realpath(UPLOAD_DIR);
    if ($dir === false || $root === false || !str_starts_with($dir, $root . DIRECTORY_SEPARATOR)) return null;
    return $dir . '/' . basename($full);
}
function delete_image_files(array $img): void
{
    $paths = array_unique([$img['file_path'], $img['thumb_path'], $img['display_path']]);
    foreach ($paths as $rel) {
        $p = safe_upload_path((string)$rel);
        if ($p !== null && is_file($p)) @unlink($p);
    }
}
function delete_image(array $img): void
{
    delete_image_files($img);
    q('UPDATE categories SET cover_image_id = NULL WHERE cover_image_id = ?', [$img['id']]);
    q('DELETE FROM images WHERE id = ?', [$img['id']]); // likes/comments/tags rows cascade
    q('DELETE FROM tags WHERE id NOT IN (SELECT tag_id FROM image_tags)');
}

/* ---------- GD helpers ---------- */
function gd_new(int $w, int $h, bool $alpha = true)
{
    $im = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    }
    return $im;
}
function gd_fit($src, int $max)
{
    $w = imagesx($src); $h = imagesy($src);
    if ($w <= $max && $h <= $max) return $src;
    $r = $max / max($w, $h);
    $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
    $dst = gd_new($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}
function gd_save_lossy($im, string $path, int $quality): string
{
    // returns extension used
    if (function_exists('imagewebp')) {
        imagepalettetotruecolor($im);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        if (@imagewebp($im, $path, $quality)) return 'webp';
    }
    // JPEG fallback: flatten on white
    $w = imagesx($im); $h = imagesy($im);
    $flat = imagecreatetruecolor($w, $h);
    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
    imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
    imagejpeg($flat, $path, $quality);
    return 'jpg';
}
function gd_avg_color($im): string
{
    $px = imagecreatetruecolor(1, 1);
    imagecopyresampled($px, $im, 0, 0, 0, 0, 1, 1, imagesx($im), imagesy($im));
    $c = imagecolorat($px, 0, 0);
    return sprintf('#%02x%02x%02x', ($c >> 16) & 255, ($c >> 8) & 255, $c & 255);
}

/** Pull friendly, privacy-safe EXIF (GPS is intentionally never stored). */
function read_exif(string $file): array
{
    if (!function_exists('exif_read_data')) return [];
    $x = @exif_read_data($file, 'ANY_TAG', true);
    if (!is_array($x)) return [];
    $get = static function (string $k) use ($x) {
        foreach (['IFD0', 'EXIF'] as $sec) if (isset($x[$sec][$k])) return $x[$sec][$k];
        return null;
    };
    $rat = static function ($v): ?float {
        if (is_numeric($v)) return (float)$v;
        if (is_string($v) && preg_match('#^(-?\d+)/(\d+)$#', $v, $m) && (int)$m[2] != 0) return $m[1] / $m[2];
        return null;
    };
    $clean = static fn($s) => trim(preg_replace('/[^\P{C}]+/u', '', (string)$s) ?? '');
    $o = [];
    $make = $clean($get('Make') ?? ''); $model = $clean($get('Model') ?? '');
    if ($model !== '') $o['Camera'] = (stripos($model, $make) === 0 || $make === '') ? $model : "$make $model";
    $lens = $clean($get('UndefinedTag:0xA434') ?? ($get('LensModel') ?? ''));
    if ($lens !== '') $o['Lens'] = $lens;
    if (($f = $rat($get('FocalLength'))) !== null) $o['Focal length'] = rtrim(rtrim(number_format($f, 1), '0'), '.') . ' mm';
    if (($f = $rat($get('FNumber'))) !== null && $f > 0) $o['Aperture'] = 'f/' . rtrim(rtrim(number_format($f, 1), '0'), '.');
    $et = $get('ExposureTime');
    if ($et !== null && ($f = $rat($et)) !== null && $f > 0) $o['Shutter'] = ($f >= 1 ? rtrim(rtrim(number_format($f, 1), '0'), '.') : '1/' . (int)round(1 / $f)) . ' s';
    $iso = $get('ISOSpeedRatings');
    if (is_array($iso)) $iso = $iso[0] ?? null;
    if ($iso !== null && is_numeric($iso)) $o['ISO'] = (string)(int)$iso;
    $dt = $get('DateTimeOriginal');
    if (is_string($dt) && preg_match('/^(\d{4}):(\d\d):(\d\d) (\d\d:\d\d)/', $dt, $m)) $o['Taken'] = "$m[1]-$m[2]-$m[3] $m[4]";
    $sw = $clean($get('Software') ?? '');
    if ($sw !== '') $o['Software'] = mb_substr($sw, 0, 60);
    return $o;
}

/**
 * Validate, sanitise and store one uploaded image.
 * Returns ['ok'=>true,'id'=>..] or ['ok'=>false,'error'=>..]
 */
function process_upload(array $f, array $user, array $meta): array
{
    @ini_set('memory_limit', '768M');
    @set_time_limit(120);
    $name = (string)($f['name'] ?? 'image');
    $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is larger than the server allows (' . human_size(max_upload_bytes()) . ').',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file received.',
            default => 'Upload failed (code ' . $err . ').',
        }];
    }
    $tmp = (string)$f['tmp_name'];
    if (!is_uploaded_file($tmp) && !(PHP_SAPI === 'cli' && defined('TFG_SEED'))) return ['ok' => false, 'error' => 'Invalid upload.'];
    $size = (int)filesize($tmp);
    if ($size <= 0) return ['ok' => false, 'error' => 'File is empty.'];
    if ($size > max_upload_bytes()) return ['ok' => false, 'error' => 'File is too large (max ' . human_size(max_upload_bytes()) . ').'];

    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$fi->file($tmp);
    if (!isset(ALLOWED_MIMES[$mime])) return ['ok' => false, 'error' => 'Only JPG, PNG, GIF and WebP images are allowed.'];
    $info = @getimagesize($tmp);
    if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) {
        return ['ok' => false, 'error' => 'That file is not a valid image.'];
    }
    $maxMp = max(1, (int)setting('max_pixels_mp', '60'));
    if ($info[0] * $info[1] > $maxMp * 1000000) return ['ok' => false, 'error' => "Image is too large in pixels (max {$maxMp} megapixels)."];

    // daily limit (non-admins)
    $lim = (int)setting('daily_upload_limit', '0');
    if ($lim > 0 && $user['role'] !== 'admin') {
        $n = (int)q_val('SELECT COUNT(*) FROM images WHERE user_id = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 DAY)', [$user['id']]);
        if ($n >= $lim) return ['ok' => false, 'error' => "Daily upload limit reached ($lim)."];
    }

    $raw = file_get_contents($tmp);
    // GIFs are stored byte-for-byte (to keep animation), so refuse any that smuggle server-side code
    if ($mime === 'image/gif' && is_string($raw) && preg_match('/<\?(?:php|=)|<script|<%/i', $raw)) {
        return ['ok' => false, 'error' => 'That GIF contains embedded code and was rejected.'];
    }
    $src = $raw !== false ? @imagecreatefromstring($raw) : false;
    unset($raw);
    if (!$src) return ['ok' => false, 'error' => 'Could not read that image.'];

    $exif = $mime === 'image/jpeg' ? read_exif($tmp) : [];
    // orientation
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = @exif_read_data($tmp);
        $ori = (int)($o['Orientation'] ?? 1);
        $rot = match ($ori) { 3, 4 => 180, 6, 5 => -90, 8, 7 => 90, default => 0 };
        if ($rot !== 0) { $r = imagerotate($src, $rot, 0); if ($r) $src = $r; }
        if (in_array($ori, [2, 4, 5, 7], true)) imageflip($src, IMG_FLIP_HORIZONTAL);
    }
    $w = imagesx($src); $h = imagesy($src);

    $sub = date('Y') . '/' . date('m');
    foreach (['o', 'm', 't'] as $d) {
        $dir = UPLOAD_DIR . "/$d/$sub";
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Uploads folder is not writable.'];
    }
    $base = bin2hex(random_bytes(16));
    $ext = ALLOWED_MIMES[$mime];
    $relO = "o/$sub/$base.$ext";
    $pathO = UPLOAD_DIR . '/' . $relO;

    // 1) sanitised original (re-encoded so hidden payloads can't survive); GIFs keep animation
    $ok = match ($mime) {
        'image/gif' => @copy($tmp, $pathO),
        'image/png' => (function () use ($src, $pathO) { imagesavealpha($src, true); return imagepng($src, $pathO, 6); })(),
        'image/webp' => @imagewebp($src, $pathO, 92),
        default => imagejpeg($src, $pathO, 92),
    };
    if (!$ok || !is_file($pathO)) return ['ok' => false, 'error' => 'Could not save the image.'];
    @chmod($pathO, 0644);
    $finalSize = (int)filesize($pathO);

    // 2) display + 3) thumbnail
    if ($mime === 'image/gif') {
        $relM = $relO;
    } else {
        $disp = gd_fit($src, DISPLAY_MAX);
        $pm = UPLOAD_DIR . "/m/$sub/$base.tmp";
        $e1 = gd_save_lossy($disp, $pm, 86);
        $relM = "m/$sub/$base.$e1";
        rename($pm, UPLOAD_DIR . '/' . $relM);
        @chmod(UPLOAD_DIR . '/' . $relM, 0644);
        if ($disp !== $src) unset($disp);
    }
    $th = gd_fit($src, THUMB_MAX);
    $pt = UPLOAD_DIR . "/t/$sub/$base.tmp";
    $e2 = gd_save_lossy($th, $pt, 80);
    $relT = "t/$sub/$base.$e2";
    rename($pt, UPLOAD_DIR . '/' . $relT);
    @chmod(UPLOAD_DIR . '/' . $relT, 0644);
    $color = gd_avg_color($th);
    unset($th, $src);

    $title = trim((string)($meta['title'] ?? ''));
    if ($title === '') {
        $title = trim(preg_replace('/[_\-.]+/', ' ', pathinfo($name, PATHINFO_FILENAME)) ?? '');
        $title = preg_replace('/[^\p{L}\p{N} \'&,()!+-]+/u', '', $title) ?? '';
    }
    $title = mb_substr($title !== '' ? $title : 'Untitled', 0, 150);
    $slug = slugify($title, 60);
    $status = ($user['role'] !== 'admin' && setting('moderation', '0') === '1') ? 'pending' : 'published';
    $cat = isset($meta['category_id']) && (int)$meta['category_id'] > 0 && category_by_id((int)$meta['category_id']) ? (int)$meta['category_id'] : null;

    q('INSERT INTO images (user_id, category_id, title, slug, description, file_path, thumb_path, display_path, mime, width, height, filesize, color, exif, status, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',
        [$user['id'], $cat, $title, $slug, mb_substr((string)($meta['description'] ?? ''), 0, 5000), $relO, $relT, $relM, $mime, $w, $h, $finalSize, $color,
            $exif ? json_encode($exif, JSON_UNESCAPED_UNICODE) : null, $status]);
    $id = (int)db()->lastInsertId();
    if (!empty($meta['tags'])) save_tags($id, $meta['tags']);
    return ['ok' => true, 'id' => $id, 'status' => $status, 'title' => $title, 'slug' => $slug, 'thumb' => thumb_url(['thumb_path' => $relT]), 'url' => url('image/' . $id . ($slug !== '' ? '-' . $slug : ''))];
}
