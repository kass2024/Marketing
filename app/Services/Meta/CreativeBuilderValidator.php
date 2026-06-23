<?php

namespace App\Services\Meta;

use App\Models\PlatformMetaConnection;
use Illuminate\Http\UploadedFile;

class CreativeBuilderValidator
{
    public const WHATSAPP_CTAS = ['WHATSAPP_MESSAGE', 'SEND_MESSAGE'];

    public const MAX_PRIMARY_TEXT = 2200;
    public const MAX_HEADLINE = 255;
    public const MAX_DESCRIPTION = 255;
    public const MIN_IMAGE_WIDTH = 600;
    public const MIN_IMAGE_HEIGHT = 600;
    public const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    /**
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, errors: array<int, array{field: string, message: string, fix: string}>, warnings: array<int, string>}
     */
    public function validate(array $data, ?UploadedFile $image = null, ?UploadedFile $video = null): array
    {
        $errors = [];
        $warnings = [];

        if (empty(trim((string) ($data['primary_text'] ?? $data['body'] ?? '')))) {
            $errors[] = $this->err('primary_text', 'Primary text is required.', 'Write or auto-generate primary ad copy.');
        }

        if (empty(trim((string) ($data['headline'] ?? '')))) {
            $errors[] = $this->err('headline', 'Headline is required.', 'Write or auto-generate a headline.');
        }

        $phone = (string) ($data['whatsapp_phone_number'] ?? PlatformMetaConnection::query()->latest()->value('whatsapp_phone_number') ?? '');
        if ($phone === '') {
            $errors[] = $this->err('whatsapp_phone_number', 'WhatsApp phone number is not connected.', 'Connect WhatsApp in Meta Connection or enter your business number.');
        }

        $cta = strtoupper((string) ($data['call_to_action'] ?? 'WHATSAPP_MESSAGE'));
        if (! in_array($cta, self::WHATSAPP_CTAS, true)) {
            $errors[] = $this->err('call_to_action', 'CTA must be WhatsApp-compatible.', 'Use WhatsApp Message or Send Message as the CTA.');
        }

        $placements = $data['placements'] ?? [];
        if ($placements === [] || (is_array($placements) && count(array_filter($placements)) === 0)) {
            $errors[] = $this->err('placements', 'Select at least one ad placement.', 'Choose Facebook and/or Instagram placements before publishing.');
        }

        if (! $image && ! $video && empty($data['image_path']) && empty($data['existing_image_url'])) {
            $errors[] = $this->err('media', 'Image or video is required.', 'Upload creative media that meets Meta ad specs.');
        }

        if ($image) {
            $mediaErrors = $this->validateImage($image);
            $errors = array_merge($errors, $mediaErrors);
        }

        $primaryLen = strlen((string) ($data['primary_text'] ?? $data['body'] ?? ''));
        if ($primaryLen > self::MAX_PRIMARY_TEXT) {
            $errors[] = $this->err('primary_text', 'Primary text exceeds Meta limit.', 'Shorten to '.self::MAX_PRIMARY_TEXT.' characters.');
        }

        if (strlen((string) ($data['headline'] ?? '')) > self::MAX_HEADLINE) {
            $errors[] = $this->err('headline', 'Headline exceeds Meta limit.', 'Shorten to '.self::MAX_HEADLINE.' characters.');
        }

        if (empty($data['service_name'])) {
            $warnings[] = 'Service/product name helps generate stronger copy.';
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @return array<int, array{field: string, message: string, fix: string}>
     */
    protected function validateImage(UploadedFile $image): array
    {
        $errors = [];

        if ($image->getSize() > self::MAX_IMAGE_BYTES) {
            $errors[] = $this->err('media', 'Image file is too large.', 'Use an image under 4 MB.');
        }

        $mime = $image->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $errors[] = $this->err('media', 'Unsupported image format.', 'Use JPG, PNG, or WebP.');
        }

        $size = @getimagesize($image->getPathname());
        if (is_array($size)) {
            [$w, $h] = $size;
            if ($w < self::MIN_IMAGE_WIDTH || $h < self::MIN_IMAGE_HEIGHT) {
                $errors[] = $this->err('media', "Image is too small ({$w}×{$h}).", 'Minimum recommended size is 600×600 px.');
            }
            $ratio = $w / max($h, 1);
            if ($ratio < 0.5 || $ratio > 2.0) {
                $errors[] = $this->err('media', 'Image aspect ratio may be rejected by Meta.', 'Use square (1:1) or 4:5 for feed ads.');
            }
        }

        return $errors;
    }

    /**
     * @return array{field: string, message: string, fix: string}
     */
    protected function err(string $field, string $message, string $fix): array
    {
        return compact('field', 'message', 'fix');
    }
}
