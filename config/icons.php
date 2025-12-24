<?php

/**
 * Spritesheet icon definitions for UI elements
 * Format: 'icon-name' => [column, row] (0-indexed, 32x32 pixels each)
 *
 * Usage: {!! icon('heart') !!} or style="{{ icon_style('heart') }}"
 */

return [
    // UI - Status
    'skull'           => [8, 0],
    'skull-crossbones'=> [0, 0],
    'target'          => [1, 0],
    'speech-bubble'   => [2, 0],
    'search'          => [3, 0],
    'sparkles'        => [4, 0],
    'stars'           => [5, 0],
    'heart'           => [6, 0],
    'lightning'       => [7, 0],
    'water-drop'      => [9, 0],

    // UI - Arrows
    'arrow-down-red'  => [0, 1],
    'arrow-up-green'  => [2, 1],
    'arrow-diagonal'  => [3, 1],
    'arrow-curve'     => [4, 1],
    'heart-broken'    => [5, 1],
    'refresh'         => [7, 1],

    // UI - Actions
    'swords-crossed'  => [0, 2],
    'arrows-crossed'  => [1, 2],
    'hand-point'      => [2, 2],
    'fire'            => [3, 2],
    'clover'          => [5, 2],
    'moon'            => [6, 2],
    'sun'             => [9, 2],

    // UI - Misc
    'campfire'        => [1, 3],
    'pyramid'         => [2, 3],
    'flower'          => [4, 3],
    'scroll'          => [5, 3],
    'diamond'         => [7, 3],

    // Resources
    'gold'            => [0, 13],
    'gold-stack'      => [1, 13],
    'coin'            => [2, 13],
    'gem-red'         => [3, 13],
    'gem-green'       => [4, 13],
    'gem-blue'        => [5, 13],
    'gem-purple'      => [6, 13],
    'chest'           => [7, 13],

    // Balls/orbs (row 14)
    'orb-red'         => [0, 14],
    'orb-green'       => [1, 14],
    'orb-blue'        => [2, 14],
    'orb-purple'      => [3, 14],

    // Food
    'apple'           => [0, 11],
    'banana'          => [1, 11],
    'grapes'          => [3, 11],
    'carrot'          => [4, 11],
    'meat'            => [0, 12],
    'bread'           => [8, 2],

    // Books
    'book-red'        => [0, 10],
    'book-green'      => [1, 10],
    'book-blue'       => [2, 10],
    'book-purple'     => [3, 10],
    'book-open'       => [4, 10],
    'letter'          => [5, 10],

    // Nature (row 17)
    'grass'           => [6, 17],
    'tree'            => [7, 17],
    'rock'            => [8, 17],
    'mountain'        => [9, 17],

    // Shapes (row 17)
    'star-burst'      => [1, 17],
    'crescent'        => [3, 17],
    'snowflake'       => [5, 17],
];
