<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class PlatformSettings
{
    protected static function path(): string
    {
        return storage_path('app/platform_branding.json');
    }

    /**
     * @return array{xander_name: string, xander_email: string}
     */
    public static function all(): array
    {
        $defaults = [
            'xander_name' => (string) config('platform.xander_name', ''),
            'xander_email' => (string) config('platform.xander_email', ''),
        ];

        if (! File::exists(self::path())) {
            return $defaults;
        }

        $stored = json_decode(File::get(self::path()), true);

        if (! is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, [
            'xander_name' => (string) ($stored['xander_name'] ?? $defaults['xander_name']),
            'xander_email' => (string) ($stored['xander_email'] ?? $defaults['xander_email']),
        ]);
    }

    /**
     * @param  array{xander_name?: string, xander_email?: string}  $data
     */
    public static function save(array $data): void
    {
        File::put(
            self::path(),
            json_encode(
                [
                    'xander_name' => (string) ($data['xander_name'] ?? ''),
                    'xander_email' => (string) ($data['xander_email'] ?? ''),
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );
    }
}
