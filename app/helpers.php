<?php

if (!function_exists('icon')) {
    /**
     * Generate an icon span element from spritesheet
     *
     * @param string|array $icon Icon name (string) or coordinates [col, row]
     * @param string $class Additional CSS classes
     * @return string HTML span element
     */
    function icon(string|array $icon, string $class = ''): string
    {
        $style = icon_style($icon);
        $classes = trim("icon $class");
        $title = is_string($icon) ? $icon : '';
        return "<span class=\"$classes\" style=\"$style\" title=\"$title\"></span>";
    }
}

if (!function_exists('icon_style')) {
    /**
     * Get the background style for an icon (includes image URL and position)
     *
     * @param string|array $icon Icon name (string) or coordinates [col, row]
     * @return string CSS style string
     */
    function icon_style(string|array $icon): string
    {
        // Direct coordinates [row, col] - 0-indexed
        if (is_array($icon)) {
            [$row, $col] = $icon;
        } else {
            // Named icon from config (uses [col, row] format, already 0-indexed)
            $icons = config('icons', []);
            if (!isset($icons[$icon])) {
                return '';
            }
            [$col, $row] = $icons[$icon];
        }

        // x = horizontal (col), y = vertical (row)
        $x = -($col * 32);
        $y = -($row * 32);

        return "background-image: url('/images/icons.png'); background-position: {$x}px {$y}px;";
    }
}
