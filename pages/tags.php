<?php
$pageTitle = 'Tags';
$canonical = abs_url('tags');
[$vis, $vp] = visible_sql();
$tags = q_all("SELECT t.name, t.slug, COUNT(*) n FROM tags t JOIN image_tags it ON it.tag_id = t.id JOIN images i ON i.id = it.image_id WHERE $vis GROUP BY t.id ORDER BY n DESC, t.name LIMIT 300", $vp);
?>
<?= breadcrumbs([['Home', url('')], ['Tags', null]]) ?>
<h1 class="page-title">Tags</h1>
<?= $tags ? tags_cloud_html($tags) : '<div class="empty"><div class="empty-ico">🏷️</div><p>No tags yet.</p></div>' ?>
