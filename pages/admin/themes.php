<?php
$noindex = true;
$pageTitle = 'Themes';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $t = (string)($_POST['theme'] ?? '');
    if (!valid_theme($t)) { flash('error', 'Pick a theme from the list.'); }
    else {
        set_setting('theme', $t);
        set_setting('allow_visitor_themes', isset($_POST['allow_visitor_themes']) ? '1' : '0');
        set_setting('effects_default', isset($_POST['effects_default']) ? '1' : '0');
        flash('ok', 'Theme set to ' . themes()[$t]['name'] . '.');
    }
    redirect('admin/themes');
}
$cur = site_theme();
?>
<div class="admin-shell">
<?= admin_nav('themes') ?>
<div class="admin-body">
  <h1 class="page-title">Themes</h1>
  <p class="muted">Pick the look of your whole gallery. Each preview below is a live miniature of the theme. Animated backgrounds are light, pause when the tab is hidden and switch off for visitors who prefer reduced motion.</p>
  <form method="post" class="form"><?= csrf_field() ?>
    <?= theme_picker($cur, 'form', 'theme') ?>
    <section class="card">
      <label class="check"><input type="checkbox" name="allow_visitor_themes" value="1" <?= setting('allow_visitor_themes', '1') === '1' ? 'checked' : '' ?>> Let each visitor choose their own theme (the 🎨 button in the header)</label>
      <label class="check"><input type="checkbox" name="effects_default" value="1" <?= setting('effects_default', '1') === '1' ? 'checked' : '' ?>> Animated backgrounds on by default (visitors can toggle them with ✨)</label>
    </section>
    <button class="btn primary big" type="submit">Save theme</button>
  </form>
</div></div>
