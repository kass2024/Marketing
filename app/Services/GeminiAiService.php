<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    public function isConfigured(): bool
    {
        $key = $this->apiKey();

        return $key !== '' && $this->isLikelyValidApiKey($key);
    }

    public function configurationHint(): ?string
    {
        $key = $this->apiKey();
        if ($key === '') {
            return 'Set GOOGLE_AI_API_KEY in .env (AI Studio AIzaSy… or Google AI AQ.… key).';
        }
        if (! $this->isLikelyValidApiKey($key)) {
            return 'GOOGLE_AI_API_KEY looks invalid. Use an AIzaSy… key from https://aistudio.google.com/apikey or a Google AI AQ.… key.';
        }

        return null;
    }

    protected function apiKey(): string
    {
        return trim((string) config('gemini.api_key'), " \t\"'");
    }

    public function generateText(string $prompt, ?string $system = null, ?int $maxTokens = null): string
    {
        $response = $this->sendContentRequest(
            config('gemini.model'),
            $this->buildTextPayload($prompt, $system, $maxTokens)
        );

        return $this->extractText($response);
    }

    /**
     * Vision + text: analyze an image (base64) and return model text (usually JSON).
     */
    public function analyzeImage(
        string $base64Data,
        string $mimeType,
        string $prompt,
        ?string $system = null,
        ?int $maxTokens = null
    ): string {
        $parts = [
            // Image first — improves OCR fidelity on text-heavy flyers
            [
                'inlineData' => [
                    'mimeType' => $mimeType,
                    'data' => $base64Data,
                ],
            ],
            ['text' => $prompt],
        ];
        if ($system) {
            array_unshift($parts, ['text' => $system]);
        }

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'generationConfig' => $this->jsonGenerationConfig(0.1, $maxTokens ?? 3072),
        ];

        $timeout = max((int) config('gemini.timeout'), (int) config('gemini.image_timeout'));
        $response = $this->sendContentRequest(config('gemini.model'), $payload, $timeout);

        return $this->extractText($response);
    }

    /**
     * Text-only JSON (ad copy, campaign fields). Thinking is off so the token
     * budget is spent on the answer, matching Xander School's Gemini client.
     */
    public function generateJson(string $prompt, ?string $system = null, ?int $maxTokens = null): string
    {
        $payload = $this->buildTextPayload($prompt, $system, $maxTokens);
        $payload['generationConfig'] = $this->jsonGenerationConfig(0.4, $maxTokens ?? 2048);

        $response = $this->sendContentRequest(config('gemini.model'), $payload);

        return $this->extractText($response);
    }

    /**
     * Generate a PNG/JPEG binary via Gemini native image generation.
     */
    public function generateImageBinary(string $prompt, ?string $model = null): string
    {
        $model = $model ?? config('gemini.image_model');
        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $response = $this->sendContentRequest($model, $payload, (int) config('gemini.image_timeout'));

        return $this->extractImageBinary($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendContentRequest(string $model, array $payload, ?int $timeout = null): Response
    {
        $hint = $this->configurationHint();
        if ($hint) {
            throw new Exception($hint);
        }

        try {
            return $this->postWithRetries($model, $payload, $timeout);
        } catch (Exception $e) {
            if (! $this->shouldRetryWithoutJsonOptions($e->getMessage(), $payload)) {
                throw $e;
            }

            unset($payload['generationConfig']['thinkingConfig'], $payload['generationConfig']['responseMimeType']);

            return $this->postWithRetries($model, $payload, $timeout);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postWithRetries(string $model, array $payload, ?int $timeout = null): Response
    {
        $url = rtrim((string) config('gemini.base_url'), '/')."/models/{$model}:generateContent";
        $attempts = max(1, (int) config('gemini.retry_attempts'));
        $lastError = 'Gemini request failed';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::connectTimeout((int) config('gemini.connect_timeout'))
                    ->timeout($timeout ?? (int) config('gemini.timeout'))
                    ->withHeaders([
                        'x-goog-api-key' => $this->apiKey(),
                    ])
                    ->post($url, $payload);
            } catch (ConnectionException $e) {
                $lastError = 'Gemini connection error: '.$e->getMessage();
                if ($attempt < $attempts) {
                    usleep((int) config('gemini.retry_delay_ms') * 1000 * $attempt);

                    continue;
                }
                throw new Exception($lastError);
            }

            if ($response->failed()) {
                $lastError = $this->sanitizeError($response->json('error.message') ?? $response->body());
                if ($attempt < $attempts && ! $this->shouldRetryWithoutJsonOptions($lastError, $payload)) {
                    usleep((int) config('gemini.retry_delay_ms') * 1000 * $attempt);

                    continue;
                }
                throw new Exception('Gemini API error: '.$lastError);
            }

            return $response;
        }

        throw new Exception($lastError);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function shouldRetryWithoutJsonOptions(string $message, array $payload): bool
    {
        if (! isset($payload['generationConfig']['thinkingConfig']) && ! isset($payload['generationConfig']['responseMimeType'])) {
            return false;
        }

        return stripos($message, 'thinking') !== false
            || stripos($message, 'Unknown name') !== false
            || stripos($message, 'responseMimeType') !== false
            || stripos($message, 'INVALID_ARGUMENT') !== false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonGenerationConfig(float $temperature, int $maxTokens): array
    {
        return [
            'temperature' => $temperature,
            'maxOutputTokens' => max(2048, $maxTokens),
            'responseMimeType' => 'application/json',
            // gemini-2.5-flash thinks by default and can spend the whole
            // output budget before it writes the JSON.
            'thinkingConfig' => [
                'thinkingBudget' => 0,
            ],
        ];
    }

    protected function buildTextPayload(string $prompt, ?string $system, ?int $maxTokens): array
    {
        $parts = [];
        if ($system) {
            $parts[] = ['text' => $system];
        }
        $parts[] = ['text' => $prompt];

        return [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => $maxTokens ?? (int) config('gemini.max_output_tokens'),
                'thinkingConfig' => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];
    }

    protected function extractText(Response $response): string
    {
        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        $text = '';

        foreach ($parts as $part) {
            if (! empty($part['thought']) || ! empty($part['thoughtSignature']) || ! empty($part['thought_signature'])) {
                continue;
            }
            if (! empty($part['text'])) {
                $text .= $part['text'];
            }
        }

        if (trim($text) === '') {
            foreach ($parts as $part) {
                if (! empty($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        $text = trim($text);
        if ($text === '') {
            $reason = data_get($response->json(), 'candidates.0.finishReason');
            throw new Exception('Gemini returned no text'.($reason ? " ({$reason})" : '').'.');
        }

        return $text;
    }

    protected function extractImageBinary(Response $response): string
    {
        $parts = data_get($response->json(), 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if ($data) {
                $binary = base64_decode($data, true);
                if ($binary !== false) {
                    return $binary;
                }
            }
        }

        $text = '';
        foreach ($parts as $part) {
            if (! empty($part['text'])) {
                $text .= $part['text'];
            }
        }

        Log::warning('GEMINI_IMAGE_NO_BINARY', ['text' => substr($text, 0, 200)]);

        throw new Exception(
            $text !== ''
                ? 'Gemini did not return an image: '.substr($text, 0, 180)
                : 'Gemini returned no image data. Try GEMINI_IMAGE_MODEL=gemini-2.0-flash-preview-image-generation or check regional availability.'
        );
    }

    protected function isLikelyValidApiKey(string $key): bool
    {
        // AI Studio classic keys (AIzaSy…) and Google AI Express / newer keys (AQ.…)
        return str_starts_with($key, 'AIza') || str_starts_with($key, 'AQ.');
    }

    protected function sanitizeError(string $message): string
    {
        $message = preg_replace('/key=[^&\s]+/i', 'key=[REDACTED]', $message) ?? $message;

        return strlen($message) > 280 ? substr($message, 0, 277).'...' : trim($message);
    }
}
