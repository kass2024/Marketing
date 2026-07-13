<?php

namespace App\Services\Meta;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Normalize uploads to Meta placement sizes and prepare lean payloads for Gemini.
 */
class AdImageProcessor
{
    /**
     * Center-crop + resize binary image data to a Meta format (cover fit).
     *
     * @param  array<string, mixed>  $format
     */
    public function resizeToFormat(string $imageBinary, array $format): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $imageBinary;
        }

        $src = @imagecreatefromstring($imageBinary);
        if (! $src) {
            return $imageBinary;
        }

        $targetW = (int) $format['width'];
        $targetH = (int) $format['height'];
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $targetRatio = $targetW / max($targetH, 1);
        $srcRatio = $srcW / max($srcH, 1);

        if ($srcRatio > $targetRatio) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
            $srcX = (int) round(($srcW - $cropW) / 2);
            $srcY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $srcX = 0;
            $srcY = (int) round(($srcH - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($targetW, $targetH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, max(1, $cropW), max(1, $cropH));

        ob_start();
        imagejpeg($dst, null, 88);
        $out = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $out !== false && $out !== '' ? $out : $imageBinary;
    }

    /**
     * Store an uploaded image resized to the chosen (or closest) Meta format.
     *
     * @return array{
     *   path: string,
     *   url: string,
     *   width: int,
     *   height: int,
     *   format: string,
     *   mime: string,
     *   resized: bool,
     *   original_width: int,
     *   original_height: int
     * }
     */
    public function normalizeUpload(UploadedFile $file, ?string $preferredFormat = null): array
    {
        $binary = file_get_contents($file->getPathname());
        if ($binary === false) {
            throw new \RuntimeException('Could not read uploaded creative.');
        }

        $size = @getimagesizefromstring($binary);
        $origW = is_array($size) ? (int) $size[0] : 0;
        $origH = is_array($size) ? (int) $size[1] : 0;

        $formatKey = $preferredFormat && AdFormatRegistry::get($preferredFormat)
            ? $preferredFormat
            : AdFormatRegistry::closestFormat(max(1, $origW), max(1, $origH));

        $format = AdFormatRegistry::get($formatKey) ?? AdFormatRegistry::get(AdFormatRegistry::defaultKey());
        if (! $format) {
            throw new \RuntimeException('Invalid Meta image format.');
        }

        $outBinary = $this->resizeToFormat($binary, $format);

        $filename = 'upload-'.$formatKey.'-'.Str::lower(Str::random(10)).'.jpg';
        $path = 'marketing-wizard/'.$filename;
        Storage::disk('public')->put($path, $outBinary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'width' => (int) $format['width'],
            'height' => (int) $format['height'],
            'format' => $formatKey,
            'mime' => 'image/jpeg',
            'resized' => true,
            'original_width' => $origW,
            'original_height' => $origH,
            'binary' => $outBinary,
        ];
    }

    /**
     * Downscale for faster Gemini vision (keeps aspect, JPEG).
     *
     * @return array{base64: string, mime: string}
     */
    public function downscaleForVision(string $imageBinary, int $maxSide = 768): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [
                'base64' => base64_encode($imageBinary),
                'mime' => 'image/jpeg',
            ];
        }

        $src = @imagecreatefromstring($imageBinary);
        if (! $src) {
            return [
                'base64' => base64_encode($imageBinary),
                'mime' => 'image/jpeg',
            ];
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $scale = min(1.0, $maxSide / max($srcW, $srcH, 1));
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        if ($scale < 1.0) {
            $dst = imagecreatetruecolor($dstW, $dstH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        imagejpeg($src, null, 72);
        $out = ob_get_clean();
        imagedestroy($src);

        return [
            'base64' => base64_encode($out !== false && $out !== '' ? $out : $imageBinary),
            'mime' => 'image/jpeg',
        ];
    }
}
