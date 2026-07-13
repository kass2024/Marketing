<?php

namespace App\Services\Meta;

use App\Services\GeminiAiService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreativeFromImageAnalyzer
{
    public function __construct(
        protected GeminiAiService $gemini,
        protected AdImageValidator $imageValidator,
        protected AdImageProcessor $imageProcessor
    ) {}

    /**
     * Analyze an uploaded creative and return Click-to-WhatsApp ad fields.
     *
     * @return array{
     *   campaign_name: string,
     *   adset_name: string,
     *   ad_name: string,
     *   service_name: string,
     *   target_audience: string,
     *   main_benefit: string,
     *   primary_text: string,
     *   headline: string,
     *   description: string,
     *   whatsapp_prefill_message: string,
     *   image_path: string,
     *   image_url: string,
     *   image_format: string,
     *   width: int|null,
     *   height: int|null,
     *   warnings: array<int, string>
     * }
     */
    public function analyzeUpload(UploadedFile $file, ?string $preferredFormat = null): array
    {
        if (! $this->gemini->isConfigured()) {
            throw new Exception($this->gemini->configurationHint() ?: 'Gemini is not configured.');
        }

        $validation = $this->imageValidator->validateUpload($file, $preferredFormat);
        if (! ($validation['valid'] ?? false)) {
            throw new Exception(implode(' ', $validation['errors'] ?? ['Invalid image.']));
        }

        $stored = $this->imageProcessor->normalizeUpload(
            $file,
            (string) ($validation['format'] ?? $preferredFormat ?? AdFormatRegistry::defaultKey())
        );

        $vision = $this->imageProcessor->downscaleForVision((string) $stored['binary'], 768);

        $system = 'Write Meta Click-to-WhatsApp ad copy. Return ONLY compact JSON.';
        $prompt = <<<'PROMPT'
From this ad image, return JSON keys only:
campaign_name, adset_name, ad_name, service_name, target_audience, main_benefit,
primary_text (≤180 chars, end with WhatsApp invite), headline (≤40 chars),
description (≤30 chars), whatsapp_prefill_message (≤120 chars).
Infer language from the creative. No fake phone numbers. No markdown.
PROMPT;

        try {
            $raw = $this->gemini->analyzeImage(
                $vision['base64'],
                $vision['mime'],
                $prompt,
                $system,
                640
            );
            $parsed = $this->parseJson($raw);
            $normalized = $this->normalize($parsed);
        } catch (Exception $e) {
            Log::warning('CREATIVE_VISION_FAILED', ['error' => $e->getMessage()]);
            $normalized = $this->fallbackFromFilename($file->getClientOriginalName());
        }

        unset($stored['binary']);

        return array_merge($normalized, [
            'image_path' => $stored['path'],
            'image_url' => $stored['url'],
            'image_format' => $stored['format'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'warnings' => array_values(array_filter(array_merge(
                $validation['warnings'] ?? [],
                ! empty($stored['resized'])
                    ? ["Resized to {$stored['width']}×{$stored['height']} for Meta."]
                    : []
            ))),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function normalize(array $data): array
    {
        $service = trim((string) ($data['service_name'] ?? 'WhatsApp offer'));
        $audience = trim((string) ($data['target_audience'] ?? 'Interested customers'));
        $benefit = trim((string) ($data['main_benefit'] ?? 'Get help on WhatsApp'));

        $campaign = trim((string) ($data['campaign_name'] ?? ''));
        if ($campaign === '') {
            $campaign = Str::limit($service.' — WhatsApp', 60, '');
        }

        $adset = trim((string) ($data['adset_name'] ?? ''));
        if ($adset === '') {
            $adset = $campaign.' — Ad Set';
        }

        $ad = trim((string) ($data['ad_name'] ?? ''));
        if ($ad === '') {
            $ad = $campaign.' — Ad';
        }

        $primary = trim((string) ($data['primary_text'] ?? ''));
        if ($primary === '') {
            $primary = "{$benefit}. Perfect for {$audience}. Tap below to chat on WhatsApp.";
        }

        $headline = trim((string) ($data['headline'] ?? Str::limit($service, 40, '')));
        $description = trim((string) ($data['description'] ?? 'Message us on WhatsApp'));
        $prefill = trim((string) ($data['whatsapp_prefill_message'] ?? "Hi! I'm interested in {$service}."));

        return [
            'campaign_name' => Str::limit($campaign, 80, ''),
            'adset_name' => Str::limit($adset, 80, ''),
            'ad_name' => Str::limit($ad, 80, ''),
            'service_name' => Str::limit($service, 120, ''),
            'target_audience' => Str::limit($audience, 160, ''),
            'main_benefit' => Str::limit($benefit, 160, ''),
            'primary_text' => Str::limit($primary, 500, ''),
            'headline' => Str::limit($headline, 40, ''),
            'description' => Str::limit($description, 30, ''),
            'whatsapp_prefill_message' => Str::limit($prefill, 200, ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseJson(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new Exception('Gemini returned invalid JSON for creative analysis.');
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    protected function fallbackFromFilename(string $filename): array
    {
        $base = Str::title(str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
        $service = $base !== '' ? $base : 'WhatsApp offer';

        return $this->normalize([
            'service_name' => $service,
            'campaign_name' => $service.' — WhatsApp',
            'target_audience' => 'People interested in '.$service,
            'main_benefit' => 'Learn more and chat instantly',
            'primary_text' => "Discover {$service}. Tap below to chat on WhatsApp.",
            'headline' => Str::limit($service, 40, ''),
            'description' => 'Chat on WhatsApp',
            'whatsapp_prefill_message' => "Hi! I'm interested in {$service}.",
        ]);
    }
}
