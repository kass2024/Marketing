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

        // Meta creative (cropped/resized) stored for publish — vision uses the original so OCR stays sharp.
        $stored = $this->imageProcessor->normalizeUpload(
            $file,
            (string) ($validation['format'] ?? $preferredFormat ?? AdFormatRegistry::defaultKey())
        );

        $originalBinary = file_get_contents($file->getPathname());
        if ($originalBinary === false) {
            throw new Exception('Could not read uploaded creative.');
        }

        $warnings = array_values(array_filter(array_merge(
            $validation['warnings'] ?? [],
            ["Resized to {$stored['width']}×{$stored['height']} for Meta."]
        )));

        $normalized = $this->extractCopyFromImage($originalBinary, $file->getClientOriginalName(), $warnings);

        unset($stored['binary']);

        return array_merge($normalized, [
            'image_path' => $stored['path'],
            'image_url' => $stored['url'],
            'image_format' => $stored['format'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'warnings' => $warnings,
        ]);
    }

    /**
     * OCR + write CTWA copy from the original creative bytes.
     *
     * @param  array<int, string>  $warnings
     * @return array<string, string>
     */
    protected function extractCopyFromImage(string $originalBinary, string $originalName, array &$warnings): array
    {
        $attempts = [
            ['side' => 1536, 'quality' => 88, 'tokens' => 1200],
            ['side' => 2048, 'quality' => 90, 'tokens' => 1600],
        ];

        $lastError = null;

        foreach ($attempts as $i => $attempt) {
            try {
                $vision = $this->imageProcessor->downscaleForVision(
                    $originalBinary,
                    $attempt['side'],
                    $attempt['quality']
                );

                $raw = $this->gemini->analyzeImage(
                    $vision['base64'],
                    $vision['mime'],
                    $this->buildPrompt($i > 0),
                    $this->systemPrompt(),
                    $attempt['tokens']
                );

                $parsed = $this->parseJson($raw);
                $normalized = $this->normalize($parsed);

                if ($this->looksWeak($normalized)) {
                    Log::info('CREATIVE_VISION_WEAK_RETRY', [
                        'attempt' => $i + 1,
                        'headline' => $normalized['headline'] ?? '',
                        'service' => $normalized['service_name'] ?? '',
                    ]);
                    $lastError = 'Weak extraction (generic or format-like copy).';
                    continue;
                }

                return $normalized;
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                Log::warning('CREATIVE_VISION_FAILED', [
                    'attempt' => $i + 1,
                    'error' => $lastError,
                ]);
            }
        }

        $warnings[] = 'AI could not read enough text from the creative — edit the copy below before publishing.';
        Log::warning('CREATIVE_VISION_GAVE_UP', ['error' => $lastError, 'file' => $originalName]);

        return $this->fallbackAttractiveCopy($originalName);
    }

    protected function systemPrompt(): string
    {
        return 'You are an expert Meta ads copywriter for Click-to-WhatsApp campaigns. '
            .'First OCR every readable word on the creative (headlines, offers, programs, brand, dates, CTAs). '
            .'Then write persuasive, customer-facing ad copy grounded ONLY in that text. '
            .'Never invent phone numbers. Never use aspect ratios, placements, or filenames (like 4:5, 4x5, 9:16) as the offer. '
            .'Return ONLY valid JSON.';
    }

    protected function buildPrompt(bool $strictRetry = false): string
    {
        $extra = $strictRetry
            ? "\nThis is a retry — previous output was too generic. Quote concrete phrases from the image (programs, visa, fees, university/brand, promotion)."
            : '';

        return <<<PROMPT
Read this advertising flyer/creative carefully (OCR all visible text).{$extra}

Return JSON with exactly these keys:
{
  "ocr_summary": "1-2 sentences summarizing what the flyer promotes, using real words from the image",
  "service_name": "clear product/service name from the flyer (e.g. Study short courses in Canada)",
  "target_audience": "who should click (e.g. students seeking short courses on a visitor visa)",
  "main_benefit": "strongest benefit or offer from the flyer (e.g. application fees waived)",
  "campaign_name": "short internal campaign name",
  "adset_name": "short ad set name",
  "ad_name": "short ad name",
  "primary_text": "2-4 punchy sentences for Facebook/Instagram primary text. Lead with the hook from the flyer, mention the offer, invite to WhatsApp. Max ~220 chars.",
  "headline": "benefit headline ≤40 characters",
  "description": "link description ≤30 characters",
  "whatsapp_prefill_message": "natural first WhatsApp message a prospect would send ≤120 chars"
}

Rules:
- Ground every field in text or offers visible on the image.
- Prefer English unless the flyer is clearly French.
- Make copy attractive: urgency, benefit, clarity — not dry.
- Do NOT output placement labels (4x5, 1:1, Stories) as service/headline.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function normalize(array $data): array
    {
        $service = trim((string) ($data['service_name'] ?? ''));
        $audience = trim((string) ($data['target_audience'] ?? ''));
        $benefit = trim((string) ($data['main_benefit'] ?? ''));
        $ocr = trim((string) ($data['ocr_summary'] ?? ''));

        if ($service === '' || $this->isJunkLabel($service)) {
            $service = $this->firstUsefulPhrase($ocr, $benefit, 'WhatsApp offer');
        }
        if ($audience === '' || $this->isJunkLabel($audience)) {
            $audience = 'People interested in '.$service;
        }
        if ($benefit === '' || $this->isJunkLabel($benefit)) {
            $benefit = $ocr !== '' ? Str::limit($ocr, 120, '') : 'Get details on WhatsApp';
        }

        $campaign = trim((string) ($data['campaign_name'] ?? ''));
        if ($campaign === '' || $this->isJunkLabel($campaign)) {
            $campaign = Str::limit($service.' — WhatsApp', 60, '');
        }

        $adset = trim((string) ($data['adset_name'] ?? ''));
        if ($adset === '' || $this->isJunkLabel($adset)) {
            $adset = $campaign.' — Ad Set';
        }

        $ad = trim((string) ($data['ad_name'] ?? ''));
        if ($ad === '' || $this->isJunkLabel($ad)) {
            $ad = $campaign.' — Ad';
        }

        $primary = trim((string) ($data['primary_text'] ?? ''));
        if ($primary === '' || $this->isJunkLabel($primary) || $this->looksLikeGenericDiscover($primary)) {
            $primary = $this->composePrimary($service, $benefit, $audience);
        }

        $headline = trim((string) ($data['headline'] ?? ''));
        if ($headline === '' || $this->isJunkLabel($headline)) {
            $headline = Str::limit($this->attractiveHeadline($service, $benefit), 40, '');
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || $this->isJunkLabel($description) || strcasecmp($description, 'Chat on WhatsApp') === 0) {
            $description = Str::limit($benefit !== '' ? $benefit : 'Message us today', 30, '');
        }

        $prefill = trim((string) ($data['whatsapp_prefill_message'] ?? ''));
        if ($prefill === '' || $this->isJunkLabel($prefill) || $this->looksLikeGenericDiscover($prefill)) {
            $prefill = "Hi! I'm interested in {$service}. Can you share details?";
        }

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
     * @param  array<string, string>  $normalized
     */
    protected function looksWeak(array $normalized): bool
    {
        foreach (['service_name', 'headline', 'primary_text', 'campaign_name'] as $key) {
            if ($this->isJunkLabel((string) ($normalized[$key] ?? ''))) {
                return true;
            }
        }

        if ($this->looksLikeGenericDiscover((string) ($normalized['primary_text'] ?? ''))) {
            return true;
        }

        $primary = (string) ($normalized['primary_text'] ?? '');
        if (strlen($primary) < 40) {
            return true;
        }

        return false;
    }

    protected function isJunkLabel(string $value): bool
    {
        $v = strtolower(trim($value));
        if ($v === '' || strlen($v) < 3) {
            return true;
        }

        $junk = [
            '4x5', '4:5', '4×5', '1x1', '1:1', '9x16', '9:16', '1.91:1', '1.91',
            'feed', 'stories', 'story', 'square', 'landscape', 'portrait',
            'whatsapp offer', 'offer', 'image', 'creative', 'upload', 'untitled',
            'chat on whatsapp', 'discover 4x5', 'discover 4:5',
        ];

        foreach ($junk as $j) {
            if ($v === $j || str_starts_with($v, $j.' ') || str_ends_with($v, ' '.$j)) {
                return true;
            }
        }

        // Pure aspect / dimension tokens
        if (preg_match('/^\d+\s*[x×:]\s*\d+$/i', $v)) {
            return true;
        }

        if (preg_match('/^(feed|story|square)?[_\s-]*(4x5|1x1|9x16|191)/i', $v)) {
            return true;
        }

        return false;
    }

    protected function looksLikeGenericDiscover(string $text): bool
    {
        return (bool) preg_match('/^discover\s+.+\.\s*tap below to chat on whatsapp\.?$/i', trim($text));
    }

    protected function composePrimary(string $service, string $benefit, string $audience): string
    {
        $hook = $benefit !== '' ? rtrim($benefit, '.').'.' : "Learn more about {$service}.";
        $who = $audience !== '' ? " Ideal for {$audience}." : '';

        return Str::limit("{$hook}{$who} Tap below to chat on WhatsApp — we'll guide you step by step.", 220, '');
    }

    protected function attractiveHeadline(string $service, string $benefit): string
    {
        if ($benefit !== '' && ! $this->isJunkLabel($benefit)) {
            return Str::limit($benefit, 40, '');
        }

        return Str::limit($service, 40, '');
    }

    protected function firstUsefulPhrase(string ...$candidates): string
    {
        foreach ($candidates as $c) {
            $c = trim($c);
            if ($c !== '' && ! $this->isJunkLabel($c)) {
                return $c;
            }
        }

        return 'WhatsApp offer';
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
     * Last-resort copy when vision fails — never use format-like filenames as the offer.
     *
     * @return array<string, string>
     */
    protected function fallbackAttractiveCopy(string $filename): array
    {
        $base = Str::title(str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
        $service = ($base !== '' && ! $this->isJunkLabel($base))
            ? $base
            : 'This limited offer';

        return $this->normalize([
            'service_name' => $service,
            'campaign_name' => $service.' — WhatsApp',
            'target_audience' => 'People exploring this offer',
            'main_benefit' => 'Get details and next steps instantly',
            'primary_text' => "Interested in {$service}? Message us on WhatsApp for personalized guidance — spots and promotions move fast.",
            'headline' => Str::limit($service, 40, ''),
            'description' => 'Chat for details',
            'whatsapp_prefill_message' => "Hi! I'd like details about {$service}.",
        ]);
    }
}
