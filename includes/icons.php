<?php
// Small line icons, drawn in SVG, for buttons and links: icon('lock').
//
// Each one is a few lines of drawing instructions, so no image files or icon
// library are needed. They take the colour of the text around them
// (stroke="currentColor") and are hidden from screen readers, because the words
// beside them already say what they mean.

const ICONS = [
    'lock'      => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/>',
    'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    'user-plus' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/>',
    'log-in'    => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
    'send'      => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
    'check'     => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
    'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    'filter'    => '<path d="M4 5h16M7 12h10M10 19h4"/>',
    'pin'       => '<path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
    'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'tag'       => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
    'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'book'      => '<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4zM20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z"/>',
    'laptop'    => '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 19h20"/>',
    'chair'     => '<path d="M7 3h10v8H7zM5 11h14v3H5zM7 14v7M17 14v7"/>',
    'shirt'     => '<path d="M8 3l-5 3 2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0z"/>',
    'box'       => '<path d="M3 7l9-4 9 4v10l-9 4-9-4z M3 7l9 4 9-4 M12 11v10"/>',
    'chevron'   => '<path d="M9 6l6 6-6 6"/>',
    'heart'     => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
    'star'      => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
    'message'   => '<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-8l-5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/>',
    'bell'      => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z M10 21a2 2 0 0 0 4 0"/>',
    'eye-off'   => '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/>',
    'code'      => '<path d="M8 7l-5 5 5 5M16 7l5 5-5 5M14 4l-4 16"/>',
];

/**
 * The icon for a listing category, chosen from its name. Admins can add and
 * rename categories, so an unknown name gets the general "box" icon.
 */
function category_icon(string $categoryName, int $size = 18): string
{
    $name = strtolower($categoryName);

    foreach (['book' => 'book', 'text' => 'book', 'electr' => 'laptop', 'furni' => 'chair', 'cloth' => 'shirt'] as $word => $iconName) {
        if (str_contains($name, $word)) {
            return icon($iconName, $size);
        }
    }

    return icon('box', $size);
}

function icon(string $name, int $size = 18): string
{
    return '<svg class="icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true"'
        . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        . (ICONS[$name] ?? '') . '</svg>';
}
