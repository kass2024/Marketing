<?php

namespace App\Services\Meta;

class AdFormatRegistry
{
    /**
     * Meta-compatible ad image formats.
     *
     * Primary CTWA trio (recommended):
     * - 4:5 Feed (1080×1350)
     * - 1:1 Square (1080×1080)
     * - 9:16 Stories / Reels / WhatsApp Status (1080×1920)
     *
     * Also supported:
     * - 1.91:1 Landscape (1200×628) for right column / link surfaces
     *
     * @see https://www.facebook.com/business/help/682655495435254
     *
     * @return array<string, array{
     *     label: string,
     *     short: string,
     *     ratio: float,
     *     width: int,
     *     height: int,
     *     min_width: int,
     *     min_height: int,
     *     tolerance: float,
     *     placements: string,
     *     dalle_size: string,
     *     primary: bool
     * }>
     */
    public static function formats(): array
    {
        return [
            'feed_4x5' => [
                'label' => '4:5 — Feed',
                'short' => '4×5',
                'ratio' => 0.8,
                'width' => 1080,
                'height' => 1350,
                'min_width' => 600,
                'min_height' => 750,
                'tolerance' => 0.06,
                'placements' => 'Facebook Feed, Instagram Feed',
                'dalle_size' => '1024x1792',
                'primary' => true,
            ],
            'square_1x1' => [
                'label' => '1:1 — Square',
                'short' => '1×1',
                'ratio' => 1.0,
                'width' => 1080,
                'height' => 1080,
                'min_width' => 600,
                'min_height' => 600,
                'tolerance' => 0.05,
                'placements' => 'Feed, Marketplace, multi-placement',
                'dalle_size' => '1024x1024',
                'primary' => true,
            ],
            'story_9x16' => [
                'label' => '9:16 — Stories & Reels',
                'short' => '9×16',
                'ratio' => 0.5625,
                'width' => 1080,
                'height' => 1920,
                'min_width' => 600,
                'min_height' => 1067,
                'tolerance' => 0.06,
                'placements' => 'Stories, Reels, WhatsApp Status',
                'dalle_size' => '1024x1792',
                'primary' => true,
            ],
            'landscape_191' => [
                'label' => '1.91:1 — Landscape',
                'short' => '1.91:1',
                'ratio' => 1.91,
                'width' => 1200,
                'height' => 628,
                'min_width' => 600,
                'min_height' => 314,
                'tolerance' => 0.08,
                'placements' => 'Facebook right column, link previews',
                'dalle_size' => '1792x1024',
                'primary' => false,
            ],
            // Legacy alias (old incorrect tall 1:1.91 key) → treat as landscape detection fallback
            'portrait_191' => [
                'label' => '1.91:1 — Landscape',
                'short' => '1.91:1',
                'ratio' => 1.91,
                'width' => 1200,
                'height' => 628,
                'min_width' => 600,
                'min_height' => 314,
                'tolerance' => 0.08,
                'placements' => 'Facebook right column, link previews',
                'dalle_size' => '1792x1024',
                'primary' => false,
            ],
        ];
    }

    /**
     * Meta's three primary mobile placement sizes for CTWA.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function primaryFormats(): array
    {
        return array_filter(self::formats(), fn ($f) => ($f['primary'] ?? false) === true);
    }

    public static function get(string $key): ?array
    {
        $formats = self::formats();
        if (isset($formats[$key])) {
            return $formats[$key];
        }
        if ($key === 'portrait_191') {
            return $formats['landscape_191'] ?? null;
        }

        return null;
    }

    public static function defaultKey(): string
    {
        return 'feed_4x5';
    }

    /**
     * @return array{valid: bool, format: string|null, width: int, height: int, message: string|null}
     */
    public static function detectFormat(int $width, int $height): array
    {
        $ratio = $width / max($height, 1);
        $best = null;
        $bestDiff = PHP_FLOAT_MAX;

        // Prefer primary formats when diffs are close
        foreach (self::formats() as $key => $fmt) {
            if ($key === 'portrait_191') {
                continue; // alias of landscape_191
            }
            $diff = abs($ratio - $fmt['ratio']);
            if ($diff <= $fmt['tolerance'] && $diff < $bestDiff) {
                $best = $key;
                $bestDiff = $diff;
            }
        }

        return [
            'valid' => $best !== null,
            'format' => $best,
            'width' => $width,
            'height' => $height,
            'message' => $best
                ? null
                : 'Image ratio does not match Meta sizes (4:5 Feed, 1:1 Square, 9:16 Stories, or 1.91:1 Landscape).',
        ];
    }

    /**
     * Nearest Meta format by aspect ratio (always returns a key — used when auto-resizing).
     */
    public static function closestFormat(int $width, int $height): string
    {
        $detected = self::detectFormat($width, $height);
        if (! empty($detected['format'])) {
            return (string) $detected['format'];
        }

        $ratio = $width / max($height, 1);
        $best = self::defaultKey();
        $bestDiff = PHP_FLOAT_MAX;

        foreach (self::formats() as $key => $fmt) {
            if ($key === 'portrait_191') {
                continue;
            }
            $diff = abs($ratio - $fmt['ratio']);
            // Prefer primary CTWA sizes when diffs are nearly equal
            if (($fmt['primary'] ?? false) === true) {
                $diff -= 0.001;
            }
            if ($diff < $bestDiff) {
                $best = $key;
                $bestDiff = $diff;
            }
        }

        return $best;
    }
}
