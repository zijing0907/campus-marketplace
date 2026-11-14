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
];

function icon(string $name, int $size = 18): string
{
    return '<svg class="icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true"'
        . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        . (ICONS[$name] ?? '') . '</svg>';
}
