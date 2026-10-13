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
     * OCR the flyer, then write Click-to-WhatsApp campaign fields from those facts.
     *
     * @param  array<int, string>  $warnings
     * @return array<string, string>
     */
    protected function extractCopyFromImage(string $originalBinary, string $originalName, array &$warnings): array
    {
        $attempts = [
            ['side' => 1600, 'quality' => 90],
            ['side' => 2200, 'quality' => 92],
        ];

        $lastError = null;
        $bestFacts = null;

        foreach ($attempts as $i => $attempt) {
            try {
                $vision = $this->imageProcessor->downscaleForVision(
                    $originalBinary,
                    $attempt['side'],
                    $attempt['quality']
                );

                $facts = $this->readFlyerFacts($vision['base64'], $vision['mime'], $i > 0);
                if ($this->factsAreEmpty($facts)) {
                    $lastError = 'Flyer facts were empty or only a print size.';
                    Log::info('CREATIVE_VISION_EMPTY_FACTS', ['attempt' => $i + 1]);
                    continue;
                }

                $bestFacts = $facts;
                $written = $this->writeCampaignFromFacts($facts);
                $normalized = $this->normalize($written);

                if ($this->looksWeak($normalized) || $this->missesFlyerFacts($normalized, $facts)) {
                    Log::info('CREATIVE_VISION_WEAK_RETRY', [
                        'attempt' => $i + 1,
                        'headline' => $normalized['headline'] ?? '',
                        'service' => $normalized['service_name'] ?? '',
                    ]);
                    $composed = $this->normalize($this->composeFromFacts($facts));
                    if (! $this->looksWeak($composed) && ! $this->missesFlyerFacts($composed, $facts)) {
                        return $composed;
                    }
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

        if (is_array($bestFacts) && ! $this->factsAreEmpty($bestFacts)) {
            $composed = $this->normalize($this->composeFromFacts($bestFacts));
            if (! $this->looksWeak($composed)) {
                return $composed;
            }
        }

        $warnings[] = 'AI could not read enough text from the creative — edit the copy below before publishing.';
        Log::warning('CREATIVE_VISION_GAVE_UP', ['error' => $lastError, 'file' => $originalName]);

        return $this->fallbackAttractiveCopy($originalName);
    }

    /**
     * @return array<string, mixed>
     */
    protected function readFlyerFacts(string $base64, string $mime, bool $strictRetry): array
    {
        $extra = $strictRetry
            ? ' Previous read missed the offer. Transcribe the largest headline, any salary or price, the place, and the brand.'
            : '';

        $raw = $this->gemini->analyzeImage(
            $base64,
            $mime,
            $this->ocrPrompt($extra),
            'You read advertising flyers for a media buyer. Transcribe what is printed. Correct obvious misspellings when the intended word is clear (Uskilled means Unskilled). Never invent phone numbers, prices, or benefits. Ignore aspect ratios, pixel sizes, and filenames. Return only JSON.',
            3072
        );

        return $this->parseJson($raw);
    }

    protected function ocrPrompt(string $extra): string
    {
        return <<<PROMPT
Read this advertising flyer. Extract only what is visibly printed.{$extra}

Ignore print sizes and placement labels (4:5, 4x5, 4x5 inch, 9:16, 1:1, 1080x1350, Stories, Feed). Those are not the offer.

Return JSON:
{
  "brand": "company or school name printed on the flyer, else empty",
  "headline_on_flyer": "the main headline as a person would say it",
  "offer": "what is being sold, taught, or recruited for, in plain language",
  "location": "city or country if printed, else empty",
  "money": "salary, fee, or price exactly as shown, else empty",
  "audience": "who this flyer is for",
  "benefits": ["short benefits actually printed"],
  "cta": "the call to action printed on the flyer",
  "language": "en or fr",
  "readable_text": "the important visible lines with spelling corrected"
}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    protected function writeCampaignFromFacts(array $facts): array
    {
        $factsJson = json_encode($this->publicFacts($facts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($factsJson === false) {
            return $this->composeFromFacts($facts);
        }

        $raw = $this->gemini->generateJson(
            $this->copyPrompt($factsJson),
            'You are a senior Meta ads copywriter for Click-to-WhatsApp campaigns. You write specific, attractive ads from flyer facts. You never use a filename, a print size, or a generic "interested in...?" line as the ad. Return only JSON.',
            2048
        );

        $parsed = $this->parseJson($raw);
        if ($this->looksWeak($this->normalize($parsed))) {
            return $this->composeFromFacts($facts);
        }

        return $parsed;
    }

    protected function copyPrompt(string $factsJson): string
    {
        return <<<PROMPT
Write a Click-to-WhatsApp campaign a media buyer would actually publish. Use ONLY these flyer facts:

{$factsJson}

Return JSON with exactly these keys:
{
  "service_name": "clear offer name a customer understands",
  "target_audience": "who should tap the ad",
  "main_benefit": "strongest concrete benefit, including money or place when the facts have them",
  "campaign_name": "internal name: brand + offer, max 55 characters",
  "adset_name": "short ad set name, max 60 characters",
  "ad_name": "short ad name, max 60 characters",
  "primary_text": "2 or 3 sentences. Sentence 1 is the hook: the offer, the place, and the money if present. Sentence 2 is one real benefit. Sentence 3 invites them to message on WhatsApp. 140 to 320 characters. No hashtags. No emojis.",
  "headline": "benefit headline, 40 characters or fewer",
  "description": "30 characters or fewer",
  "whatsapp_prefill_message": "first person, names the offer, 110 characters or fewer"
}

BAD primary_text: "Interested in Work In Dubai Uskilled Work 4X5 Inch? Message us on WhatsApp for personalized guidance — spots and promotions move fast."
GOOD primary_text: "Special Dubai job offer: unskilled work from USD 850, with food and accommodation. Message Parrot Canada on WhatsApp and apply now."

Rules:
- Sound like a paid ad, not a file name and not a form.
- Never mention aspect ratios, inches, pixels, Stories, or Feed.
- Do not invent prices, visas, phone numbers, or benefits that are not in the facts.
- If money or a place is in the facts, both primary_text and headline must include them.
- Write in the flyer language (en or fr).
- Count headline and description characters and stay inside the caps.
PROMPT;
    }

    /**
     * Local campaign when the copy model drifts, still grounded in the flyer.
     *
     * @param  array<string, mixed>  $facts
     * @return array<string, string>
     */
    protected function composeFromFacts(array $facts): array
    {
        $brand = $this->cleanFact((string) ($facts['brand'] ?? ''));
        $offer = $this->cleanFact((string) ($facts['offer'] ?? ''));
        $flyerHeadline = $this->cleanFact((string) ($facts['headline_on_flyer'] ?? ''));
        $location = $this->cleanFact((string) ($facts['location'] ?? ''));
        $money = $this->cleanFact((string) ($facts['money'] ?? ''));
        $audience = $this->cleanFact((string) ($facts['audience'] ?? ''));
        $cta = $this->cleanFact((string) ($facts['cta'] ?? ''));

        $benefits = $facts['benefits'] ?? [];
        if (is_string($benefits)) {
            $benefits = preg_split('/[,;|]/', $benefits) ?: [];
        }
        $benefits = array_values(array_filter(array_map(
            fn ($item) => $this->cleanFact((string) $item),
            is_array($benefits) ? $benefits : []
        )));

        $service = $offer !== '' ? $offer : $flyerHeadline;
        if ($service === '') {
            $service = $brand !== '' ? $brand : 'This offer';
        }
        if ($location !== '' && ! str_contains(mb_strtolower($service), mb_strtolower($location))) {
            $service = trim($service.' in '.$location);
        }

        $hook = $flyerHeadline !== '' ? $flyerHeadline : $service;
        if ($money !== '' && ! str_contains(mb_strtolower($hook), mb_strtolower($money))) {
            $hook = rtrim($hook, '.').' — '.$money;
        }

        $benefitLine = $benefits !== []
            ? ' Includes '.implode(', ', array_slice($benefits, 0, 3)).'.'
            : '';
        $who = $brand !== '' ? $brand : 'us';
        $primary = trim($hook.'.'.$benefitLine.' Message '.$who.' on WhatsApp to get started.');

        $headlineSource = trim(($location !== '' ? $location.' ' : '').($money !== '' ? $money : ($offer !== '' ? $offer : $service)));
        $headline = $this->clip($headlineSource !== '' ? $headlineSource : $service, 40);
        $description = $this->clip($cta !== '' ? $cta : 'Message us on WhatsApp', 30);
        $campaign = $this->clip(trim(($brand !== '' ? $brand.' ' : '').$service).' — WhatsApp', 60);

        return [
            'service_name' => $service,
            'target_audience' => $audience !== '' ? $audience : 'People interested in '.$service,
            'main_benefit' => $money !== '' ? $money : ($benefits[0] ?? $hook),
            'campaign_name' => $campaign,
            'adset_name' => $this->clip($campaign.' — Ad set', 70),
            'ad_name' => $this->clip($campaign.' — Ad', 70),
            'primary_text' => $this->clip($primary, 420),
            'headline' => $headline,
            'description' => $description,
            'whatsapp_prefill_message' => $this->clip("Hi! I'd like details about {$service}.", 120),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function normalize(array $data): array
    {
        $service = $this->cleanFact((string) ($data['service_name'] ?? ''));
        $audience = $this->cleanFact((string) ($data['target_audience'] ?? ''));
        $benefit = $this->cleanFact((string) ($data['main_benefit'] ?? ''));
        $ocr = $this->cleanFact((string) ($data['ocr_summary'] ?? ''));

        if ($service === '') {
            $service = $this->firstUsefulPhrase($ocr, $benefit, 'Your offer');
        }
        if ($audience === '') {
            $audience = 'People interested in '.$service;
        }
        if ($benefit === '') {
            $benefit = $ocr !== '' ? $this->clip($ocr, 120) : 'Get details on WhatsApp';
        }

        $campaign = $this->cleanFact((string) ($data['campaign_name'] ?? ''));
        if ($campaign === '') {
            $campaign = $this->clip($service.' — WhatsApp', 60);
        }

        $adset = $this->cleanFact((string) ($data['adset_name'] ?? ''));
        if ($adset === '') {
            $adset = $this->clip($campaign.' — Ad set', 70);
        }

        $ad = $this->cleanFact((string) ($data['ad_name'] ?? ''));
        if ($ad === '') {
            $ad = $this->clip($campaign.' — Ad', 70);
        }

        $primary = $this->cleanFact((string) ($data['primary_text'] ?? ''));
        if ($primary === '' || $this->looksLikeGenericDiscover($primary) || $this->looksLikeFilenameFallback($primary)) {
            $primary = $this->composePrimary($service, $benefit, $audience);
        }

        $headline = $this->cleanFact((string) ($data['headline'] ?? ''));
        if ($headline === '') {
            $headline = $this->clip($this->attractiveHeadline($service, $benefit), 40);
        }

        $description = $this->cleanFact((string) ($data['description'] ?? ''));
        if ($description === '' || strcasecmp($description, 'Chat on WhatsApp') === 0) {
            $description = $this->clip($benefit !== '' ? $benefit : 'Message us today', 30);
        }

        $prefill = $this->cleanFact((string) ($data['whatsapp_prefill_message'] ?? ''));
        if ($prefill === '' || $this->looksLikeGenericDiscover($prefill)) {
            $prefill = "Hi! I'm interested in {$service}. Can you share details?";
        }

        return [
            'campaign_name' => $this->clip($campaign, 80),
            'adset_name' => $this->clip($adset, 80),
            'ad_name' => $this->clip($ad, 80),
            'service_name' => $this->clip($service, 120),
            'target_audience' => $this->clip($audience, 160),
            'main_benefit' => $this->clip($benefit, 160),
            'primary_text' => $this->clip($primary, 500),
            'headline' => $this->clip($headline, 40),
            'description' => $this->clip($description, 30),
            'whatsapp_prefill_message' => $this->clip($prefill, 200),
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    protected function factsAreEmpty(array $facts): bool
    {
        $facts = $this->publicFacts($facts);
        $blob = trim(implode(' ', array_filter([
            $facts['brand'],
            $facts['offer'],
            $facts['headline_on_flyer'],
            $facts['money'],
            $facts['location'],
            $facts['readable_text'],
        ])));

        return mb_strlen($this->scrubPrintSizes($blob)) < 8;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    protected function publicFacts(array $facts): array
    {
        $benefits = $facts['benefits'] ?? [];
        if (! is_array($benefits)) {
            $benefits = [$benefits];
        }

        $language = strtolower(substr(trim((string) ($facts['language'] ?? 'en')), 0, 2));

        return [
            'brand' => $this->cleanFact((string) ($facts['brand'] ?? '')),
            'headline_on_flyer' => $this->cleanFact((string) ($facts['headline_on_flyer'] ?? '')),
            'offer' => $this->cleanFact((string) ($facts['offer'] ?? '')),
            'location' => $this->cleanFact((string) ($facts['location'] ?? '')),
            'money' => $this->cleanFact((string) ($facts['money'] ?? '')),
            'audience' => $this->cleanFact((string) ($facts['audience'] ?? '')),
            'benefits' => array_values(array_filter(array_map(
                fn ($item) => $this->cleanFact((string) $item),
                $benefits
            ))),
            'cta' => $this->cleanFact((string) ($facts['cta'] ?? '')),
            'language' => $language !== '' ? $language : 'en',
            'readable_text' => $this->scrubPrintSizes($this->fixCommonOcr((string) ($facts['readable_text'] ?? ''))),
        ];
    }

    /**
     * @param  array<string, string>  $normalized
     * @param  array<string, mixed>  $facts
     */
    protected function missesFlyerFacts(array $normalized, array $facts): bool
    {
        $facts = $this->publicFacts($facts);
        $hay = mb_strtolower(implode(' ', [
            $normalized['primary_text'] ?? '',
            $normalized['headline'] ?? '',
            $normalized['service_name'] ?? '',
        ]));

        $needles = [];
        if (preg_match_all('/\d{2,}/', (string) $facts['money'], $money)) {
            $needles = array_merge($needles, $money[0]);
        }
        if (preg_match_all('/[A-Za-z]{4,}/', (string) $facts['location'], $places)) {
            foreach ($places[0] as $place) {
                $needles[] = mb_strtolower($place);
            }
        }

        $needles = array_values(array_unique(array_filter($needles)));
        if ($needles === []) {
            return false;
        }

        foreach ($needles as $needle) {
            if (! str_contains($hay, $needle)) {
                return true;
            }
        }

        return false;
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

        $primary = (string) ($normalized['primary_text'] ?? '');
        if ($this->looksLikeGenericDiscover($primary) || $this->looksLikeFilenameFallback($primary)) {
            return true;
        }

        foreach (['service_name', 'headline', 'primary_text', 'campaign_name'] as $key) {
            if ($this->containsPrintSize((string) ($normalized[$key] ?? ''))) {
                return true;
            }
        }

        if (mb_strlen($primary) < 40) {
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

        $exact = [
            '4x5', '4:5', '4×5', '1x1', '1:1', '9x16', '9:16', '1.91:1', '1.91',
            'feed', 'stories', 'story', 'square', 'landscape', 'portrait',
            'offer', 'image', 'creative', 'upload', 'untitled',
        ];
        $phrases = [
            'whatsapp offer', 'chat on whatsapp', 'discover 4x5', 'discover 4:5',
        ];

        if (in_array($v, $exact, true)) {
            return true;
        }

        foreach ($phrases as $j) {
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

        if ($this->containsPrintSize($v) && mb_strlen($this->scrubPrintSizes($v)) < 8) {
            return true;
        }

        return false;
    }

    protected function containsPrintSize(string $value): bool
    {
        return (bool) preg_match('/\b\d+\s*[x×:]\s*\d+\s*(inch|inches|px|cm)?\b/i', $value)
            || (bool) preg_match('/\b(4\s*[:x×]\s*5|9\s*[:x×]\s*16|1\s*[:x×]\s*1|1\.91\s*:\s*1)\b/i', $value);
    }

    protected function scrubPrintSizes(string $value): string
    {
        $value = preg_replace('/\b\d+\s*[x×:]\s*\d+\s*(inch|inches|px|cm)?\b/i', ' ', $value) ?? $value;
        $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;
        $value = trim($value, " \t-—|,;");

        return $this->fixCommonOcr($value);
    }

    protected function fixCommonOcr(string $value): string
    {
        return preg_replace('/\buskilled\b/i', 'Unskilled', $value) ?? $value;
    }

    protected function cleanFact(string $value): string
    {
        $value = $this->scrubPrintSizes(trim($value));
        if ($value === '' || $this->isJunkLabel($value)) {
            return '';
        }

        return $value;
    }

    protected function clip(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $cut = mb_substr($value, 0, $max);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > (int) ($max * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t,.;:-—");
    }

    protected function looksLikeFilenameFallback(string $text): bool
    {
        return (bool) preg_match('/personalized guidance/i', $text)
            || (bool) preg_match('/^interested in .+\?\s*message us on whatsapp/i', trim($text));
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
        $service = ($base !== '' && ! $this->containsPrintSize($base) && ! $this->isJunkLabel($this->scrubPrintSizes($base)))
            ? $this->scrubPrintSizes($base)
            : 'Your offer';

        return $this->normalize([
            'service_name' => $service,
            'campaign_name' => $service.' — WhatsApp',
            'target_audience' => 'People exploring this offer',
            'main_benefit' => 'Get the details on WhatsApp',
            'primary_text' => "Want the details on {$service}? Message us on WhatsApp and we will walk you through the next step.",
            'headline' => $this->clip($service, 40),
            'description' => 'Chat for details',
            'whatsapp_prefill_message' => "Hi! I'd like details about {$service}.",
        ]);
    }
}
