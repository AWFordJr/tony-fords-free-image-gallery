<?php
$noindex = true;
$pageTitle = 'Upload pictures';
$me = require_uploader();
$preCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$maxBytes = max_upload_bytes();
?>
<?= breadcrumbs([['Home', url('')], ['Upload', null]]) ?>
<h1 class="page-title">Upload pictures</h1>
<noscript><div class="alert error">Uploading needs JavaScript. Please enable it in your browser.</div></noscript>
<?php if (setting('moderation', '0') === '1' && $me['role'] !== 'admin'): ?><div class="alert info">New pictures are reviewed by an admin before they appear.</div><?php endif; ?>
<div class="upload-layout" id="uploader" data-max="<?= (int)$maxBytes ?>" data-endpoint="<?= e(url('api/upload')) ?>">
  <section class="card">
    <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Choose pictures or drop them here">
      <div class="dz-ico">⬆</div>
      <strong>Drag &amp; drop pictures here</strong>
      <span>or click to choose files · JPG, PNG, GIF, WebP · up to <?= e(human_size($maxBytes)) ?> each</span>
      <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
    </div>
    <div class="queue" id="queue" aria-live="polite"></div>
  </section>
  <aside class="card form">
    <h2>Details for this batch</h2>
    <label class="field">Album<select id="up-cat"><?= category_options($preCat ?: null, [], '— No album —') ?></select></label>
    <label class="field">Tags <small class="muted">(comma separated)</small><input id="up-tags" maxlength="300" placeholder="sunset, beach, summer"></label>
    <label class="field">Description <small class="muted">(optional, used for every picture)</small><textarea id="up-desc" rows="3" maxlength="2000"></textarea></label>
    <p class="muted small">Each picture's title comes from its file name - you can rename it on the picture page afterwards, or edit the title in the list before uploading.</p>
    <button class="btn primary big" type="button" id="up-start" disabled>Upload <span id="up-count"></span></button>
    <?php if ($me['role'] === 'admin'): ?><p class="muted small"><a href="<?= e(url('admin/categories')) ?>">Manage albums</a></p><?php endif; ?>
  </aside>
</div>
