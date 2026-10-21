<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The twelve colours of the system, in the Trello 2018 family. Each one has a primary shade
 * (board backgrounds, labels, the dashboard) and a more intense secondary one (the top bar).
 * The CSS classes and variables are generated from here (see style()), so this is the only list.
 */
final class Palette
{
    /**
     * Computed in OKLCH: every colour has the same perceived lightness, hues spread around the wheel,
     * moderate chroma. White text: 3.6:1 on the primary shade (WCAG AA for large text and graphics),
     * 5.5:1 on the secondary one (AA for any text), which is why small labels use the secondary.
     */
    public const COLORS = [
        'blue' => ['#4687d8', '#2168bb'],
        'sky' => ['#0e91be', '#027196'],
        'teal' => ['#1b9690', '#057671'],
        'green' => ['#3d9756', '#077937'],
        'lime' => ['#699236', '#4b7402'],
        'yellow' => ['#a9811b', '#866405'],
        'orange' => ['#c86f25', '#a35301'],
        'red' => ['#dc5e55', '#bd3a34'],
        'pink' => ['#cc6490', '#ad4373'],
        'purple' => ['#a373c5', '#8553a7'],
        'indigo' => ['#797ce1', '#5d5cc4'],
        'grey' => ['#7e8792', '#616a75'],
    ];

    public const DEFAULT = 'sky';

    public static function has(?string $key): bool
    {
        return isset(self::COLORS[$key ?? '']);
    }

    public static function primary(?string $key): string
    {
        return (self::COLORS[$key ?? ''] ?? self::COLORS[self::DEFAULT])[0];
    }

    public static function secondary(?string $key): string
    {
        return (self::COLORS[$key ?? ''] ?? self::COLORS[self::DEFAULT])[1];
    }

    public static function names(): array
    {
        return [
            'blue' => __('Blue'), 'sky' => __('Sky'), 'teal' => __('Teal'), 'green' => __('Green'),
            'lime' => __('Lime'), 'yellow' => __('Yellow'), 'orange' => __('Orange'), 'red' => __('Red'),
            'pink' => __('Pink'), 'purple' => __('Purple'), 'indigo' => __('Indigo'), 'grey' => __('Grey'),
        ];
    }

    /** CSS for <head>: the user's colour as --k-accent / --k-header, and one .label-{key} class per colour (its secondary shade as --lc). */
    public static function style(string $accent): string
    {
        $css = ':root{--k-accent:' . self::primary($accent) . ';--k-header:' . self::secondary($accent) . '}';
        foreach (self::COLORS as $key => [, $secondary]) {
            $css .= ".label-{$key}{--lc:{$secondary}}";
        }

        return $css;
    }
}
