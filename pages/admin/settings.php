<?php
$noindex = true;
$pageTitle = 'Settings';
$err = [];
$tzs = DateTimeZone::listIdentifiers();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $title = trim((string)($_POST['site_title'] ?? ''));
    $tag = trim((string)($_POST['tagline'] ?? ''));
    if ($title === '' || mb_strlen($title) > 80) $err[] = 'Site title is required (max 80 characters).';
    if (mb_strlen($tag) > 160) $err[] = 'Tagline is too long (max 160).';
    $mode = ($_POST['mode'] ?? '') === 'open' ? 'open' : 'single';
    $layout = in_array($_POST['layout'] ?? '', ['justified', 'masonry', 'grid'], true) ? $_POST['layout'] : 'justified';
    $tz = (string)($_POST['timezone'] ?? 'UTC');
    if (!in_array($tz, $tzs, true)) $tz = 'UTC';
    $num = fn(string $k, int $min, int $max, int $def) => (string)max($min, min($max, (int)($_POST[$k] ?? $def)));
    $chk = fn(string $k) => isset($_POST[$k]) ? '1' : '0';
    if (!$err) {
        $new = [
            'site_title' => $title, 'tagline' => $tag, 'mode' => $mode, 'layout' => $layout, 'timezone' => $tz,
            'per_page' => $num('per_page', 6, 120, 30),
            'max_upload_mb' => $num('max_upload_mb', 1, 200, 15),
            'max_pixels_mp' => $num('max_pixels_mp', 1, 200, 60),
            'daily_upload_limit' => $num('daily_upload_limit', 0, 100000, 0),
            'slideshow_seconds' => $num('slideshow_seconds', 2, 30, 4),
            'moderation' => $chk('moderation'), 'allow_comments' => $chk('allow_comments'), 'allow_guest_likes' => $chk('allow_guest_likes'),
            'pretty_urls' => $chk('pretty_urls'), 'show_exif' => $chk('show_exif'), 'allow_download' => $chk('allow_download'),
        ];
        foreach ($new as $k => $v) set_setting($k, $v);
        flash('ok', 'Settings saved.' . ($mode === 'open' ? ' Open registration is ON - anyone can sign up and share.' : ' Single Owner mode is ON - only admins can post.'));
        redirect('admin/settings');
    }
}
$s = settings_all();
$sv = fn(string $k, string $d = '') => $_POST[$k] ?? ($s[$k] ?? $d);
$ck = fn(string $k) => (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? isset($_POST[$k]) : ($s[$k] ?? '0') === '1') ? ' checked' : '';
?>
<div class="admin-shell">
<?= admin_nav('settings') ?>
<div class="admin-body">
  <h1 class="page-title">Settings</h1>
  <?php if ($err): ?><div class="alert error"><?php foreach ($err as $x): ?><div><?= e($x) ?></div><?php endforeach; ?></div><?php endif; ?>
  <form method="post" class="form settings-form"><?= csrf_field() ?>
    <section class="card">
      <h2>Who can post?</h2>
      <div class="mode-cards">
        <label class="mode-card"><input type="radio" name="mode" value="single" <?= $sv('mode') !== 'open' ? 'checked' : '' ?>><span class="mc-body"><span class="mc-icon">👤</span><strong>Single owner</strong><small>Only admins post. Everyone else just views, likes and comments.</small></span></label>
        <label class="mode-card"><input type="radio" name="mode" value="open" <?= $sv('mode') === 'open' ? 'checked' : '' ?>><span class="mc-body"><span class="mc-icon">👥</span><strong>Open registration</strong><small>Anyone can sign up and share their own pictures.</small></span></label>
      </div>
      <label class="check"><input type="checkbox" name="moderation" value="1"<?= $ck('moderation') ?>> Hold pictures from non-admins for approval before they appear</label>
    </section>
    <section class="card">
      <h2>Site</h2>
      <div class="grid2">
        <label class="field">Site title<input name="site_title" value="<?= e($sv('site_title')) ?>" required maxlength="80"></label>
        <label class="field">Tagline<input name="tagline" value="<?= e($sv('tagline')) ?>" maxlength="160"></label>
        <label class="field">Time zone<select name="timezone"><?php foreach ($tzs as $tz): ?><option<?= $tz === $sv('timezone', 'UTC') ? ' selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></label>
        <label class="field">Default layout<select name="layout"><?php foreach (['justified' => 'Justified rows', 'masonry' => 'Masonry columns', 'grid' => 'Square grid'] as $k => $l): ?><option value="<?= $k ?>" <?= $sv('layout') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="field">Pictures per page<input type="number" name="per_page" min="6" max="120" value="<?= (int)$sv('per_page', '30') ?>"></label>
        <label class="field">Slideshow seconds<input type="number" name="slideshow_seconds" min="2" max="30" value="<?= (int)$sv('slideshow_seconds', '4') ?>"></label>
      </div>
      <p class="muted small">Colours and effects live on the <a href="<?= e(url('admin/themes')) ?>">Themes</a> page.</p>
    </section>
    <section class="card">
      <h2>Uploads</h2>
      <div class="grid2">
        <label class="field">Max file size (MB)<input type="number" name="max_upload_mb" min="1" max="200" value="<?= (int)$sv('max_upload_mb', '15') ?>"><small class="muted">Your server currently allows <?= e(human_size(server_upload_limit() ?: 0)) ?> per file (php.ini upload_max_filesize / post_max_size).</small></label>
        <label class="field">Max megapixels<input type="number" name="max_pixels_mp" min="1" max="200" value="<?= (int)$sv('max_pixels_mp', '60') ?>"><small class="muted">Protects server memory when resizing.</small></label>
        <label class="field">Daily uploads per user (0 = unlimited)<input type="number" name="daily_upload_limit" min="0" value="<?= (int)$sv('daily_upload_limit', '0') ?>"></label>
      </div>
    </section>
    <section class="card">
      <h2>Features</h2>
      <label class="check"><input type="checkbox" name="allow_comments" value="1"<?= $ck('allow_comments') ?>> Comments on pictures</label>
      <label class="check"><input type="checkbox" name="allow_guest_likes" value="1"<?= $ck('allow_guest_likes') ?>> Let visitors who are not logged in like pictures</label>
      <label class="check"><input type="checkbox" name="show_exif" value="1"<?= $ck('show_exif') ?>> Show camera details (EXIF) on picture pages</label>
      <label class="check"><input type="checkbox" name="allow_download" value="1"<?= $ck('allow_download') ?>> Show a download button for originals</label>
      <label class="check"><input type="checkbox" name="pretty_urls" value="1"<?= $ck('pretty_urls') ?>> Pretty URLs (<code>/category/nature</code>) - needs Apache mod_rewrite or the nginx rules from the README</label>
      <p class="muted small">If a link ever stops working after changing this, open <code>index.php?r=admin/settings</code> directly and switch it back.</p>
    </section>
    <button class="btn primary big" type="submit">Save settings</button>
  </form>
</div></div>
