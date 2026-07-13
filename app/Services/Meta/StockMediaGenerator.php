<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\File;

class StockMediaGenerator
{
    /**
     * Ensure stock ad PNG files exist (generated once via GD).
     */
    public static function ensureAssets(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $dir = public_path('img/stock-ads');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $templates = [
            'jobs-canada' => ['title' => 'GETTING A JOB', 'subtitle' => 'IN CANADA', 'bg' => [196, 30, 58], 'accent' => [255, 255, 255]],
            'study-abroad' => ['title' => 'STUDY ABROAD', 'subtitle' => 'Your future starts here', 'bg' => [37, 99, 235], 'accent' => [255, 255, 255]],
            'consultation' => ['title' => 'FREE', 'subtitle' => 'CONSULTATION', 'bg' => [66, 116, 49], 'accent' => [255, 255, 255]],
            'real-estate' => ['title' => 'FIND YOUR', 'subtitle' => 'DREAM HOME', 'bg' => [15, 118, 110], 'accent' => [255, 255, 255]],
            'elearning' => ['title' => 'ONLINE', 'subtitle' => 'COURSES', 'bg' => [124, 58, 237], 'accent' => [255, 255, 255]],
            'cleaning' => ['title' => 'PROFESSIONAL', 'subtitle' => 'CLEANING', 'bg' => [14, 165, 233], 'accent' => [255, 255, 255]],
            'event' => ['title' => 'JOIN OUR', 'subtitle' => 'EVENT', 'bg' => [234, 88, 12], 'accent' => [255, 255, 255]],
            'product-sale' => ['title' => 'FLASH', 'subtitle' => 'SALE', 'bg' => [220, 38, 38], 'accent' => [255, 255, 255]],
        ];

        foreach ($templates as $filename => $spec) {
            $path = "{$dir}/{$filename}.png";
            if (File::exists($path)) {
                continue;
            }
            self::renderCard($path, $spec['title'], $spec['subtitle'], $spec['bg'], $spec['accent']);
        }
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $bg
     * @param  array{0: int, 1: int, 2: int}  $accent
     */
    protected static function renderCard(
        string $path,
        string $title,
        string $subtitle,
        array $bg,
        array $accent
    ): void {
        $w = 1200;
        $h = 1500;
        $img = imagecreatetruecolor($w, $h);
        $background = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
        $textColor = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);
        $muted = imagecolorallocate($img, 255, 255, 255);

        imagefilledrectangle($img, 0, 0, $w, $h, $background);

        $font = 5;
        $titleX = (int) (($w - imagefontwidth($font) * strlen($title)) / 2);
        imagestring($img, $font, max(40, $titleX), 600, $title, $textColor);

        $subX = (int) (($w - imagefontwidth($font) * strlen($subtitle)) / 2);
        imagestring($img, $font, max(40, $subX), 680, $subtitle, $muted);

        imagestring($img, 3, 40, $h - 80, 'Tap Send message on WhatsApp', $muted);

        imagepng($img, $path);
        imagedestroy($img);
    }
}
