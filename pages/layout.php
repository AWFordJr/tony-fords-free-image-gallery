<?php
/** Site layout. Variables from the page: $pageTitle, $pageDesc, $ogImage, $canonical, $noindex, $content, $__page */
$siteTitle = (string)setting('site_title', "Tony Ford's Free Image Gallery");
$theme = active_theme();
$themes = themes();
$tone = $themes[$theme]['tone'];
$u = current_user();
$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $siteTitle : $siteTitle;
$desc = $pageDesc ?? (string)setting('tagline', '');
$og = $ogImage ?? abs_url('') . '';
$ogImg = isset($ogImage) ? $ogImage : (($_SERVER['HTTP_HOST'] ?? '') !== '' ? (is_https() ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)$_SERVER['HTTP_HOST']) . base_path() . '/assets/img/og-image.jpg' : '');
$fxDefault = setting('effects_default', '1');
header('Content-Type: text/html; charset=utf-8');
$nav = [
    ['', 'Home', 'home'],
    ['categories', 'Albums', 'categories'],
    ['popular', 'Popular', 'popular'],
    ['tags', 'Tags', 'tags'],
    ['favorites', 'Favorites', 'favorites'],
];
?><!doctype html>
<html lang="en" data-theme="<?= e($theme) ?>" data-tone="<?= e($tone) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(mb_substr(strip_tags((string)$desc), 0, 200)) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($siteTitle) ?>">
<meta property="og:title" content="<?= e($pageTitle ?? $siteTitle) ?>">
<meta property="og:description" content="<?= e(mb_substr(strip_tags((string)$desc), 0, 200)) ?>">
<meta property="og:type" content="website">
<?php if ($ogImg): ?><meta property="og:image" content="<?= e($ogImg) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<meta name="theme-color" content="<?= $tone === 'light' ? '#f4f6fb' : '#0e1220' ?>">
<link rel="icon" href="<?= e(base_path()) ?>/assets/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/themes.css')) ?>">
</head>
<body class="page-<?= e(str_replace('/', '-', $__page)) ?>"
      data-base="<?= e(base_path()) ?>" data-csrf="<?= e(csrf_token()) ?>" data-api="<?= e(url('api')) ?>"
      data-pretty="<?= e(setting('pretty_urls', '1')) ?>"
      data-fx-default="<?= e($fxDefault) ?>" data-slide="<?= (int)setting('slideshow_seconds', '4') ?>"
      data-allow-themes="<?= e(setting('allow_visitor_themes', '1')) ?>" data-site-theme="<?= e(site_theme()) ?>"
      data-logged="<?= $u ? '1' : '0' ?>" data-layout="<?= e(setting('layout', 'justified')) ?>"
      data-download="<?= e(setting('allow_download', '1')) ?>">
<canvas id="fx" aria-hidden="true"></canvas>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="hdr-in">
    <a class="brand" href="<?= e(url('')) ?>" aria-label="<?= e($siteTitle) ?> home">
      <img src="<?= e(asset('img/logo-64.png')) ?>" alt="Tony Ford" width="44" height="44">
      <span class="brand-text"><strong><?= e($siteTitle) ?></strong><small><?= e(setting('tagline', '')) ?></small></span>
    </a>
    <button class="icon-btn nav-toggle" type="button" aria-label="Menu" aria-expanded="false" data-nav-toggle>☰</button>
    <nav class="main-nav" id="main-nav" aria-label="Main">
      <?php foreach ($nav as [$r, $label, $key]): ?>
        <a href="<?= e(url($r)) ?>" class="<?= $__page === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <form class="search" action="<?= e(url('search')) ?>" method="get" role="search">
      <?php if (setting('pretty_urls', '1') !== '1'): ?><input type="hidden" name="r" value="search"><?php endif; ?>
      <input type="search" name="q" placeholder="Search pictures, tags…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search" maxlength="100">
    </form>
    <div class="hdr-actions">
      <button class="icon-btn" type="button" data-toggle-tone title="Light / dark" aria-label="Toggle light or dark">◐</button>
      <button class="icon-btn" type="button" data-toggle-fx title="Animated effects on/off" aria-label="Toggle animated effects" aria-pressed="true">✨</button>
      <?php if (setting('allow_visitor_themes', '1') === '1'): ?>
        <button class="icon-btn" type="button" data-open-themes title="Choose a theme" aria-label="Choose a theme">🎨</button>
      <?php endif; ?>
      <?php if (can_upload()): ?><a class="btn primary small" href="<?= e(url('upload')) ?>">⬆ Upload</a><?php endif; ?>
      <?php if ($u): ?>
        <div class="usermenu">
          <button class="user-btn" type="button" data-usermenu aria-haspopup="true" aria-expanded="false"><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['username'], 0, 1))) ?></span><span class="uname"><?= e($u['username']) ?></span></button>
          <div class="menu" hidden>
            <a href="<?= e(url('user/' . $u['username'])) ?>">My pictures</a>
            <a href="<?= e(url('favorites')) ?>">Favorites</a>
            <a href="<?= e(url('account')) ?>">Account</a>
            <?php if ($u['role'] === 'admin'): ?><a href="<?= e(url('admin')) ?>">Admin dashboard</a><?php endif; ?>
            <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit">Log out</button></form>
          </div>
        </div>
      <?php else: ?>
        <a class="btn small" href="<?= e(url('login')) ?>">Log in</a>
        <?php if (registration_open()): ?><a class="btn small primary" href="<?= e(url('register')) ?>">Sign up</a><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="main" class="wrap">
  <?php foreach (flashes() as $f): ?>
    <div class="alert <?= e($f['t']) ?>" role="status"><?= e($f['m']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<footer class="site-footer">
  <div class="foot-in">
    <img src="<?= e(asset('img/logo-64.png')) ?>" alt="" width="40" height="40" loading="lazy">
    <p class="credit"><?= e(TFG_CREDIT) ?></p>
    <p class="muted small">Free for non-commercial use. v<?= e(TFG_VERSION) ?></p>
  </div>
</footer>

<?php if (setting('allow_visitor_themes', '1') === '1'): ?>
<dialog id="theme-dialog" class="dialog">
  <form method="dialog" class="dlg-head"><h2>Choose a theme</h2><button class="icon-btn" aria-label="Close">✕</button></form>
  <p class="muted small">Your pick is remembered on this device<?= $u ? ' and on your account' : '' ?>. Animated effects pause when the tab is hidden and can be turned off with the ✨ button.</p>
  <?= theme_picker($theme, 'visitor') ?>
</dialog>
<?php endif; ?>

<div id="lightbox" class="lb" hidden role="dialog" aria-modal="true" aria-label="Image viewer">
  <div class="lb-top">
    <span class="lb-count" id="lb-count"></span>
    <span class="lb-title" id="lb-title"></span>
    <span class="lb-tools">
      <button type="button" class="lb-btn" id="lb-like" aria-label="Like" title="Like (L)">♡ <span id="lb-likes">0</span></button>
      <button type="button" class="lb-btn" id="lb-play" aria-label="Start slideshow" title="Slideshow (Space)">▶</button>
      <button type="button" class="lb-btn" id="lb-full" aria-label="Fullscreen" title="Fullscreen (F)">⛶</button>
      <a class="lb-btn" id="lb-open" href="#" title="Open page (I)">ⓘ</a>
      <button type="button" class="lb-btn" id="lb-close" aria-label="Close" title="Close (Esc)">✕</button>
    </span>
  </div>
  <button type="button" class="lb-nav lb-prev" id="lb-prev" aria-label="Previous">‹</button>
  <button type="button" class="lb-nav lb-next" id="lb-next" aria-label="Next">›</button>
  <div class="lb-stage" id="lb-stage"><img id="lb-img" alt="" draggable="false"></div>
  <div class="lb-progress" id="lb-progress"></div>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/effects.js')) ?>" defer></script>
<script src="<?= e(asset('js/lightbox.js')) ?>" defer></script>
<?php if ($__page === 'upload'): ?><script src="<?= e(asset('js/upload.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
