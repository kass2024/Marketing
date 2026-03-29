<?php

namespace App\Services;

/**
 * Converts browser-recorded WebM (and other inputs) to formats accepted by WhatsApp Cloud API (OGG/Opus, MP3).
 */
class WhatsAppAudioConverter
{
    public function toWhatsAppFormat(string $absoluteInputPath): ?string
    {
        if (! is_file($absoluteInputPath) || ! is_readable($absoluteInputPath)) {
            return null;
        }

        $ffmpeg = (string) config('services.whatsapp.ffmpeg_binary', 'ffmpeg');
        $dir = dirname($absoluteInputPath);
        $base = pathinfo($absoluteInputPath, PATHINFO_FILENAME);

        $ogg = $dir.DIRECTORY_SEPARATOR.$base.'-wa.ogg';
        if ($this->runFfmpeg([$ffmpeg, '-y', '-i', $absoluteInputPath, '-c:a', 'libopus', '-b:a', '64k', $ogg])
            && is_file($ogg)) {
            return $ogg;
        }

        $mp3 = $dir.DIRECTORY_SEPARATOR.$base.'-wa.mp3';
        if ($this->runFfmpeg([$ffmpeg, '-y', '-i', $absoluteInputPath, '-vn', '-ar', '44100', '-ac', '1', '-b:a', '128k', $mp3])
            && is_file($mp3)) {
            return $mp3;
        }

        return null;
    }

    /**
     * @param  list<string>  $cmd
     */
    protected function runFfmpeg(array $cmd): bool
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes, null, null);
        if (! is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }
}
