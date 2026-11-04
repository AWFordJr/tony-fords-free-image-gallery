<?php
// POST /api/theme  theme=ID  -> remember the visitor's theme
require_post();
if (setting('allow_visitor_themes', '1') !== '1') json_out(['ok' => false, 'error' => 'Theme switching is turned off.'], 403);
$t = (string)($_POST['theme'] ?? '');
if (!valid_theme($t)) json_out(['ok' => false, 'error' => 'Unknown theme.'], 422);
setcookie('tfg_theme', $t, theme_cookie_opts());
if ($u = current_user()) q('UPDATE users SET theme = ? WHERE id = ?', [$t, $u['id']]);
json_out(['ok' => true, 'theme' => $t, 'tone' => themes()[$t]['tone']]);
