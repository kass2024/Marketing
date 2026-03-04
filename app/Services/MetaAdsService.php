<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsService
{
    protected string $baseUrl = 'https://graph.facebook.com/v19.0';
    protected ?string $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.meta.access_token');
    }

    /**
     * Get Ad Accounts from Meta
     */
    public function getAdAccounts(): array
    {
        try {
            $response = Http::get("{$this->baseUrl}/me/adaccounts", [
                'access_token' => $this->accessToken,
            ]);

            if ($response->failed()) {
                Log::error('Meta API Error', [
                    'response' => $response->body()
                ]);

                return [];
            }

            return $response->json();

        } catch (\Exception $e) {

            Log::error('MetaAdsService Exception', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}