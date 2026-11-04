<?php
// POST /api/like  id=IMAGE_ID  -> toggles like
require_post();
$id = (int)($_POST['id'] ?? 0);
$img = $id > 0 ? fetch_image($id) : null;
if (!$img || $img['status'] !== 'published') json_out(['ok' => false, 'error' => 'Picture not found.'], 404);
$r = toggle_like($id);
json_out($r, $r['ok'] ? 200 : 403);
