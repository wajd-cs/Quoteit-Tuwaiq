<?php

namespace App\Support;

/**
 * Look of a quote or story chosen when writing it.
 * Only fixed presets, so every colour stays easy to read.
 */
class QuoteStyle
{
    public const FONTS = [
        'almarai' => 'Almarai',
        'cairo' => 'Cairo',
        'alexandria' => 'Alexandria',
        'rubik' => 'Rubik',
        'readex' => 'Readex Pro',
    ];

    public const SIZES = ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large'];

    public const COLORS = ['default', 'teal', 'orange', 'rose', 'lilac', 'sky', 'gold', 'mint'];

    public const ALIGNS = ['start' => 'Start of line', 'center' => 'Centre'];

    public const DEFAULT = ['font' => 'almarai', 'size' => 'm', 'color' => 'default', 'align' => 'start'];

    /** Keep only known values; returns null when everything is the default. */
    public static function clean(?array $input): ?array
    {
        $input ??= [];
        $style = [
            'font' => array_key_exists($input['font'] ?? '', self::FONTS) ? $input['font'] : 'almarai',
            'size' => array_key_exists($input['size'] ?? '', self::SIZES) ? $input['size'] : 'm',
            'color' => in_array($input['color'] ?? '', self::COLORS, true) ? $input['color'] : 'default',
            'align' => array_key_exists($input['align'] ?? '', self::ALIGNS) ? $input['align'] : 'start',
        ];

        return $style === self::DEFAULT ? null : $style;
    }

    /** CSS classes for the quote text. */
    public static function classes(?array $style): string
    {
        $style = array_merge(self::DEFAULT, $style ?? []);

        return "qs-font-{$style['font']} qs-size-{$style['size']} qs-color-{$style['color']} qs-align-{$style['align']}";
    }
}
