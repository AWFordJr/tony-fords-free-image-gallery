<?php
// POST /api/upload  (one file per request; the browser uploader sends them one by one with progress)
require_post();
$me = require_uploader();
$f = $_FILES['file'] ?? null;
if (!is_array($f) || is_array($f['name'] ?? null)) json_out(['ok' => false, 'error' => 'No file received (or it exceeded the server limit).'], 400);
$meta = [
    'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150),
    'description' => trim((string)($_POST['description'] ?? '')),
    'category_id' => (int)($_POST['category_id'] ?? 0),
    'tags' => parse_tags((string)($_POST['tags'] ?? '')),
];
$res = process_upload($f, $me, $meta);
json_out($res, $res['ok'] ? 200 : 422);
