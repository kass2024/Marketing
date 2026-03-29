<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VoiceDiagnoseCommand extends Command
{
    protected $signature = 'voice:diagnose {--json : Machine-readable output}';

    protected $description = 'Diagnose voice notes / TTS / WhatsApp media (ffmpeg, storage, env)';

    public function handle(): int
    {
        $ffmpegEnv = env('FFMPEG_BINARY');
        $ffmpegPath = $ffmpegEnv ?: $this->resolveFfmpegPath();
        $ffmpegVersion = $this->ffmpegVersion($ffmpegPath);

        $publicLink = public_path('storage');
        $linkOk = is_link($publicLink) || File::exists($publicLink);
        $waDir = storage_path('app/public/whatsapp');
        $waWritable = File::isDirectory($waDir) ? File::isWritable($waDir) : null;

        $data = [
            'environment' => app()->environment(),
            'app_url' => config('app.url'),
            'app_url_https' => str_starts_with((string) config('app.url'), 'https://'),
            'voice_faq_replies' => (bool) config('chatbot.voice_faq_replies'),
            'voice_reply_sources' => config('chatbot.voice_reply_sources', []),
            'transcribe_inbound' => (bool) config('chatbot.transcribe_inbound_audio'),
            'ffmpeg_env' => $ffmpegEnv ?: null,
            'ffmpeg_resolved' => $ffmpegPath,
            'ffmpeg_version' => $ffmpegVersion,
            'openai_key_set' => filled(config('services.openai.key')),
            'whatsapp' => [
                'phone_number_id_set' => filled(config('services.whatsapp.phone_number_id')),
                'access_token_set' => filled(config('services.whatsapp.access_token')),
                'graph_url' => config('services.whatsapp.graph_url'),
                'graph_version' => config('services.whatsapp.graph_version'),
            ],
            'storage' => [
                'public_disk_root' => storage_path('app/public'),
                'whatsapp_dir' => $waDir,
                'whatsapp_dir_exists' => File::isDirectory($waDir),
                'whatsapp_dir_writable' => $waWritable,
                'public_storage_link' => $publicLink,
                'public_storage_link_ok' => $linkOk,
            ],
            'logs' => [
                'voice_log' => storage_path('logs/voice.log'),
                'voice_log_writable' => File::isWritable(dirname(storage_path('logs/voice.log'))),
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Voice / WhatsApp media diagnostics');
        $this->newLine();
        $this->table(
            ['Check', 'Value'],
            [
                ['APP_URL', $data['app_url']],
                ['HTTPS (recommended for Meta)', $data['app_url_https'] ? 'yes' : 'no'],
                ['CHATBOT_VOICE_FAQ_REPLIES', $data['voice_faq_replies'] ? 'true' : 'false'],
                ['CHATBOT_VOICE_SOURCES', implode(', ', $data['voice_reply_sources'])],
                ['FFMPEG_BINARY (.env)', $data['ffmpeg_env'] ?? '(not set)'],
                ['ffmpeg resolved path', $data['ffmpeg_resolved'] ?? '(none)'],
                ['ffmpeg -version (first line)', $data['ffmpeg_version'] ?? '(could not run)'],
                ['OpenAI API key', $data['openai_key_set'] ? 'set' : 'missing'],
                ['WA phone_number_id', $data['whatsapp']['phone_number_id_set'] ? 'set' : 'missing'],
                ['WA access_token', $data['whatsapp']['access_token_set'] ? 'set' : 'missing'],
                ['storage/app/public/whatsapp', $this->dirStatus($waDir, $waWritable)],
                ['public/storage link', $linkOk ? 'ok' : 'missing — run php artisan storage:link'],
                ['voice.log', $data['logs']['voice_log']],
            ]
        );

        $this->newLine();
        $this->comment('Tail voice log: Get-Content storage\\logs\\voice.log -Tail 80  (Unix: tail -f storage/logs/voice.log)');

        return self::SUCCESS;
    }

    private function dirStatus(string $dir, ?bool $writable): string
    {
        if (! File::isDirectory($dir)) {
            return 'missing (created on first upload if parent writable)';
        }

        return $writable ? 'exists, writable' : 'exists, not writable';
    }

    private function resolveFfmpegPath(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $out = shell_exec('where ffmpeg 2>nul');

            return $out ? trim(explode("\n", $out)[0]) : null;
        }

        $out = shell_exec('command -v ffmpeg 2>/dev/null');

        return $out ? trim($out) : null;
    }

    private function ffmpegVersion(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $out = @shell_exec(escapeshellarg($path).' -version 2>&1');
        if (! is_string($out) || $out === '') {
            return null;
        }

        $line = strtok($out, "\n");

        return $line !== false ? trim($line) : null;
    }
}
