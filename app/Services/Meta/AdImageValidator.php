<?php

namespace App\Services\Meta;

use Illuminate\Http\UploadedFile;

class AdImageValidator
{
    public const MAX_BYTES = 4 * 1024 * 1024;

    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, width: int|null, height: int|null, format: string|null, format_label: string|null}
     */
    public function validateUpload(UploadedFile $file, ?string $expectedFormat = null): array
    {
        $errors = [];
        $warnings = [];

        if ($file->getSize() > self::MAX_BYTES) {
            $errors[] = 'Image must be under 4 MB.';
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            $errors[] = 'Use JPG, PNG, or WebP only.';
        }

        $size = @getimagesize($file->getPathname());
        if (! is_array($size)) {
            return [
                'valid' => false,
                'errors' => ['Could not read image dimensions.'],
                'warnings' => [],
                'width' => null,
                'height' => null,
                'format' => null,
                'format_label' => null,
            ];
        }

        [$width, $height] = $size;
        $detected = AdFormatRegistry::detectFormat($width, $height);

        if (! $detected['valid']) {
            $errors[] = $detected['message'] ?? 'Unsupported aspect ratio.';
        }

        if ($expectedFormat) {
            $fmt = AdFormatRegistry::get($expectedFormat);
            if ($fmt && $detected['format'] && $detected['format'] !== $expectedFormat) {
                $warnings[] = "Image looks like {$detected['format']} but you selected {$expectedFormat}. We'll use the detected format.";
            }
            if ($fmt && ($width < $fmt['min_width'] || $height < $fmt['min_height'])) {
                $errors[] = "Minimum for {$fmt['label']}: {$fmt['min_width']}×{$fmt['min_height']} px (yours: {$width}×{$height}).";
            }
        }

        $formatKey = $detected['format'] ?? $expectedFormat;
        $formatLabel = $formatKey ? (AdFormatRegistry::get($formatKey)['label'] ?? $formatKey) : null;

        if ($formatKey && $detected['valid']) {
            $fmt = AdFormatRegistry::get($formatKey);
            if ($fmt && ($width < $fmt['width'] * 0.7 || $height < $fmt['height'] * 0.7)) {
                $warnings[] = "Recommended size: {$fmt['width']}×{$fmt['height']} px for best quality.";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'width' => $width,
            'height' => $height,
            'format' => $formatKey,
            'format_label' => $formatLabel,
        ];
    }

    /**
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, width: int|null, height: int|null, format: string|null}
     */
    public function validatePath(string $absolutePath, ?string $expectedFormat = null): array
    {
        if (! is_file($absolutePath)) {
            return [
                'valid' => false,
                'errors' => ['Image file not found.'],
                'warnings' => [],
                'width' => null,
                'height' => null,
                'format' => null,
            ];
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';
        $uploaded = new UploadedFile($absolutePath, basename($absolutePath), $mime, null, true);

        return $this->validateUpload($uploaded, $expectedFormat);
    }
}
