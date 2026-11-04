<?php
$img = fetch_image($id);
if (!$img) not_found();
$u = current_user();
$canEdit = can_edit_image($img);
$selfUrl = image_url($img);

/* ---- comment actions ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'comment' && setting('allow_comments', '1') === '1') {
        $me = require_login();
        $body = trim((string)($_POST['body'] ?? ''));
        $body = preg_replace("/\r\n?/", "\n", $body) ?? '';
        if ($body === '' || mb_strlen($body) > 1500) { flash('error', 'Write a comment between 1 and 1500 characters.'); }
        elseif ((int)q_val('SELECT COUNT(*) FROM comments WHERE user_id = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 MINUTE)', [$me['id']]) >= 5) { flash('error', 'Slow down a little - too many comments in a minute.'); }
        else {
            q('INSERT INTO comments (image_id, user_id, body, created_at) VALUES (?,?,?,UTC_TIMESTAMP())', [$img['id'], $me['id'], $body]);
            q('UPDATE images SET comments_count = (SELECT COUNT(*) FROM comments WHERE image_id = ?) WHERE id = ?', [$img['id'], $img['id']]);
            flash('ok', 'Comment posted.');
        }
    } elseif ($act === 'delete_comment') {
        $me = require_login();
        $cm = q_row('SELECT * FROM comments WHERE id = ? AND image_id = ?', [(int)($_POST['comment_id'] ?? 0), $img['id']]);
        if ($cm && ($me['role'] === 'admin' || (int)$cm['user_id'] === (int)$me['id'] || (int)$img['user_id'] === (int)$me['id'])) {
            q('DELETE FROM comments WHERE id = ?', [$cm['id']]);
            q('UPDATE images SET comments_count = (SELECT COUNT(*) FROM comments WHERE image_id = ?) WHERE id = ?', [$img['id'], $img['id']]);
            flash('ok', 'Comment deleted.');
        }
    } elseif ($act === 'delete_image' && $canEdit) {
        $cid = $img['category_id'];
        delete_image($img);
        flash('ok', 'Picture deleted.');
        $c = $cid ? category_by_id((int)$cid) : null;
        redirect($c ? 'category/' . $c['slug'] : '');
    }
    redirect_to($selfUrl . '#comments');
}

/* ---- view counter (once per session) ---- */
if ($img['status'] === 'published') {
    $seen = $_SESSION['seen'] ?? [];
    if (!in_array($img['id'], $seen, true)) {
        q('UPDATE images SET views = views + 1 WHERE id = ?', [$img['id']]);
        $img['views']++;
        $seen[] = $img['id'];
        $_SESSION['seen'] = array_slice($seen, -200);
    }
}

$tags = image_tags((int)$img['id']);
$exif = ($img['exif'] && setting('show_exif', '1') === '1') ? (json_decode((string)$img['exif'], true) ?: []) : [];
$liked = in_array($img['id'], liked_ids([$img['id']]), true);
$comments = setting('allow_comments', '1') === '1'
    ? q_all('SELECT c.*, u.username, u.display_name FROM comments c JOIN users u ON u.id = c.user_id WHERE c.image_id = ? ORDER BY c.id ASC LIMIT 200', [$img['id']]) : [];

$catWhere = $img['category_id'] ? 'i.category_id = ?' : 'i.category_id IS NULL';
$catParams = $img['category_id'] ? [$img['category_id']] : [];
$older = fetch_images("$catWhere AND i.id < ?", array_merge($catParams, [$img['id']]), 'new', 1, 0)[0] ?? null;
$newer = fetch_images("$catWhere AND i.id > ?", array_merge($catParams, [$img['id']]), 'old', 1, 0)[0] ?? null;

$related = [];
if ($tags) {
    $tids = q_all('SELECT tag_id FROM image_tags WHERE image_id = ?', [$img['id']]);
    $ids = array_map(fn($r) => (int)$r['tag_id'], $tids);
    $in = implode(',', $ids);
    $related = fetch_images("i.id <> ? AND i.id IN (SELECT image_id FROM image_tags WHERE tag_id IN ($in))", [$img['id']], 'popular', 12, 0);
}
if (count($related) < 6 && $img['category_id']) {
    $have = array_column($related, 'id'); $have[] = $img['id'];
    $in = implode(',', array_map('intval', $have));
    $related = array_merge($related, fetch_images("i.category_id = ? AND i.id NOT IN ($in)", [$img['category_id']], 'new', 12 - count($related), 0));
}

$crumbs = [['Home', url('')]];
if ($img['category_id']) {
    $crumbs[] = ['Albums', url('categories')];
    foreach (category_trail((int)$img['category_id']) as $t) $crumbs[] = [$t['name'], category_url($t)];
}
$crumbs[] = [$img['title'], null];

$pageTitle = $img['title'];
$pageDesc = $img['description'] ? mb_substr(trim(preg_replace('/\s+/', ' ', (string)$img['description'])), 0, 180) : $img['title'] . ' by ' . $img['author'];
$canonical = abs_url('image/' . $img['id'] . ($img['slug'] !== '' ? '-' . $img['slug'] : ''));
$host = (is_https() ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$ogImage = $host . display_url($img);
if ($img['status'] !== 'published') $noindex = true;
?>
<?= breadcrumbs($crumbs) ?>
<article class="viewer">
  <div class="viewer-main">
    <div class="viewer-stage" style="--c:<?= e($img['color']) ?>">
      <?php if ($older): ?><a class="vnav prev" href="<?= e(image_url($older)) ?>" rel="prev" title="Older: <?= e($older['title']) ?>">‹</a><?php endif; ?>
      <a href="<?= e(display_url($img)) ?>" class="g-link single" data-id="<?= (int)$img['id'] ?>" data-full="<?= e(display_url($img)) ?>" data-title="<?= e($img['title']) ?>" data-w="<?= (int)$img['width'] ?>" data-h="<?= (int)$img['height'] ?>" data-likes="<?= (int)$img['likes_count'] ?>" data-liked="<?= $liked ? 1 : 0 ?>" data-url="<?= e($selfUrl) ?>" title="Click to view fullscreen">
        <img src="<?= e(display_url($img)) ?>" width="<?= (int)$img['width'] ?>" height="<?= (int)$img['height'] ?>" alt="<?= e($img['title']) ?>" decoding="async">
      </a>
      <?php if ($newer): ?><a class="vnav next" href="<?= e(image_url($newer)) ?>" rel="next" title="Newer: <?= e($newer['title']) ?>">›</a><?php endif; ?>
    </div>
    <?php if ($img['status'] !== 'published'): ?><div class="alert info">This picture is <strong><?= e($img['status']) ?></strong> - only you and the admins can see it.</div><?php endif; ?>
  </div>

  <aside class="viewer-side">
    <h1 class="page-title"><?= e($img['title']) ?></h1>
    <p class="byline"><a href="<?= e(url('user/' . $img['username'])) ?>"><?= e($img['author']) ?></a> · <?= e(fmt_date($img['created_at'])) ?>
      <?php if ($img['category_id']): ?> · <a href="<?= e(category_url(category_by_id((int)$img['category_id']) ?? ['slug' => ''])) ?>"><?= e(category_path_label((int)$img['category_id'])) ?></a><?php endif; ?></p>

    <div class="actions">
      <button type="button" class="btn like-btn<?= $liked ? ' on' : '' ?>" data-like="<?= (int)$img['id'] ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>"><span class="heart"><?= $liked ? '♥' : '♡' ?></span> <span class="n"><?= (int)$img['likes_count'] ?></span></button>
      <button type="button" class="btn" data-open-lightbox>⛶ Fullscreen</button>
      <?php if (setting('allow_download', '1') === '1'): ?><a class="btn" href="<?= e(original_url($img)) ?>" download>⬇ Download</a><?php endif; ?>
      <?php if ($canEdit): ?><a class="btn" href="<?= e(url('edit/' . $img['id'])) ?>">✎ Edit</a><?php endif; ?>
    </div>

    <?php if ($img['description']): ?><p class="desc"><?= nl2br(e($img['description'])) ?></p><?php endif; ?>
    <?php if ($tags): ?><?= tags_cloud_html($tags) ?><?php endif; ?>

    <dl class="facts">
      <dt>Size</dt><dd><?= (int)$img['width'] ?> × <?= (int)$img['height'] ?> px · <?= e(human_size((int)$img['filesize'])) ?></dd>
      <dt>Views</dt><dd><?= number_format((int)$img['views']) ?></dd>
      <dt>Likes</dt><dd><?= number_format((int)$img['likes_count']) ?></dd>
    </dl>

    <?php if ($exif): ?>
    <details class="exif" open><summary>📷 Camera details</summary>
      <dl class="facts"><?php foreach ($exif as $k => $val): ?><dt><?= e($k) ?></dt><dd><?= e($val) ?></dd><?php endforeach; ?></dl>
    </details>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <form method="post" class="inline-form" data-confirm="Delete this picture permanently?">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete_image">
      <button class="btn danger small" type="submit">🗑 Delete picture</button>
    </form>
    <?php endif; ?>
  </aside>
</article>

<?php if (setting('allow_comments', '1') === '1'): ?>
<section class="block comments" id="comments">
  <div class="block-head"><h2>Comments (<?= count($comments) ?>)</h2></div>
  <?php foreach ($comments as $c): ?>
    <div class="comment">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr($c['username'], 0, 1))) ?></span>
      <div class="cbody"><div class="chead"><a href="<?= e(url('user/' . $c['username'])) ?>"><?= e($c['display_name']) ?></a> <small class="muted"><?= e(ago($c['created_at'])) ?></small>
        <?php if ($u && ($u['role'] === 'admin' || (int)$u['id'] === (int)$c['user_id'] || (int)$u['id'] === (int)$img['user_id'])): ?>
          <form method="post" class="inline-form" data-confirm="Delete this comment?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>"><button class="link-btn" type="submit">delete</button></form>
        <?php endif; ?></div>
        <p><?= nl2br(e($c['body'])) ?></p></div>
    </div>
  <?php endforeach; ?>
  <?php if ($u): ?>
    <form method="post" class="form comment-form"><?= csrf_field() ?><input type="hidden" name="action" value="comment">
      <label class="field">Add a comment<textarea name="body" rows="3" maxlength="1500" required></textarea></label>
      <button class="btn primary" type="submit">Post comment</button></form>
  <?php else: ?>
    <p class="muted"><a href="<?= e(url('login', ['next' => $selfUrl])) ?>">Log in</a> to leave a comment.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="block"><div class="block-head"><h2>More like this</h2></div><?= gallery_html($related) ?></section>
<?php endif; ?>
