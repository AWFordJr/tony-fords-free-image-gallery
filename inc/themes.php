<?php
declare(strict_types=1);

/** Theme registry. Colours live in assets/css/themes.css; effects in assets/js/effects.js */
function themes(): array
{
    return [
        'classic-dark' => ['name' => 'Classic Dark', 'desc' => 'Clean modern dark. Easy on the eyes.', 'effect' => 'none', 'tone' => 'dark'],
        'classic-light' => ['name' => 'Classic Light', 'desc' => 'Clean, bright and simple.', 'effect' => 'none', 'tone' => 'light'],
        'toxic-rain' => ['name' => 'Toxic Rain', 'desc' => 'Acid green glow with glowing rain and splashes.', 'effect' => 'rain', 'tone' => 'dark'],
        'matrix' => ['name' => 'Matrix', 'desc' => 'Falling green code on pure black.', 'effect' => 'matrix', 'tone' => 'dark'],
        'dragons-lair' => ['name' => "Dragon's Lair", 'desc' => 'Obsidian, gold and dragon-fire sparks.', 'effect' => 'lair', 'tone' => 'dark'],
        'midnight-starfield' => ['name' => 'Midnight Starfield', 'desc' => 'Twinkling stars drifting slowly by.', 'effect' => 'stars', 'tone' => 'dark'],
        'ember-glow' => ['name' => 'Ember Glow', 'desc' => 'Warm embers rising from the fire.', 'effect' => 'embers', 'tone' => 'dark'],
        'frozen-snowfall' => ['name' => 'Frozen Snowfall', 'desc' => 'Soft snow falling over icy blue.', 'effect' => 'snow', 'tone' => 'dark'],
        'neon-synthwave' => ['name' => 'Neon Synthwave', 'desc' => 'Retro grid horizon in pink, purple and cyan.', 'effect' => 'synthwave', 'tone' => 'dark'],
        'ocean-depths' => ['name' => 'Ocean Depths', 'desc' => 'Deep teal water with rising bubbles.', 'effect' => 'bubbles', 'tone' => 'dark'],
    ];
}

function valid_theme(?string $t): bool
{
    return is_string($t) && isset(themes()[$t]);
}

function site_theme(): string
{
    $t = (string)setting('theme', 'classic-dark');
    return valid_theme($t) ? $t : 'classic-dark';
}

/** Theme for the current visitor. */
function active_theme(): string
{
    if (setting('allow_visitor_themes', '1') === '1') {
        $u = current_user();
        if ($u && valid_theme($u['theme'] ?? null)) return $u['theme'];
        $c = $_COOKIE['tfg_theme'] ?? null;
        if (valid_theme($c)) return $c;
    }
    return site_theme();
}

/** Theme picker with live previews. $name = form field name, $current = selected id. */
function theme_picker(string $current, string $mode = 'form', string $name = 'theme'): string
{
    $h = '<div class="theme-grid" data-theme-picker="' . e($mode) . '">';
    foreach (themes() as $id => $t) {
        $sel = $id === $current;
        $h .= '<label class="theme-card' . ($sel ? ' selected' : '') . '" data-theme-id="' . e($id) . '">';
        if ($mode === 'form') {
            $h .= '<input type="radio" name="' . e($name) . '" value="' . e($id) . '"' . ($sel ? ' checked' : '') . '>';
        } else {
            $h .= '<input type="radio" name="vt" value="' . e($id) . '"' . ($sel ? ' checked' : '') . '>';
        }
        $h .= '<span class="theme-preview" data-theme="' . e($id) . '" data-fx="' . e($t['effect']) . '">'
            . '<span class="tp-bar"><i></i><i></i><i></i></span>'
            . '<span class="tp-fx"></span>'
            . '<span class="tp-grid"><b></b><b></b><b></b><b></b><b></b><b></b></span>'
            . '<span class="tp-btn"></span></span>'
            . '<span class="theme-meta"><strong>' . e($t['name']) . '</strong><small>' . e($t['desc']) . '</small></span>'
            . '</label>';
    }
    return $h . '</div>';
}
