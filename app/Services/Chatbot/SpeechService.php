<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpeechService
{
    protected int $timeout = 60;

    public function transcribeFile(string $absolutePath, string $originalFilename = 'audio.ogg'): ?string
    {
        $key = config('services.openai.key');
        if (! $key || ! is_readable($absolutePath)) {
            Log::channel('voice')->warning('Whisper: missing API key or unreadable file', [
                'path' => $absolutePath,
                'filename' => $originalFilename,
            ]);

            return null;
        }

        try {
            $contents = file_get_contents($absolutePath);
            if ($contents === false || $contents === '') {
                Log::channel('voice')->warning('Whisper: empty or unread file', [
                    'filename' => $originalFilename,
                ]);

                return null;
            }

            $response = Http::withToken($key)
                ->timeout($this->timeout)
                ->attach('file', $contents, $originalFilename)
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                ]);

            if ($response->failed()) {
                Log::warning('Whisper transcription failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                Log::channel('voice')->warning('Whisper API error', [
                    'filename' => $originalFilename,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $text = trim((string) $response->json('text', ''));

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::error('Whisper exception', ['error' => $e->getMessage()]);
            Log::channel('voice')->error('Whisper exception', [
                'filename' => $originalFilename,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return string|null public storage path relative to disk (e.g. tts/uuid.mp3)
     */
    public function textToSpeechMp3(string $text): ?string
    {
        $key = config('services.openai.key');
        if (! $key) {
            return null;
        }

        $text = Str::limit(trim($text), 3900, '…');
        if ($text === '') {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://api.openai.com/v1/audio/speech', [
                    'model' => 'tts-1',
                    'voice' => 'alloy',
                    'input' => $text,
                    'response_format' => 'mp3',
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI TTS failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $path = 'tts/'.Str::uuid().'.mp3';
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $e) {
            Log::error('TTS exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
