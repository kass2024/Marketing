<?php

namespace App\Services\Meta;

use Illuminate\Http\UploadedFile;

class AdImageValidator
{
    /** Soft cap before we auto-resize/compress for Meta. */
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif'];

    /**
     * Lightweight checks only — any readable raster is accepted; aspect is auto-fixed later.
     *
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, width: int|null, height: int|null, format: string|null, format_label: string|null}
     */
    public function validateUpload(UploadedFile $file, ?string $expectedFormat = null): array
    {
        $errors = [];
        $warnings = [];

        if ($file->getSize() > self::MAX_BYTES) {
            $errors[] = 'Image must be under 10 MB (we resize to Meta sizes after upload).';
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if ($mime !== '' && ! in_array($mime, self::ALLOWED_MIMES, true) && ! str_starts_with($mime, 'image/')) {
            $errors[] = 'Upload an image file (JPG, PNG, WebP, or similar).';
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
        $formatKey = ($expectedFormat && AdFormatRegistry::get($expectedFormat))
            ? $expectedFormat
            : (string) ($detected['format'] ?? AdFormatRegistry::closestFormat($width, $height));

        if (! ($detected['valid'] ?? false) || ($detected['format'] ?? null) !== $formatKey) {
            $fmt = AdFormatRegistry::get($formatKey);
            $label = $fmt['label'] ?? $formatKey;
            $warnings[] = "We'll auto-crop/resize to Meta {$label} ({$fmt['width']}×{$fmt['height']}).";
        }

        $formatLabel = $formatKey ? (AdFormatRegistry::get($formatKey)['label'] ?? $formatKey) : null;

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
