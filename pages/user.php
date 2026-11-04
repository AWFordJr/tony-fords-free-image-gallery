<?php
$owner = q_row("SELECT id, username, display_name, bio, role, created_at FROM users WHERE username = ? AND status = 'active'", [$username]);
if (!$owner) not_found();
$where = 'i.user_id = ?';
$total = count_images($where, [$owner['id']]);
$p = pagination_info($total, per_page(), current_page_num());
$images = fetch_images($where, [$owner['id']], current_sort(), per_page(), $p['offset']);
$pageTitle = $owner['display_name'] . "'s pictures";
$canonical = abs_url('user/' . $owner['username']);
$likesTotal = (int)q_val("SELECT COALESCE(SUM(likes_count),0) FROM images WHERE user_id = ? AND status='published'", [$owner['id']]);
?>
<?= breadcrumbs([['Home', url('')], [$owner['display_name'], null]]) ?>
<section class="profile">
  <?php if ($owner['role'] === 'admin'): ?><img class="profile-avatar" src="<?= e(asset('img/logo-128.png')) ?>" alt="" width="84" height="84">
  <?php else: ?><span class="avatar big"><?= e(mb_strtoupper(mb_substr($owner['username'], 0, 1))) ?></span><?php endif; ?>
  <div><h1 class="page-title"><?= e($owner['display_name']) ?></h1>
  <p class="muted small">@<?= e($owner['username']) ?> · joined <?= e(fmt_date($owner['created_at'])) ?> · <?= number_format($total) ?> pictures · ♥ <?= number_format($likesTotal) ?></p>
  <?php if ($owner['bio'] !== ''): ?><p><?= nl2br(e($owner['bio'])) ?></p><?php endif; ?></div>
</section>
<?= sort_bar('user/' . $owner['username'], [], current_sort()) ?>
<?= gallery_html($images, 'No pictures yet.') ?>
<?= pagination_html($p, 'user/' . $owner['username'], current_sort() !== 'new' ? ['sort' => current_sort()] : []) ?>
