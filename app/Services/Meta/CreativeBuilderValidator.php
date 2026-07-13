<?php

namespace App\Services\Meta;

use App\Models\PlatformMetaConnection;
use App\Services\Tenant\TenantConnectionResolver;
use Illuminate\Http\UploadedFile;

class CreativeBuilderValidator
{
    public const WHATSAPP_CTAS = ['WHATSAPP_MESSAGE', 'SEND_MESSAGE'];

    public function __construct(
        protected ClickToWhatsAppCreativeBuilder $whatsAppBuilder
    ) {}

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

        $whatsappErrors = $this->validateWhatsAppDestination($data);
        $errors = array_merge($errors, $whatsappErrors);

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
     * @param  array<string, mixed>  $data
     * @return array<int, array{field: string, message: string, fix: string}>
     */
    protected function validateWhatsAppDestination(array $data): array
    {
        $chatUrl = trim((string) ($data['whatsapp_chat_url'] ?? ''));
        $phone = trim((string) ($data['whatsapp_phone_number'] ?? ''));

        if ($chatUrl === '' && $phone === '') {
            $fallback = app(TenantConnectionResolver::class)->whatsappPhoneNumber();
            if ($fallback) {
                return [];
            }

            return [$this->err(
                'whatsapp_chat_url',
                'WhatsApp chat destination is required.',
                'Paste any wa.me link (e.g. https://wa.me/14389009784?text=Hello) or enter a phone number.'
            )];
        }

        $target = $chatUrl !== '' ? $chatUrl : $phone;

        if (preg_match('#^https?://#i', $target)) {
            if (! $this->whatsAppBuilder->isValidWhatsAppUrl($target)) {
                return [$this->err(
                    'whatsapp_chat_url',
                    'Not a valid WhatsApp link.',
                    'Use https://wa.me/<number> or https://api.whatsapp.com/send?phone=...'
                )];
            }

            return [];
        }

        $digits = preg_replace('/\D+/', '', $target) ?? '';
        if (strlen($digits) < 8) {
            return [$this->err(
                'whatsapp_phone_number',
                'Phone number looks too short.',
                'Enter full international number or paste a complete wa.me link.'
            )];
        }

        return [];
    }

    /**
     * @return array{field: string, message: string, fix: string}
     */
    protected function err(string $field, string $message, string $fix): array
    {
        return compact('field', 'message', 'fix');
    }
}
