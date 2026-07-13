<?php

namespace App\Services\Tenant;

use App\Models\Client;
use App\Models\PlatformMetaConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TenantWhatsAppSyncService
{
    protected string $graphUrl;

    protected string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config('platform.meta.graph_version', config('services.meta.graph_version', 'v19.0'));
        $this->graphUrl = rtrim(config('platform.meta.graph_url', config('services.meta.graph_url', 'https://graph.facebook.com')), '/');
    }

    /**
     * @return array{status: string, phone_number_id: ?string, message: string}
     */
    public function provisionAndRequestCode(Client $client): array
    {
        $token = $this->platformToken();
        $wabaId = $this->wabaId();
        $digits = TenantMetaPageValidator::normalizeWhatsAppNumber((string) $client->whatsapp_phone_number);
        $verifiedName = $this->verifiedNameFor($client);

        $existing = $this->findPhoneOnWaba($wabaId, $token, $digits);

        if ($existing) {
            $phoneNumberId = (string) $existing['id'];
            $status = (string) ($existing['code_verification_status'] ?? '');

            if (strtoupper($status) === 'VERIFIED') {
                return $this->finalizeVerifiedClient($client, $phoneNumberId, $verifiedName, $existing);
            }

            $this->requestCode($phoneNumberId, $token);

            $client->update([
                'whatsapp_phone_number_id' => $phoneNumberId,
                'whatsapp_verified_name' => $verifiedName,
                'whatsapp_verification_status' => 'code_sent',
            ]);

            return [
                'status' => 'code_sent',
                'phone_number_id' => $phoneNumberId,
                'message' => 'Verification code sent by Meta to your WhatsApp number.',
            ];
        }

        [$cc, $national] = $this->parsePhoneParts($digits);

        $create = Http::timeout(45)->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$wabaId}/phone_numbers",
            [
                'cc' => $cc,
                'phone_number' => $national,
                'verified_name' => $verifiedName,
                'access_token' => $token,
            ]
        );

        if (! $create->ok()) {
            $message = $create->json('error.message') ?? 'Could not add number to WhatsApp Business Account.';

            Log::error('TENANT_WA_ADD_FAILED', [
                'client_id' => $client->id,
                'response' => $create->json(),
            ]);

            throw ValidationException::withMessages([
                'whatsapp_phone_number' => $message,
            ]);
        }

        $phoneNumberId = (string) ($create->json('id') ?? '');

        if ($phoneNumberId === '') {
            throw ValidationException::withMessages([
                'whatsapp_phone_number' => 'Meta did not return a phone number ID.',
            ]);
        }

        $this->requestCode($phoneNumberId, $token);

        $client->update([
            'whatsapp_phone_number_id' => $phoneNumberId,
            'whatsapp_verified_name' => $verifiedName,
            'whatsapp_verification_status' => 'code_sent',
        ]);

        return [
            'status' => 'code_sent',
            'phone_number_id' => $phoneNumberId,
            'message' => "Number added to Business Manager as \"{$verifiedName}\". Enter the SMS code Meta sent you.",
        ];
    }

    /**
     * @return array{status: string, phone_number_id: ?string, message: string}
     */
    public function verifyCodeAndRegister(Client $client, string $code): array
    {
        $token = $this->platformToken();
        $phoneNumberId = (string) $client->whatsapp_phone_number_id;

        if ($phoneNumberId === '') {
            throw ValidationException::withMessages([
                'whatsapp_verification_code' => 'No pending WhatsApp number. Start registration again.',
            ]);
        }

        $verify = Http::timeout(45)->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}/verify_code",
            [
                'code' => preg_replace('/\D+/', '', $code),
                'access_token' => $token,
            ]
        );

        if (! $verify->ok()) {
            $message = $verify->json('error.message') ?? 'Invalid or expired verification code.';

            throw ValidationException::withMessages([
                'whatsapp_verification_code' => $message,
            ]);
        }

        $pin = (string) config('platform.whatsapp.registration_pin', '123456');

        $register = Http::timeout(45)->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}/register",
            [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
                'access_token' => $token,
            ]
        );

        if (! $register->ok()) {
            Log::warning('TENANT_WA_REGISTER_FAILED', [
                'client_id' => $client->id,
                'response' => $register->json(),
            ]);
        }

        $details = $this->getPhoneDetails($phoneNumberId, $token);

        return $this->finalizeVerifiedClient(
            $client,
            $phoneNumberId,
            $this->verifiedNameFor($client),
            $details
        );
    }

    /**
     * @param  array<string, mixed>|null  $metaPhone
     * @return array{status: string, phone_number_id: ?string, message: string}
     */
    protected function finalizeVerifiedClient(
        Client $client,
        string $phoneNumberId,
        string $verifiedName,
        ?array $metaPhone = null
    ): array {
        $display = $metaPhone['display_phone_number'] ?? null;

        $client->update([
            'whatsapp_phone_number_id' => $phoneNumberId,
            'whatsapp_verified_name' => $metaPhone['verified_name'] ?? $verifiedName,
            'whatsapp_verification_status' => 'verified',
            'whatsapp_verified_at' => now(),
            'whatsapp_meta_synced_at' => now(),
            'whatsapp_phone_number' => $display
                ? TenantMetaPageValidator::normalizeWhatsAppNumber($display)
                : $client->whatsapp_phone_number,
        ]);

        return [
            'status' => 'verified',
            'phone_number_id' => $phoneNumberId,
            'message' => 'WhatsApp business number verified and synced with Meta.',
        ];
    }

    protected function verifiedNameFor(Client $client): string
    {
        $name = trim((string) $client->company_name);

        return mb_substr($name !== '' ? $name : 'Business', 0, 128);
    }

    protected function platformToken(): string
    {
        $connection = PlatformMetaConnection::query()->platformDefault()->active()->first();
        $token = $connection?->plainAccessToken();

        if ($token) {
            return $token;
        }

        $token = config('platform.meta.system_user_token') ?: config('platform.whatsapp.access_token');

        if (! $token) {
            throw ValidationException::withMessages([
                'registration_error' => 'Platform Meta token is not configured.',
            ]);
        }

        return $token;
    }

    protected function wabaId(): string
    {
        $id = PlatformMetaConnection::query()->platformDefault()->value('whatsapp_business_id')
            ?: config('platform.whatsapp.business_id');

        if (! $id) {
            throw ValidationException::withMessages([
                'registration_error' => 'Platform WhatsApp Business Account ID is not configured.',
            ]);
        }

        return (string) $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findPhoneOnWaba(string $wabaId, string $token, string $digits): ?array
    {
        $response = Http::timeout(30)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$wabaId}/phone_numbers",
            [
                'access_token' => $token,
                'fields' => 'id,display_phone_number,verified_name,code_verification_status,status',
            ]
        );

        if (! $response->ok()) {
            return null;
        }

        foreach ($response->json('data', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $display = TenantMetaPageValidator::normalizeWhatsAppNumber((string) ($row['display_phone_number'] ?? ''));

            if ($display === $digits) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getPhoneDetails(string $phoneNumberId, string $token): ?array
    {
        $response = Http::timeout(30)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}",
            [
                'access_token' => $token,
                'fields' => 'id,display_phone_number,verified_name,code_verification_status,status',
            ]
        );

        return $response->ok() ? $response->json() : null;
    }

    protected function requestCode(string $phoneNumberId, string $token): void
    {
        $response = Http::timeout(45)->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}/request_code",
            [
                'code_method' => 'SMS',
                'language' => 'en_US',
                'access_token' => $token,
            ]
        );

        if (! $response->ok()) {
            $message = $response->json('error.message') ?? 'Could not send Meta verification SMS.';

            throw ValidationException::withMessages([
                'whatsapp_phone_number' => $message,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parsePhoneParts(string $digits): array
    {
        if (str_starts_with($digits, '1') && strlen($digits) === 11) {
            return ['1', substr($digits, 1)];
        }

        if (strlen($digits) === 10) {
            return ['1', $digits];
        }

        if (strlen($digits) > 10) {
            return [substr($digits, 0, strlen($digits) - 10), substr($digits, -10)];
        }

        throw ValidationException::withMessages([
            'whatsapp_phone_number' => 'Could not parse country code from this number.',
        ]);
    }
}
