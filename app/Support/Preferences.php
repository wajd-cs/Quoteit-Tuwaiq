<?php

namespace App\Support;

use App\Models\User;

/**
 * Accessibility / display settings. Saved on the user (database) or, for guests, in the browser.
 */
class Preferences
{
    public const DEFAULTS = [
        'contrast' => 'normal',     // normal | high
        'colors' => 'default',      // default | protanopia | deuteranopia | tritanopia | mono
        'text_scale' => 1,          // 0.85 - 1.75
        'speech_rate' => 1,         // 0.75 | 1 | 1.25 | 1.5 | 2
        'reduce_motion' => false,
        'underline_links' => false,
        'readable_spacing' => false,
        'tour_done' => false,       // the first-visit tour was shown
    ];

    public const OPTIONS = [
        'contrast' => ['normal', 'high'],
        'colors' => ['default', 'protanopia', 'deuteranopia', 'tritanopia', 'mono'],
        'text_scale' => [0.85, 1, 1.15, 1.3, 1.5, 1.75],
        'speech_rate' => [0.75, 1, 1.25, 1.5, 2],
    ];

    public static function for(?User $user): array
    {
        return array_merge(self::DEFAULTS, array_intersect_key($user?->preferences ?? [], self::DEFAULTS));
    }

    /** Keep only known keys with allowed values. */
    public static function clean(array $input): array
    {
        $clean = [];
        foreach (self::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if (is_bool($default)) {
                $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array((string) $value, array_map('strval', self::OPTIONS[$key]), true)) {
                $clean[$key] = is_numeric($value) ? (float) $value : $value;
            }
        }

        return $clean;
    }
}
