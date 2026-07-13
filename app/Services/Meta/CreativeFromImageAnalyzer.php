<?php

namespace App\Services\Meta;

use App\Services\GeminiAiService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreativeFromImageAnalyzer
{
    public function __construct(
        protected GeminiAiService $gemini,
        protected AdImageValidator $imageValidator
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
     *   height: int|null
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

        $formatKey = (string) ($validation['format'] ?? $preferredFormat ?? AdFormatRegistry::defaultKey());
        $path = $file->store('marketing-wizard', 'public');
        $absolute = Storage::disk('public')->path($path);
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $binary = file_get_contents($absolute);
        if ($binary === false) {
            throw new Exception('Could not read uploaded creative.');
        }

        $base64 = base64_encode($binary);
        $system = 'You write high-converting Meta Click-to-WhatsApp ads for Facebook/Instagram. '
            .'Return ONLY valid JSON. No markdown. Keep headline ≤40 chars, primary_text ≤220 chars, description ≤30 chars. '
            .'CTA is always WhatsApp message. Tone: clear, benefit-led, professional.';

        $prompt = <<<'PROMPT'
Analyze this ad creative image and produce Click-to-WhatsApp campaign fields.

Return JSON with exactly these keys:
{
  "campaign_name": "short campaign name",
  "adset_name": "short ad set name",
  "ad_name": "short ad name",
  "service_name": "product or service offered",
  "target_audience": "who this ad targets",
  "main_benefit": "main benefit in one short phrase",
  "primary_text": "Facebook primary text ending with a WhatsApp invite",
  "headline": "short headline",
  "description": "short description under headline",
  "whatsapp_prefill_message": "message the user pre-sends on WhatsApp"
}

Infer language from the creative (English or French is fine). Do not invent fake phone numbers.
PROMPT;

        try {
            $raw = $this->gemini->analyzeImage($base64, $mime, $prompt, $system, 2048);
            $parsed = $this->parseJson($raw);
            $normalized = $this->normalize($parsed);
        } catch (Exception $e) {
            Log::warning('CREATIVE_VISION_FAILED', ['error' => $e->getMessage()]);
            $normalized = $this->fallbackFromFilename($file->getClientOriginalName());
        }

        return array_merge($normalized, [
            'image_path' => $path,
            'image_url' => Storage::disk('public')->url($path),
            'image_format' => $formatKey,
            'width' => $validation['width'] ?? null,
            'height' => $validation['height'] ?? null,
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
