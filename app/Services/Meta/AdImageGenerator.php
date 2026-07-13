<?php

namespace App\Services\Meta;

use App\Services\GeminiAiService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdImageGenerator
{
    public function __construct(
        protected GeminiAiService $gemini,
        protected AdImageProcessor $imageProcessor
    ) {}

    /**
     * Generate an ad image and resize to the target Meta format.
     *
     * @param  array<string, mixed>  $input
     * @return array{path: string, url: string, width: int, height: int, format: string, prompt: string, provider: string}
     */
    public function generate(array $input): array
    {
        $formatKey = (string) ($input['image_format'] ?? AdFormatRegistry::defaultKey());
        $format = AdFormatRegistry::get($formatKey) ?? AdFormatRegistry::get(AdFormatRegistry::defaultKey());
        if (! $format) {
            throw new Exception('Invalid image format selected.');
        }

        $prompt = $this->buildPrompt($input, $format);
        $provider = config('services.ads.ai_provider', 'gemini');

        Log::info('AD_IMAGE_GENERATE', ['format' => $formatKey, 'provider' => $provider]);

        $raw = $provider === 'openai'
            ? $this->generateWithOpenAi($prompt, $format)
            : $this->generateWithGemini($prompt);

        $resized = $this->imageProcessor->resizeToFormat($raw, $format);
        $filename = 'ai-'.$formatKey.'-'.uniqid().'.jpg';
        $path = 'marketing-wizard/'.$filename;
        Storage::disk('public')->put($path, $resized);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'width' => $format['width'],
            'height' => $format['height'],
            'format' => $formatKey,
            'prompt' => $prompt,
            'provider' => $provider,
        ];
    }

    protected function generateWithGemini(string $prompt): string
    {
        return $this->gemini->generateImageBinary($prompt);
    }

    /**
     * @param  array<string, mixed>  $format
     */
    protected function generateWithOpenAi(string $prompt, array $format): string
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            throw new Exception('OpenAI API key is not configured. Set AD_AI_PROVIDER=gemini or add OPENAI_API_KEY.');
        }

        $model = config('services.openai.image_model', 'dall-e-3');
        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $format['dalle_size'] ?? '1024x1024',
                'quality' => 'standard',
                'response_format' => 'b64_json',
            ]);

        if ($response->failed()) {
            throw new Exception('OpenAI image failed: '.($response->json('error.message') ?? $response->body()));
        }

        $b64 = $response->json('data.0.b64_json');
        $raw = $b64 ? base64_decode($b64, true) : false;
        if ($raw === false) {
            throw new Exception('Could not decode OpenAI image.');
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $format
     */
    protected function buildPrompt(array $input, array $format): string
    {
        $custom = trim((string) ($input['ai_image_prompt'] ?? ''));
        if ($custom !== '') {
            return $custom.' Professional social media advertisement, clean typography, no watermarks, no UI mockups.';
        }

        $service = trim((string) ($input['service_name'] ?? 'professional service'));
        $audience = trim((string) ($input['target_audience'] ?? 'potential customers'));
        $benefit = trim((string) ($input['main_benefit'] ?? 'expert help'));
        $headline = trim((string) ($input['headline'] ?? ''));
        $offer = trim((string) ($input['offer_discount'] ?? ''));

        $ratioHint = match ($format['short'] ?? '') {
            '9×16' => 'vertical story ad layout, full-bleed mobile design',
            '4×5' => 'portrait feed ad, bold headline at top, clear call-to-action area at bottom',
            '1:1.91' => 'tall portrait promotional flyer style ad',
            default => 'square social media ad',
        };

        $parts = [
            "Create a {$ratioHint} for \"{$service}\".",
            "Target audience: {$audience}.",
            "Key benefit: {$benefit}.",
        ];

        if ($headline !== '') {
            $parts[] = "Headline text on image: \"{$headline}\".";
        }
        if ($offer !== '') {
            $parts[] = "Highlight offer: {$offer}.";
        }

        $parts[] = 'Style: modern, professional, high contrast, readable text, suitable for Facebook and Instagram ads.';
        $parts[] = 'Include a subtle WhatsApp green accent for the message button area.';
        $parts[] = 'No logos of Meta, Facebook, or Instagram. No fake phone UI.';

        return implode(' ', $parts);
    }
}
