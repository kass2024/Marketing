<?php

namespace App\Services;

use App\Services\Meta\MetaApiLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class MetaAdsService
{
    protected string $baseUrl;
    protected ?string $accessToken;
    protected ?string $defaultAccount;
    protected bool $debug;
    protected MetaApiLogger $apiLogger;

    public function __construct(?MetaApiLogger $apiLogger = null)
    {
        $version = config('services.meta.graph_version', 'v19.0');

        $this->baseUrl = "https://graph.facebook.com/{$version}";
        $this->accessToken = config('services.meta.token')
            ?: config('platform.meta.system_user_token')
            ?: null;

        if (! $this->accessToken) {
            try {
                $connection = \App\Models\PlatformMetaConnection::query()->platformDefault()->active()->first();
                $this->accessToken = $connection?->plainAccessToken();
            } catch (\Throwable) {
                // Boot without DB (e.g. config:cache) is fine.
            }
        }

        $accountId = config('services.meta.ad_account_id');
        $this->defaultAccount = $accountId
            ? $this->formatAccount($accountId)
            : null;

        $this->debug = config('app.debug', false);
        $this->apiLogger = $apiLogger ?? new MetaApiLogger();

        if ($this->accessToken && $this->defaultAccount) {
            Log::info('META_SERVICE_INITIALIZED', [
                'account' => $this->defaultAccount,
                'graph_version' => $version,
            ]);
        }
    }

    protected function ensureConfigured(): void
    {
        if (! $this->accessToken) {
            throw new Exception(
                'Meta access token missing. Copy .env.example to .env and set META_SYSTEM_USER_TOKEN.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ACCOUNT
    |--------------------------------------------------------------------------
    */

    protected function formatAccount(?string $id): string
    {
        if(!$id){
            throw new Exception('Meta Ad Account ID missing.');
        }

        return str_starts_with($id,'act_') ? $id : "act_{$id}";
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP CLIENT
    |--------------------------------------------------------------------------
    */

    protected function client(bool $forMutation = false, bool $forSearch = false)
    {
        $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

        if ($forMutation) {
            $timeout = (int) config('services.meta.mutation_timeout', 25);

            return Http::timeout($timeout)
                ->connectTimeout(min($connectTimeout, 15))
                ->acceptJson();
        }

        if ($forSearch) {
            $timeout = (int) config('services.meta.search_timeout', 15);

            return Http::timeout($timeout)
                ->connectTimeout(min($connectTimeout, 10))
                ->retry(1, 500)
                ->acceptJson();
        }

        $timeout = (int) config('services.meta.http_timeout', 90);

        return Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry(4, 2000)
            ->acceptJson();
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE ERROR
    |--------------------------------------------------------------------------
    */
protected function handleError($response, $endpoint, $payload = [])
{
    /*
    |--------------------------------------------------------------------------
    | Parse Response Safely
    |--------------------------------------------------------------------------
    */

    $body = null;

    try {
        $body = $response->json();
    } catch (\Throwable $e) {
        $body = $response->body();
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Error Message Safely
    |--------------------------------------------------------------------------
    */

    $message = 'Meta API Error';

    if (is_array($body) && isset($body['error']['message'])) {
        $message = $body['error']['message'];
        if (! empty($body['error']['error_user_msg'])) {
            $message .= ' — ' . $body['error']['error_user_msg'];
        }
        if (! empty($body['error']['error_subcode'])) {
            $message .= ' (Meta subcode ' . $body['error']['error_subcode'] . ')';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Log Detailed Meta Error
    |--------------------------------------------------------------------------
    */

    Log::error('META_API_ERROR', [

        'endpoint' => $endpoint,

        'http_status' => $response->status(),

        'payload' => $payload,

        'response' => $body,

        'meta_error_code' => $body['error']['code'] ?? null,

        'meta_error_type' => $body['error']['type'] ?? null

    ]);

    /*
    |--------------------------------------------------------------------------
    | Throw Exception
    |--------------------------------------------------------------------------
    */

    throw new Exception($message);
}

    /*
    |--------------------------------------------------------------------------
    | BASE REQUEST
    |--------------------------------------------------------------------------
    */

    protected function request(string $method, string $endpoint, array $payload = [], bool $asForm = true, bool $forMutation = false, int $attempt = 1)
    {
        $this->ensureConfigured();

        $payload['access_token'] = $this->accessToken;
        $started = microtime(true);

        Log::info("META_API_{$method}", [
            'endpoint' => $endpoint,
            'payload' => $this->apiLogger->redactSecrets($payload),
        ]);

        $client = $this->client($forMutation);

        if ($asForm) {
            $client = $client->asForm();
        }

        $response = $client->{$method}(
            "{$this->baseUrl}/{$endpoint}",
            $payload
        );

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $body = null;

        try {
            $body = $response->json();
        } catch (Throwable) {
            $body = ['raw' => $response->body()];
        }

        if ($response->failed()) {
            $metaCode = data_get($body, 'error.code');
            $isRetryable = $this->apiLogger->isRetryableError(
                is_numeric($metaCode) ? (int) $metaCode : null,
                $response->status()
            );

            $this->apiLogger->log(
                $method,
                $endpoint,
                $payload,
                is_array($body) ? $body : null,
                $response->status(),
                false,
                data_get($body, 'error.message'),
                $durationMs
            );

            if ($isRetryable && $attempt < 3) {
                usleep(500000 * $attempt);

                return $this->request($method, $endpoint, $payload, $asForm, $forMutation, $attempt + 1);
            }

            $this->handleError($response, $endpoint, $payload);
        }

        $this->apiLogger->log(
            $method,
            $endpoint,
            $payload,
            is_array($body) ? $body : null,
            $response->status(),
            true,
            null,
            $durationMs
        );

        return $body;
    }

    /*
    |--------------------------------------------------------------------------
    | GET
    |--------------------------------------------------------------------------
    */

    protected function get(string $endpoint,array $params=[]):array
    {
        return $this->request('get',$endpoint,$params,false);
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    protected function post(string $endpoint,array $payload=[], bool $forMutation = false):array
    {
        return $this->request('post',$endpoint,$payload,true, $forMutation);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    protected function delete(string $endpoint):array
    {
        return $this->request('delete',$endpoint,[],false);
    }

    /*
    |--------------------------------------------------------------------------
    | CONNECTION TEST
    |--------------------------------------------------------------------------
    */

    public function checkConnection():bool
    {
        try{
            $this->get('me');
            return true;
        }
        catch(Exception $e){

            Log::error('META_CONNECTION_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET PAGES
    |--------------------------------------------------------------------------
    */

    public function getPages(): array
    {
        try {
            $res = $this->get('me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account{id,username},connected_instagram_account{id,username}',
                'limit' => 100,
            ]);
            $data = $res['data'] ?? [];
            if ($data !== []) {
                return $data;
            }
        } catch (Throwable $e) {
            Log::warning('META_GET_PAGES_FAILED', [
                'message' => $e->getMessage(),
            ]);
        }

        $pageId = config('services.meta.page_id');
        if (! empty($pageId)) {
            Log::info('META_GET_PAGES_USING_CONFIG_FALLBACK', [
                'page_id' => $pageId,
            ]);

            try {
                $page = $this->get((string) $pageId, [
                    'fields' => 'id,name,access_token,instagram_business_account{id,username,name},connected_instagram_account{id,username,name}',
                ]);
                if (! empty($page['id'])) {
                    return [$page];
                }
            } catch (Throwable $e) {
                Log::warning('META_GET_PAGE_FALLBACK_FAILED', [
                    'page_id' => $pageId,
                    'message' => $e->getMessage(),
                ]);
            }

            return [
                [
                    'id' => (string) $pageId,
                    'name' => (string) config('services.meta.page_name', 'Facebook Page'),
                ],
            ];
        }

        return [];
    }

    /**
     * Pages enriched with linked Instagram accounts (for Ad Studio identity / destinations).
     *
     * @return array<int, array{id:string,name:string,instagram_id:?string,instagram_username:?string}>
     */
    public function listPagesWithInstagram(): array
    {
        $pages = [];
        foreach ($this->getPages() as $page) {
            $id = (string) ($page['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $ig = $page['connected_instagram_account'] ?? $page['instagram_business_account'] ?? null;
            $pageToken = $page['access_token'] ?? null;

            // me/accounts sometimes omits nested IG — hydrate from the Page node
            if ((! is_array($ig) || empty($ig['id']) || empty($ig['username'])) && $id !== '') {
                try {
                    $hydrated = $this->get($id, [
                        'fields' => 'id,name,access_token,instagram_business_account{id,username,name},connected_instagram_account{id,username,name}',
                    ]);
                    $ig = $hydrated['connected_instagram_account']
                        ?? $hydrated['instagram_business_account']
                        ?? $ig;
                    if (! $pageToken && ! empty($hydrated['access_token'])) {
                        $pageToken = $hydrated['access_token'];
                    }
                } catch (Throwable $e) {
                    Log::info('META_PAGE_IG_HYDRATE_FAILED', [
                        'page_id' => $id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            // Username often requires the Page access token (not system-user token)
            if (is_array($ig) && ! empty($ig['id']) && empty($ig['username']) && $pageToken) {
                try {
                    $igUser = Http::timeout(30)->get(
                        rtrim((string) config('services.meta.graph_url', 'https://graph.facebook.com'), '/').'/'
                        .config('services.meta.graph_version', 'v19.0').'/'.$ig['id'],
                        [
                            'access_token' => $pageToken,
                            'fields' => 'id,username,name,profile_picture_url',
                        ]
                    );
                    if ($igUser->ok() && ! empty($igUser->json('username'))) {
                        $ig['username'] = $igUser->json('username');
                        $ig['name'] = $igUser->json('name') ?: ($ig['name'] ?? null);
                    }
                } catch (Throwable) {
                    // directory still has the IG id
                }
            }

            $pages[] = [
                'id' => $id,
                'name' => (string) ($page['name'] ?? $id),
                'access_token' => $pageToken,
                'instagram_id' => is_array($ig) && ! empty($ig['id']) ? (string) $ig['id'] : null,
                'instagram_username' => is_array($ig) && ! empty($ig['username']) ? (string) $ig['username'] : null,
                'instagram_name' => is_array($ig) && ! empty($ig['name']) ? (string) $ig['name'] : null,
            ];
        }

        return $pages;
    }

    /**
     * All Instagram business accounts discoverable from pages + ad account.
     *
     * @return array<int, array{id:string,username:?string,source:string,page_id:?string,page_name:?string}>
     */
    public function listInstagramAccounts(?string $preferredPageId = null): array
    {
        $byId = [];

        foreach ($this->listPagesWithInstagram() as $page) {
            if (! empty($page['instagram_id'])) {
                $byId[$page['instagram_id']] = [
                    'id' => $page['instagram_id'],
                    'username' => $page['instagram_username'],
                    'source' => 'page',
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                ];
            }
        }

        // Only deep-lookup the preferred page (avoid N Graph calls per page — was making Ad Studio hang)
        $preferred = trim((string) ($preferredPageId ?? ''));
        if ($preferred !== '' && ! collect($byId)->contains(fn ($row) => ($row['page_id'] ?? null) === $preferred)) {
            $diag = $this->diagnoseInstagramConnection($preferred);
            $igId = (string) ($diag['instagram_user_id'] ?? '');
            if ($igId !== '' && ! isset($byId[$igId])) {
                $byId[$igId] = [
                    'id' => $igId,
                    'username' => $diag['instagram_username'] ?? null,
                    'source' => (string) ($diag['source'] ?? 'page'),
                    'page_id' => $preferred,
                    'page_name' => null,
                ];
            }
        }

        try {
            $accountId = $this->formatAccount(config('services.meta.ad_account_id'));
            $res = $this->get("{$accountId}/instagram_accounts", [
                'fields' => 'id,username',
                'limit' => 50,
            ]);
            foreach ($res['data'] ?? [] as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                if (! isset($byId[$id])) {
                    $byId[$id] = [
                        'id' => $id,
                        'username' => $row['username'] ?? null,
                        'source' => 'ad_account',
                        'page_id' => null,
                        'page_name' => null,
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::warning('META_LIST_INSTAGRAM_ACCOUNTS_FAILED', ['error' => $e->getMessage()]);
        }

        // Business Manager Instagram assets (Marketing API / BM guides)
        $businessId = trim((string) (
            config('platform.meta.business_id')
            ?: config('services.meta.business_id')
            ?: ''
        ));
        if ($businessId === '') {
            try {
                $connection = \App\Models\PlatformMetaConnection::query()->platformDefault()->active()->first();
                $businessId = trim((string) ($connection?->business_id ?? ''));
            } catch (Throwable) {
                $businessId = '';
            }
        }
        if ($businessId !== '') {
            foreach ([
                'owned_instagram_accounts',
                'owned_instagram_assets',
                'client_instagram_assets',
                'client_instagram_accounts',
                'instagram_accounts',
            ] as $edge) {
                try {
                    $res = $this->get("{$businessId}/{$edge}", [
                        'fields' => 'id,username,name',
                        'limit' => 50,
                    ]);
                    foreach ($res['data'] ?? [] as $row) {
                        $nested = is_array($row['ig_user'] ?? null) ? $row['ig_user'] : null;
                        if ($nested) {
                            $row = array_merge($row, $nested);
                        }
                        $id = (string) ($row['id'] ?? '');
                        if ($id === '') {
                            continue;
                        }
                        if (! isset($byId[$id])) {
                            $byId[$id] = [
                                'id' => $id,
                                'username' => $row['username'] ?? null,
                                'source' => $edge,
                                'page_id' => null,
                                'page_name' => null,
                            ];
                        } elseif (empty($byId[$id]['username']) && ! empty($row['username'])) {
                            $byId[$id]['username'] = $row['username'];
                        }
                    }
                } catch (Throwable $e) {
                    Log::info('META_BM_IG_EDGE_SKIP', ['edge' => $edge, 'error' => $e->getMessage()]);
                }
            }
        }

        $envIg = trim((string) config('services.meta.instagram_user_id', ''));
        if ($envIg !== '' && ! isset($byId[$envIg])) {
            $byId[$envIg] = [
                'id' => $envIg,
                'username' => null,
                'source' => 'env',
                'page_id' => null,
                'page_name' => null,
            ];
        }

        return array_values($byId);
    }

    /*
    |--------------------------------------------------------------------------
    | GET AD ACCOUNTS
    |--------------------------------------------------------------------------
    */

    public function getAdAccounts(): array
    {
        $this->ensureConfigured();

        return $this->get('me/adaccounts', [
            'fields' => 'id,account_id,name,account_status,currency',
            'limit' => 100,
        ]);
    }

    /**
     * Ad accounts available for business self-registration (excludes platform main account).
     *
     * @return array<int, array{id:string,name:string,currency:?string,status:string}>
     */
    public function getBusinessAdAccounts(): array
    {
        try {
            $response = $this->getAdAccounts();
        } catch (Throwable $e) {
            Log::warning('META_GET_AD_ACCOUNTS_FAILED', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $platformId = $this->normalizeAccountId(config('services.meta.ad_account_id'));
        $accounts = [];

        foreach ($response['data'] ?? [] as $account) {
            $id = (string) ($account['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $normalized = $this->normalizeAccountId($id);

            if ($platformId && $normalized === $platformId) {
                continue;
            }

            $statusMap = [
                1 => 'ACTIVE',
                2 => 'DISABLED',
                3 => 'UNSETTLED',
                7 => 'PENDING',
            ];

            $accounts[] = [
                'id' => $normalized,
                'name' => (string) ($account['name'] ?? $normalized),
                'currency' => $account['currency'] ?? null,
                'status' => $statusMap[$account['account_status'] ?? null] ?? 'UNKNOWN',
            ];
        }

        return $accounts;
    }

    protected function normalizeAccountId(?string $id): ?string
    {
        if (! $id) {
            return null;
        }

        return str_starts_with($id, 'act_') ? $id : 'act_'.$id;
    }

    public function leadgenTosAcceptUrl(string $pageId): string
    {
        return 'https://www.facebook.com/ads/leadgen/tos?page_id='.urlencode($pageId);
    }

    /**
     * @return array{accepted: bool, acceptance_time: ?string, page_name: ?string, error: ?string}
     */
    public function getPageLeadgenTosStatus(string $pageId): array
    {
        $this->ensureConfigured();

        try {
            $response = $this->get($pageId, [
                'fields' => 'id,name,leadgen_tos_accepted,leadgen_tos_acceptance_time',
            ]);

            return [
                'accepted' => (bool) ($response['leadgen_tos_accepted'] ?? false),
                'acceptance_time' => $response['leadgen_tos_acceptance_time'] ?? null,
                'page_name' => $response['name'] ?? null,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('META_LEADGEN_TOS_CHECK_FAILED', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'accepted' => false,
                'acceptance_time' => null,
                'page_name' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function formatLeadgenTosError(string $pageId, ?string $pageName = null): string
    {
        $label = $pageName ? 'Page "'.$pageName.'"' : 'This Facebook Page';
        $url = $this->leadgenTosAcceptUrl($pageId);

        return $label." must accept Meta Lead Generation Terms before lead ad sets can run. "
            ."Open {$url} while logged in as a Page admin, click Accept, then try again.";
    }

    protected function isLeadgenTosError(Throwable $e): bool
    {
        return str_contains($e->getMessage(), '1815089')
            || str_contains($e->getMessage(), 'Lead Generation Terms');
    }

    protected function enrichLeadgenTosError(Exception $e, array $payload): Exception
    {
        if (! $this->isLeadgenTosError($e)) {
            return $e;
        }

        $pageId = null;
        $promotedObject = $payload['promoted_object'] ?? null;

        if (is_string($promotedObject)) {
            $decoded = json_decode($promotedObject, true);
            $pageId = $decoded['page_id'] ?? null;
        } elseif (is_array($promotedObject)) {
            $pageId = $promotedObject['page_id'] ?? null;
        }

        if (! $pageId) {
            return $e;
        }

        $status = $this->getPageLeadgenTosStatus((string) $pageId);

        return new Exception($this->formatLeadgenTosError(
            (string) $pageId,
            $status['page_name'] ?? null
        ));
    }

    public function normalizeLandingUrlForMeta(string $url, bool $strict = false): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new Exception('Website URL is empty.');
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        $url = preg_replace('#^http://#i', 'https://', $url, 1);

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            throw new Exception('Website URL must include a hostname.');
        }

        if ($strict) {
            if (! str_contains($host, '.')) {
                throw new Exception('Website URL must be a valid hostname (e.g. https://www.example.com/path).');
            }

            $blocked = ['facebook.com', 'fb.com', 'fb.me', 'messenger.com'];
            foreach ($blocked as $b) {
                if ($host === $b || str_ends_with($host, '.'.$b)) {
                    throw new Exception('Use your own website as the destination, not '.$b.'.');
                }
            }

            if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
                throw new Exception('Website URL must use https://.');
            }
        }

        return rtrim($url, '/');
    }

    /**
     * @return list<string>
     */
    public function landingUrlCandidates(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return [];
        }

        try {
            $primary = $this->normalizeLandingUrlForMeta($url, false);
        } catch (Exception) {
            return [];
        }

        $candidates = [$primary];
        $parts = parse_url($primary);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        if ($host !== '' && ! str_starts_with($host, 'www.')) {
            $candidates[] = 'https://www.'.$host.$path.$query;
        }

        if (str_starts_with($host, 'www.')) {
            $candidates[] = 'https://'.substr($host, 4).$path.$query;
        }

        return array_values(array_unique(array_map(fn ($u) => rtrim($u, '/'), $candidates)));
    }

    public function getConnectedInstagramUserId(string $pageId): ?string
    {
        return $this->resolveInstagramUserId($pageId);
    }

    public function resolveInstagramUserId(?string $pageId = null, ?string $accountId = null): ?string
    {
        foreach ($this->instagramPageIdCandidates($pageId) as $candidate) {
            $found = $this->lookupInstagramIdFromPage($candidate);
            if ($found !== null) {
                return $found;
            }
        }

        $fromAccount = $this->lookupInstagramIdFromAdAccount($accountId);
        if ($fromAccount !== null) {
            return $fromAccount;
        }

        $fromEnv = trim((string) config('services.meta.instagram_user_id', ''));

        return $fromEnv !== '' ? $fromEnv : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnoseInstagramConnection(?string $pageId = null, ?string $accountId = null): array
    {
        $pageId = trim((string) ($pageId ?? \App\Support\TenantScope::pageId() ?? config('services.meta.page_id', '')));
        $accountId = $accountId ?? \App\Support\TenantScope::adAccountMetaId() ?? config('services.meta.ad_account_id');
        $report = [
            'page_id' => $pageId,
            'ad_account_id' => $accountId,
            'app' => 'WABA',
            'instagram_user_id' => null,
            'source' => null,
            'page_connected' => false,
            'env_fallback' => trim((string) config('services.meta.instagram_user_id', '')) !== '',
            'ready' => false,
            'hints' => [],
        ];

        if ($pageId !== '') {
            foreach (['connected_instagram_account{id,username}', 'instagram_business_account{id,username}'] as $fields) {
                try {
                    $res = $this->get($pageId, ['fields' => $fields]);
                    $key = str_contains($fields, 'connected') ? 'connected_instagram_account' : 'instagram_business_account';
                    $account = $res[$key] ?? null;
                    if (is_array($account) && ! empty($account['id'])) {
                        $report['page_connected'] = true;
                        $report['instagram_user_id'] = (string) $account['id'];
                        $report['instagram_username'] = $account['username'] ?? null;
                        $report['source'] = 'page:'.$key;
                        break;
                    }
                } catch (Exception $e) {
                    $report['page_errors'][] = $fields.': '.$e->getMessage();
                }
            }
        }

        if ($report['instagram_user_id'] === null) {
            $fromAccount = $this->lookupInstagramIdFromAdAccount($accountId);
            if ($fromAccount !== null) {
                $report['instagram_user_id'] = $fromAccount;
                $report['source'] = 'ad_account:instagram_accounts';
            }
        }

        if ($report['instagram_user_id'] === null && $report['env_fallback']) {
            $report['instagram_user_id'] = trim((string) config('services.meta.instagram_user_id', ''));
            $report['source'] = 'env:META_INSTAGRAM_USER_ID';
        }

        $report['ready'] = $report['instagram_user_id'] !== null && $report['instagram_user_id'] !== '';

        if (! $report['ready']) {
            $report['hints'] = [
                'WABA uses its own .env (META_AD_ACCOUNT_ID, META_PAGE_ID) — not xanderbot credentials.',
                'Link this WABA Page to Instagram in Meta Business Suite, or set META_INSTAGRAM_USER_ID in WABA .env only.',
                'Per-business: set meta_page_id on the client record so IG lookup uses the correct Page.',
            ];
        }

        return $report;
    }

    /**
     * @return list<string>
     */
    protected function instagramPageIdCandidates(?string $pageId): array
    {
        $ids = [
            trim((string) $pageId),
            trim((string) (\App\Support\TenantScope::pageId() ?? '')),
            trim((string) config('services.meta.page_id', '')),
        ];

        return array_values(array_unique(array_filter($ids)));
    }

    protected function lookupInstagramIdFromPage(string $pageId): ?string
    {
        foreach (['connected_instagram_account{id}', 'instagram_business_account{id}'] as $fields) {
            try {
                $res = $this->get($pageId, ['fields' => $fields]);
                $key = str_contains($fields, 'connected') ? 'connected_instagram_account' : 'instagram_business_account';
                $account = $res[$key] ?? null;

                if (is_array($account) && ! empty($account['id'])) {
                    return (string) $account['id'];
                }
            } catch (Exception $e) {
                Log::warning('META_PAGE_INSTAGRAM_LOOKUP_FAILED', [
                    'page_id' => $pageId,
                    'fields' => $fields,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function lookupInstagramIdFromAdAccount(?string $accountId = null): ?string
    {
        try {
            $accountId = $this->formatAccount($accountId ?? config('services.meta.ad_account_id'));
            $res = $this->get("{$accountId}/instagram_accounts", [
                'fields' => 'id,username',
                'limit' => 5,
            ]);

            foreach ($res['data'] ?? [] as $row) {
                if (! empty($row['id'])) {
                    return (string) $row['id'];
                }
            }
        } catch (Exception $e) {
            Log::warning('META_AD_ACCOUNT_INSTAGRAM_LOOKUP_FAILED', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $targeting
     * @return array<string, mixed>
     */
    public function applyFacebookInstagramPlacements(array $targeting): array
    {
        $platforms = $targeting['publisher_platforms'] ?? [];

        if (! is_array($platforms)) {
            $platforms = [];
        }

        $merged = array_values(array_unique(array_merge($platforms, ['facebook', 'instagram'])));

        $targeting['publisher_platforms'] = $merged;

        return $this->enrichPlacementTargeting($targeting);
    }

    public function ensureAdSetTargetsInstagram(string $adsetMetaId): bool
    {
        $meta = $this->getAdSet($adsetMetaId);
        $targeting = $meta['targeting'] ?? [];

        if (is_string($targeting)) {
            $decoded = json_decode($targeting, true);
            $targeting = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($targeting)) {
            $targeting = [];
        }

        $platforms = $targeting['publisher_platforms'] ?? [];

        if (is_array($platforms) && in_array('instagram', $platforms, true)) {
            return false;
        }

        $patched = $this->applyFacebookInstagramPlacements($targeting);

        $this->updateAdSet($adsetMetaId, [
            'targeting' => json_encode($patched, JSON_THROW_ON_ERROR),
        ]);

        return true;
    }

    public function attachCreativeToAd(string $adId, string $creativeId): array
    {
        return $this->updateAd($adId, [
            'creative' => json_encode(
                ['creative_id' => (string) $creativeId],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CAMPAIGN
    |--------------------------------------------------------------------------
    */

    public function createCampaign(string $accountId,array $data):array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [

            'name'=>$data['name'],

            'objective'=>$data['objective'],

            'status'=>$data['status'] ?? 'PAUSED',

            'special_ad_categories'=>json_encode(['NONE']),

            'is_adset_budget_sharing_enabled'=>false
        ];

        Log::info('META_CAMPAIGN_PAYLOAD',$payload);

        return $this->post("{$accountId}/campaigns",$payload);
    }

    public function updateCampaign(string $campaignId,array $data):array
    {
        return $this->post($campaignId,$data);
    }

    public function deleteCampaign(string $campaignId):array
    {
        return $this->delete($campaignId);
    }

    /*
    |--------------------------------------------------------------------------
    | TARGETING BUILDER
    |--------------------------------------------------------------------------
    */

protected function buildTargeting(array $targeting): array
{
    unset($targeting['locales']);

    if (! empty($targeting['geo_locations'])) {
        $targeting['geo_locations'] = $this->normalizeGeoLocationsForApi(
            $targeting['geo_locations']
        );
    }

    if (! empty($targeting['flexible_spec'])) {
        $targeting = $this->sanitizeFlexibleSpec($targeting);
    }

    if (! empty($targeting['publisher_platforms'])) {
        $targeting = $this->enrichPlacementTargeting($targeting);
    }

    $targeting = $this->applyTargetingAutomation($targeting);

    Log::info('META_TARGETING_FINAL', $targeting);

    return $targeting;
}

    /**
     * Meta requires targeting_automation.advantage_audience (0 or 1) on all ad sets.
     */
    protected function applyTargetingAutomation(array $targeting): array
    {
        if (isset($targeting['targeting_automation']['advantage_audience'])) {
            $targeting['targeting_automation']['advantage_audience'] =
                (int) $targeting['targeting_automation']['advantage_audience'] === 1 ? 1 : 0;

            return $targeting;
        }

        $configured = config('services.meta.advantage_audience');

        if ($configured !== null && $configured !== '') {
            $advantageAudience = (int) $configured === 1 ? 1 : 0;
        } else {
            $hasManualAudience = ! empty($targeting['flexible_spec'])
                || ! empty($targeting['publisher_platforms'])
                || ! empty($targeting['exclusions']);

            $advantageAudience = $hasManualAudience ? 0 : 1;
        }

        $targeting['targeting_automation'] = [
            'advantage_audience' => $advantageAudience,
        ];

        return $targeting;
    }

    protected function isAdvantageAudienceError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '1870227')
            || str_contains($message, 'advantage_audience')
            || str_contains($message, 'Advantage audience flag required');
    }

    /**
     * Build geo_locations from country codes and optional city/region entries.
     * Countries with selected cities or regions are targeted at that level only.
     *
     * @param  array<int, string>  $selectedCountries
     * @param  array<int, array<string, mixed>>  $selectedCities
     * @param  array<int, array<string, mixed>>  $selectedRegions
     * @return array<string, mixed>
     */
    public function buildGeoLocations(array $selectedCountries, array $selectedCities = [], array $selectedRegions = []): array
    {
        $countries = array_values(array_unique(array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            $selectedCountries
        )));

        if ($countries === [] && $selectedCities === [] && $selectedRegions === []) {
            throw new Exception('At least one country, region, or city is required.');
        }

        $citiesByCountry = [];
        $regionsByCountry = [];

        foreach ($selectedCities as $city) {
            if (! is_array($city)) {
                continue;
            }

            $key = trim((string) ($city['key'] ?? ''));
            $country = strtoupper(trim((string) ($city['country'] ?? $city['country_code'] ?? '')));

            if ($key === '' || $country === '') {
                continue;
            }

            $entry = ['key' => $key];

            if (! empty($city['name'])) {
                $entry['name'] = (string) $city['name'];
            }

            if (! empty($city['region'])) {
                $entry['region'] = (string) $city['region'];
            }

            if (! empty($city['region_id'])) {
                $entry['region_id'] = (int) $city['region_id'];
            }

            $entry['country'] = $country;
            $citiesByCountry[$country][] = $entry;
        }

        foreach ($selectedRegions as $region) {
            if (! is_array($region)) {
                continue;
            }

            $key = trim((string) ($region['key'] ?? ''));
            $country = strtoupper(trim((string) ($region['country'] ?? $region['country_code'] ?? '')));

            if ($key === '' || $country === '') {
                continue;
            }

            $entry = ['key' => $key];
            if (! empty($region['name'])) {
                $entry['name'] = (string) $region['name'];
            }
            $entry['country'] = $country;
            $regionsByCountry[$country][] = $entry;
        }

        // Include countries implied by city/region picks
        foreach (array_keys($citiesByCountry + $regionsByCountry) as $country) {
            if (! in_array($country, $countries, true)) {
                $countries[] = $country;
            }
        }

        if ($countries === []) {
            throw new Exception('At least one country is required.');
        }

        $geo = [
            'countries' => [],
            'cities' => [],
            'regions' => [],
        ];

        foreach ($countries as $country) {
            $hasCities = ! empty($citiesByCountry[$country]);
            $hasRegions = ! empty($regionsByCountry[$country]);

            if ($hasCities) {
                $geo['cities'] = array_merge($geo['cities'], $citiesByCountry[$country]);
            }
            if ($hasRegions) {
                $geo['regions'] = array_merge($geo['regions'], $regionsByCountry[$country]);
            }
            if (! $hasCities && ! $hasRegions) {
                $geo['countries'][] = $country;
            }
        }

        if ($geo['countries'] === []) {
            unset($geo['countries']);
        }

        if ($geo['cities'] === []) {
            unset($geo['cities']);
        }

        if ($geo['regions'] === []) {
            unset($geo['regions']);
        }

        if (! isset($geo['countries']) && ! isset($geo['cities']) && ! isset($geo['regions'])) {
            throw new Exception('At least one valid country, region, or city is required.');
        }

        return $geo;
    }

    /**
     * Meta only needs city keys in API payloads; strip extra metadata safely.
     */
    protected function normalizeGeoLocationsForApi(array $geoLocations): array
    {
        if (! empty($geoLocations['countries']) && is_array($geoLocations['countries'])) {
            $geoLocations['countries'] = array_values(array_unique(array_map(
                fn ($code) => strtoupper(trim((string) $code)),
                $geoLocations['countries']
            )));
        }

        if (! empty($geoLocations['cities']) && is_array($geoLocations['cities'])) {
            $geoLocations['cities'] = array_values(array_map(function ($city) {
                $key = is_array($city)
                    ? trim((string) ($city['key'] ?? ''))
                    : trim((string) $city);

                if ($key === '') {
                    return null;
                }

                return ['key' => $key];
            }, $geoLocations['cities']));

            $geoLocations['cities'] = array_values(array_filter($geoLocations['cities']));

            if ($geoLocations['cities'] === []) {
                unset($geoLocations['cities']);
            }
        }

        if (! empty($geoLocations['regions']) && is_array($geoLocations['regions'])) {
            $geoLocations['regions'] = array_values(array_map(function ($region) {
                $key = is_array($region)
                    ? trim((string) ($region['key'] ?? ''))
                    : trim((string) $region);

                if ($key === '') {
                    return null;
                }

                return ['key' => $key];
            }, $geoLocations['regions']));

            $geoLocations['regions'] = array_values(array_filter($geoLocations['regions']));

            if ($geoLocations['regions'] === []) {
                unset($geoLocations['regions']);
            }
        }

        return $geoLocations;
    }

    /**
     * Remove deprecated or invalid interest IDs (Meta subcode 2446394/2446395).
     */
    protected function sanitizeFlexibleSpec(array $targeting): array
    {
        if (empty($targeting['flexible_spec']) || ! is_array($targeting['flexible_spec'])) {
            unset($targeting['flexible_spec']);
            return $targeting;
        }

        $interestIds = [];

        foreach ($targeting['flexible_spec'] as $spec) {
            foreach ($spec['interests'] ?? [] as $interest) {
                $id = trim((string) ($interest['id'] ?? ''));
                if ($id !== '') {
                    $interestIds[] = $id;
                }
            }
        }

        $interestIds = array_values(array_unique($interestIds));

        if ($interestIds === []) {
            unset($targeting['flexible_spec']);
            return $targeting;
        }

        $targeting['flexible_spec'] = [[
            'interests' => array_map(
                fn ($id) => ['id' => (string) $id],
                $interestIds
            ),
        ]];

        return $targeting;
    }

    protected function isDetailedTargetingError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '2446394')
            || str_contains($message, '2446395')
            || str_contains($message, '1870247')
            || str_contains($message, '1487694')
            || str_contains($message, 'deprecated_interest')
            || str_contains($message, 'detailed targeting')
            || str_contains($message, 'no longer available')
            || str_contains($message, 'alternative options');
    }

    /**
     * Parse Meta's deprecated-interest alternatives from an API error message.
     *
     * @return array<string, string> Map of deprecated_interest_id => alternative_interest_id
     */
    protected function extractInterestAlternatives(string $message): array
    {
        $replacements = [];

        if (preg_match('/Relevant alternative options:\s*(\[.*\])\s*(?:\(|$)/s', $message, $matches)) {
            $options = json_decode($matches[1], true);
        } elseif (preg_match('/(\[{"deprecated_interest_id".*?\}])/s', $message, $matches)) {
            $options = json_decode($matches[1], true);
        } else {
            $options = null;
        }

        if (! is_array($options)) {
            return $replacements;
        }

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $deprecated = trim((string) ($option['deprecated_interest_id'] ?? ''));
            $alternative = trim((string) ($option['alternative_interest_id'] ?? ''));

            if ($deprecated !== '' && $alternative !== '') {
                $replacements[$deprecated] = $alternative;
            }
        }

        return $replacements;
    }

    /**
     * Swap deprecated interest IDs for Meta-suggested alternatives.
     */
    public function applyInterestReplacements(array $targeting, array $replacements): ?array
    {
        if ($replacements === [] || empty($targeting['flexible_spec'])) {
            return null;
        }

        $changed = false;

        foreach ($targeting['flexible_spec'] as $specIndex => $spec) {
            foreach ($spec['interests'] ?? [] as $interestIndex => $interest) {
                $id = trim((string) ($interest['id'] ?? ''));

                if ($id === '' || ! isset($replacements[$id])) {
                    continue;
                }

                $targeting['flexible_spec'][$specIndex]['interests'][$interestIndex]['id']
                    = (string) $replacements[$id];
                $changed = true;
            }
        }

        return $changed ? $targeting : null;
    }

    protected function stripInterestTargeting(array $targeting): array
    {
        unset($targeting['flexible_spec']);

        return $targeting;
    }

    /**
     * @return string[] Valid Meta interest IDs
     */
    public function validateInterestIds(array $interestIds): array
    {
        $interestIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $interestIds
        ))));

        if ($interestIds === []) {
            return [];
        }

        $this->ensureConfigured();

        try {
            $response = $this->client(forSearch: true)->get("{$this->baseUrl}/search", [
                'type' => 'adinterestvalid',
                'interest_list' => json_encode($interestIds),
                'access_token' => $this->accessToken,
            ]);

            if ($response->failed()) {
                Log::warning('META_INTEREST_VALIDATION_FAILED', [
                    'interest_ids' => $interestIds,
                    'response' => $response->body(),
                ]);

                return $interestIds;
            }

            $result = $response->json();
            $valid = [];

            foreach ($result['data'] ?? [] as $item) {
                if (($item['valid'] ?? false) && ! empty($item['id'])) {
                    $valid[] = (string) $item['id'];
                }
            }

            return $valid;
        } catch (Throwable $e) {
            Log::warning('META_INTEREST_VALIDATION_ERROR', [
                'interest_ids' => $interestIds,
                'error' => $e->getMessage(),
            ]);

            return $interestIds;
        }
    }

    /**
     * Search cities, regions, or countries via Meta Targeting Search.
     */
    public function searchGeoLocations(
        string $query,
        string $locationType = 'city',
        ?string $countryCode = null
    ): array {
        $query = trim($query);

        if (strlen($query) < 2) {
            return [];
        }

        $this->ensureConfigured();

        $allowedTypes = ['city', 'country', 'region', 'zip'];
        if (! in_array($locationType, $allowedTypes, true)) {
            $locationType = 'city';
        }

        $params = [
            'type' => 'adgeolocation',
            'location_types' => json_encode([$locationType]),
            'q' => $query,
            'limit' => 25,
            'access_token' => $this->accessToken,
        ];

        if ($countryCode) {
            $params['country_code'] = strtoupper(trim($countryCode));
        }

        $response = $this->client(forSearch: true)->get("{$this->baseUrl}/search", $params);

        if ($response->failed()) {
            Log::warning('META_GEO_SEARCH_FAILED', [
                'query' => $query,
                'location_type' => $locationType,
                'country_code' => $countryCode,
                'response' => $response->body(),
            ]);

            return [];
        }

        $items = $response->json()['data'] ?? [];

        return collect($items)->map(function ($item) {
            return [
                'key' => (string) ($item['key'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
                'country_code' => strtoupper((string) ($item['country_code'] ?? $item['country'] ?? '')),
                'country_name' => (string) ($item['country_name'] ?? ''),
                'region' => (string) ($item['region'] ?? ''),
                'region_id' => isset($item['region_id']) ? (int) $item['region_id'] : null,
                'supports_city' => (bool) ($item['supports_city'] ?? true),
            ];
        })->filter(fn ($item) => $item['key'] !== '' && $item['name'] !== '')->values()->all();
    }

    /**
     * Auto-suggest cities for selected countries via Meta Targeting Search + seed names.
     *
     * @param  array<int, string>  $countryCodes
     * @return array<int, array<string, mixed>>
     */
    public function suggestCitiesForCountries(array $countryCodes, string $locationType = 'city'): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            $countryCodes
        ))));

        if ($codes === []) {
            return [];
        }

        $seeds = config('meta.major_cities', []);
        $countryNames = config('meta.countries', []);
        $seen = [];
        $results = [];

        foreach ($codes as $code) {
            $queries = $seeds[$code] ?? [];
            if ($queries === []) {
                $name = (string) ($countryNames[$code] ?? '');
                if ($name !== '') {
                    $queries = [explode(' ', $name)[0], $name];
                }
                // Letter probes so Meta returns cities even without a curated list
                $queries = array_merge($queries, ['ka', 'ki', 'mu', 'na', 'to', 'la', 'sa', 'ma']);
            }

            $queries = array_values(array_unique(array_filter(array_map(
                fn ($q) => trim((string) $q),
                $queries
            ), fn ($q) => strlen($q) >= 2)));

            foreach (array_slice($queries, 0, 12) as $query) {
                foreach ($this->searchGeoLocations($query, $locationType, $code) as $hit) {
                    $key = (string) ($hit['key'] ?? '');
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $hitCountry = strtoupper((string) ($hit['country_code'] ?? ''));
                    if ($hitCountry !== '' && $hitCountry !== $code) {
                        continue;
                    }
                    $seen[$key] = true;
                    $hit['country_code'] = $hitCountry !== '' ? $hitCountry : $code;
                    $results[] = $hit;
                }
            }
        }

        usort($results, fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

        return array_slice($results, 0, 60);
    }

    protected function resolveDestinationType(string $optimizationGoal): ?string
    {
        return match (strtoupper($optimizationGoal)) {
            'LEAD_GENERATION', 'QUALITY_LEAD' => 'ON_AD',
            'CONVERSATIONS', 'REPLIES' => 'WHATSAPP',
            default => null,
        };
    }

    /**
     * Normalize placement positions to Meta Marketing API enums.
     * @see https://developers.facebook.com/docs/marketing-api/audiences/reference/placement-targeting/
     */
    protected function enrichPlacementTargeting(array $targeting): array
    {
        if (! empty($targeting['publisher_platforms']) && is_array($targeting['publisher_platforms'])) {
            $platforms = $targeting['publisher_platforms'];

            if (in_array('facebook', $platforms, true)) {
                $targeting['facebook_positions'] = $targeting['facebook_positions'] ?? [
                    'feed',
                    'story',
                    'facebook_reels',
                ];
            }

            if (in_array('instagram', $platforms, true)) {
                $targeting['instagram_positions'] = $targeting['instagram_positions'] ?? [
                    'stream',
                    'story',
                    'reels',
                ];
            }

            if (in_array('messenger', $platforms, true)) {
                $targeting['messenger_positions'] = $targeting['messenger_positions'] ?? [
                    'messenger_home',
                    'story',
                ];
            }

            if (in_array('audience_network', $platforms, true)) {
                $targeting['audience_network_positions'] = $targeting['audience_network_positions'] ?? [
                    'classic',
                    'instream_video',
                ];
            }
        }

        // Facebook Reels enum is facebook_reels — never bare "reels" on facebook_positions
        if (! empty($targeting['facebook_positions']) && is_array($targeting['facebook_positions'])) {
            $targeting['facebook_positions'] = array_values(array_unique(array_map(
                fn ($p) => $p === 'reels' ? 'facebook_reels' : (string) $p,
                $targeting['facebook_positions']
            )));
        }

        // Instagram Reels enum is reels — never facebook_reels on instagram_positions
        if (! empty($targeting['instagram_positions']) && is_array($targeting['instagram_positions'])) {
            $targeting['instagram_positions'] = array_values(array_unique(array_map(
                fn ($p) => $p === 'facebook_reels' ? 'reels' : (string) $p,
                $targeting['instagram_positions']
            )));
        }

        if (empty($targeting['device_platforms'])) {
            $targeting['device_platforms'] = ['mobile', 'desktop'];
        }

        return $targeting;
    }

    /**
     * Apply placement position defaults (for local targeting JSON or API payloads).
     */
    public function enrichPlacementsForTargeting(array $targeting): array
    {
        if (empty($targeting['publisher_platforms'])) {
            return $targeting;
        }

        return $this->enrichPlacementTargeting($targeting);
    }

    /**
     * @param  array<string, mixed>  $targeting
     * @return array<string, mixed>
     */
    public function targetingWithFacebookAndInstagram(array $targeting): array
    {
        return $this->applyFacebookInstagramPlacements($targeting);
    }

   /*
|--------------------------------------------------------------------------
| ADSET
|--------------------------------------------------------------------------
*/

public function createAdSet(string $accountId, array $data): array
{
    $accountId = $this->formatAccount($accountId);

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (empty($data['campaign_id'])) {
        throw new Exception('campaign_id required');
    }

    if (empty($data['name'])) {
        throw new Exception('AdSet name required');
    }

    if (empty($data['targeting'])) {
        throw new Exception('targeting required');
    }

    if (empty($data['daily_budget'])) {
        throw new Exception('daily_budget required');
    }

    /*
    |--------------------------------------------------------------------------
    | TARGETING SAFETY
    |--------------------------------------------------------------------------
    | Targeting may arrive as array OR JSON string
    */

    $targeting = $data['targeting'];

    if (is_string($targeting)) {

        $decoded = json_decode($targeting, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid targeting JSON');
        }

        $targeting = $decoded;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD TARGETING
    |--------------------------------------------------------------------------
    */

    $targeting = $this->buildTargeting($targeting);

/*
|--------------------------------------------------------------------------
| Safety: ensure array
|--------------------------------------------------------------------------
*/

if (!is_array($targeting)) {
    throw new Exception('Invalid targeting structure');
}

    /*
    |--------------------------------------------------------------------------
    | PAYLOAD
    |--------------------------------------------------------------------------
    */

    $payload = [

        'name' => $data['name'],

        'campaign_id' => $data['campaign_id'],

        'daily_budget' => (int) $data['daily_budget'],

        'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',

        'optimization_goal' => $data['optimization_goal'] ?? 'LINK_CLICKS',

        'bid_strategy' => $data['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP',

        'status' => $data['status'] ?? 'PAUSED',

        'start_time' => $data['start_time'] ?? now()->timestamp,

        'targeting' => json_encode($targeting)
    ];

    /*
    |--------------------------------------------------------------------------
    | PROMOTED OBJECT
    |--------------------------------------------------------------------------
    */

    if (!empty($data['promoted_object'])) {

        $payload['promoted_object'] = is_array($data['promoted_object'])
            ? json_encode($data['promoted_object'])
            : $data['promoted_object'];
    }

    $destinationType = $data['destination_type']
        ?? $this->resolveDestinationType((string) ($payload['optimization_goal'] ?? ''));

    if ($destinationType) {
        $payload['destination_type'] = $destinationType;
    }

    /*
    |--------------------------------------------------------------------------
    | DEBUG LOG
    |--------------------------------------------------------------------------
    */

    Log::info('META_ADSET_PAYLOAD', [
        'endpoint' => "{$accountId}/adsets",
        'payload' => $payload
    ]);

    $optimizationGoal = strtoupper((string) ($payload['optimization_goal'] ?? ''));

    if (in_array($optimizationGoal, ['LEAD_GENERATION', 'QUALITY_LEAD'], true)) {
        $pageId = null;
        $promotedObject = $data['promoted_object'] ?? null;

        if (is_array($promotedObject)) {
            $pageId = $promotedObject['page_id'] ?? null;
        }

        if ($pageId) {
            $tosStatus = $this->getPageLeadgenTosStatus((string) $pageId);

            if (! $tosStatus['accepted']) {
                throw new Exception($this->formatLeadgenTosError(
                    (string) $pageId,
                    $tosStatus['page_name'] ?? null
                ));
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API REQUEST (auto-retry deprecated interests & targeting fallbacks)
    |--------------------------------------------------------------------------
    */

    $interestReplacements = [];
    $interestsRemoved = false;
    $lastException = null;
    $maxAttempts = 5;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $response = $this->post("{$accountId}/adsets", $payload, true);

            Log::info('META_ADSET_CREATED', [
                'response' => $response,
                'attempt' => $attempt,
            ]);

            if ($interestReplacements !== []) {
                $response['_meta_interest_replacements'] = $interestReplacements;
            }

            if ($interestsRemoved) {
                $response['_meta_interests_removed'] = true;
            }

            return $response;
        } catch (Exception $e) {
            $lastException = $e;

            if ($this->isLeadgenTosError($e)) {
                throw $this->enrichLeadgenTosError($e, $payload);
            }

            if ($this->isAdvantageAudienceError($e)) {
                $targeting = $this->applyTargetingAutomation($targeting);
                $payload['targeting'] = json_encode($targeting);

                Log::warning('META_ADSET_RETRY_WITH_ADVANTAGE_AUDIENCE', [
                    'attempt' => $attempt,
                    'targeting_automation' => $targeting['targeting_automation'] ?? null,
                ]);

                continue;
            }

            $message = $e->getMessage();

            $alternatives = $this->extractInterestAlternatives($message);
            $updatedTargeting = $this->applyInterestReplacements($targeting, $alternatives);

            if ($updatedTargeting !== null) {
                foreach ($alternatives as $deprecatedId => $alternativeId) {
                    $interestReplacements[$deprecatedId] = $alternativeId;
                }

                $targeting = $updatedTargeting;
                $payload['targeting'] = json_encode($targeting);

                Log::warning('META_ADSET_RETRY_WITH_INTEREST_ALTERNATIVES', [
                    'attempt' => $attempt,
                    'replacements' => $alternatives,
                ]);

                continue;
            }

            if (! empty($targeting['flexible_spec']) && $this->isDetailedTargetingError($e)) {
                $targeting = $this->stripInterestTargeting($targeting);
                $payload['targeting'] = json_encode($targeting);
                $interestsRemoved = true;

                Log::warning('META_ADSET_RETRY_WITHOUT_INTERESTS', [
                    'attempt' => $attempt,
                    'reason' => $message,
                ]);

                continue;
            }

            throw $e;
        }
    }

    throw $lastException ?? new Exception('Meta ad set creation failed after retries.');
}
    public function deleteAdSet(string $adsetId):array
    {
        return $this->delete($adsetId);
    }

    /*
    |--------------------------------------------------------------------------
    | IMAGE UPLOAD
    |--------------------------------------------------------------------------
    */

    public function uploadImage(string $accountId,string $filePath):array
    {
        $this->ensureConfigured();

        $accountId = $this->formatAccount($accountId);

        Log::info('META_UPLOAD_IMAGE',[
            'account'=>$accountId,
            'file'=>$filePath
        ]);

        $timeout = (int) config('services.meta.http_timeout', 90);
        $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

        $response = Http::timeout(max(60, $timeout))
            ->connectTimeout($connectTimeout)
            ->attach(
                'filename',
                file_get_contents($filePath),
                basename($filePath)
            )
            ->post("{$this->baseUrl}/{$accountId}/adimages", [
                'access_token' => $this->accessToken,
            ]);

        if($response->failed()){
            $this->handleError($response,'uploadImage');
        }

        return $response->json();
    }

    public function getAdImagesByHashes(string $accountId, array $hashes): array
    {
        $hashes = array_values(array_filter(array_unique($hashes)));

        if ($hashes === []) {
            return [];
        }

        $this->ensureConfigured();

        $accountId = $this->formatAccount($accountId);

        $response = $this->get("{$accountId}/adimages", [
            'hashes' => json_encode($hashes),
            'fields' => 'hash,url',
        ]);

        $map = [];

        foreach ($response['data'] ?? [] as $image) {
            $hash = $image['hash'] ?? null;

            if (!$hash) {
                continue;
            }

            $map[$hash] = $image['url'] ?? null;
        }

        return $map;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATIVE
    |--------------------------------------------------------------------------
    */

    public function createCreative(string $accountId,array $data):array
    {
        $this->ensureConfigured();

        $accountId = $this->formatAccount($accountId);

        if (empty($data['object_story_spec']) || ! is_array($data['object_story_spec'])) {
            throw new Exception('object_story_spec is required for Meta creatives.');
        }

        $objectStorySpec = array_filter($data['object_story_spec']);

        if (empty($objectStorySpec['page_id'])) {
            throw new Exception('object_story_spec.page_id is required.');
        }

        $payload = [
            'name' => $data['name'],
            'object_story_spec' => json_encode($objectStorySpec),
        ];

        if (! empty($data['asset_feed_spec']) && is_array($data['asset_feed_spec'])) {
            $payload['asset_feed_spec'] = json_encode($data['asset_feed_spec']);
        }

        Log::info('META_CREATIVE_PAYLOAD',$payload);

        return $this->post("{$accountId}/adcreatives", $payload, true);
    }

  

 /*
|--------------------------------------------------------------------------
| CREATE AD
|--------------------------------------------------------------------------
*/

public function createAd(string $accountId, array $data): array
{
    $this->ensureConfigured();

    $accountId = $this->formatAccount($accountId);

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (empty($data['name'])) {
        throw new Exception('Ad name is required');
    }

    if (empty($data['adset_id'])) {
        throw new Exception('adset_id is required');
    }

    if (empty($data['creative']['id'])) {
        throw new Exception('creative id is required');
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD PAYLOAD
    |--------------------------------------------------------------------------
    | Meta requires the creative field to be JSON encoded
    | and it must contain creative_id
    */

    $payload = [

        'name' => $data['name'],

        'adset_id' => $data['adset_id'],

        'status' => $data['status'] ?? 'PAUSED',

        'creative' => json_encode([
            'creative_id' => $data['creative']['id']
        ])
    ];

    /*
    |--------------------------------------------------------------------------
    | DEBUG LOG
    |--------------------------------------------------------------------------
    */

    Log::info('META_AD_CREATE_PAYLOAD', [

        'endpoint' => "{$accountId}/ads",

        'payload' => $payload

    ]);

    /*
    |--------------------------------------------------------------------------
    | SEND REQUEST
    |--------------------------------------------------------------------------
    */

    $response = $this->post("{$accountId}/ads", $payload, true);

    /*
    |--------------------------------------------------------------------------
    | RESPONSE LOG
    |--------------------------------------------------------------------------
    */

    Log::info('META_AD_CREATE_RESPONSE', $response);

    return $response;
}

/*
|--------------------------------------------------------------------------
| UPDATE AD
|--------------------------------------------------------------------------
*/

public function updateAd(string $adId, array $data): array
{
    Log::info('META_AD_UPDATE_PAYLOAD', [
        'ad_id' => $adId,
        'payload' => $data
    ]);

    return $this->post($adId, $data);
}


/*
|--------------------------------------------------------------------------
| DELETE AD
|--------------------------------------------------------------------------
*/

public function deleteAd(string $adId): array
{
    Log::info('META_AD_DELETE', [
        'ad_id' => $adId
    ]);

    return $this->delete($adId);
}
public function getCampaigns(string $accountId): array
{
    $accountId = $this->formatAccount($accountId);

    return $this->get("{$accountId}/campaigns", [
        'fields' => 'id,name,status,effective_status,objective,configured_status',
        'limit' => 200,
        'filtering' => json_encode([
            [
                'field' => 'effective_status',
                'operator' => 'IN',
                'value' => [
                    'ACTIVE',
                    'PAUSED',
                    'DELETED',
                    'PENDING_REVIEW',
                    'DISAPPROVED',
                    'PREAPPROVED',
                    'PENDING_BILLING_INFO',
                    'CAMPAIGN_PAUSED',
                    'ARCHIVED',
                    'ADSET_PAUSED',
                    'IN_PROCESS',
                    'WITH_ISSUES',
                ],
            ],
        ]),
    ]);
}


/*
|--------------------------------------------------------------------------
| GET ADSETS
|--------------------------------------------------------------------------
*/

public function getAdSets(string $accountId): array
{
    $accountId = $this->formatAccount($accountId);

    return $this->get("{$accountId}/adsets", [
        'fields' => 'id,name,campaign_id,status,effective_status,daily_budget',
        'limit' => 200,
        'filtering' => json_encode([
            [
                'field' => 'effective_status',
                'operator' => 'IN',
                'value' => [
                    'ACTIVE',
                    'PAUSED',
                    'CAMPAIGN_PAUSED',
                    'PENDING_REVIEW',
                    'DISAPPROVED',
                    'PREAPPROVED',
                    'PENDING_BILLING_INFO',
                    'WITH_ISSUES',
                    'IN_PROCESS',
                ],
            ],
        ]),
    ]);
}

/**
 * List ads under a campaign or ad set (used to detect empty orphan objects).
 */
public function getChildAds(string $parentId, int $limit = 1): array
{
    return $this->get("{$parentId}/ads", [
        'fields' => 'id,status,effective_status',
        'limit' => max(1, $limit),
    ]);
}


/*
|--------------------------------------------------------------------------
| GET ADS
|--------------------------------------------------------------------------
*/
public function getAds(?string $accountId = null): array
{
    $accountId = $accountId
        ? $this->formatAccount($accountId)
        : $this->defaultAccount;

    return $this->get("{$accountId}/ads", [

        'fields' => implode(',', [

            'id',
            'name',
            'status',
            'effective_status',
            'adset_id',

            'creative{id,name}',

            'ad_review_feedback'

        ])

    ]);
}

/*
|--------------------------------------------------------------------------
| GET SINGLE AD
|--------------------------------------------------------------------------
*/

public function getAd(string $adId): array
{
    return $this->get($adId, [
        'fields' => 'id,name,status,effective_status,adset_id,campaign_id'
    ]);
}
/*
|--------------------------------------------------------------------------
| GET INSIGHTS
|--------------------------------------------------------------------------
*/
public function getInsights(string $objectId, string $preset = 'lifetime', array $extra = []): array
{
    /*
    |--------------------------------------------------------------------------
    | Default Fields For Monitoring Dashboard
    |--------------------------------------------------------------------------
    */

    $fields = implode(',', [

        'impressions',
        'clicks',
        'spend',
        'reach',

        'ctr',
        'cpm',
        'cpc',

        'frequency',
        'inline_link_clicks',

        'actions',
        'action_values',

        'video_p25_watched_actions',
        'video_p50_watched_actions',
        'video_p75_watched_actions',
        'video_p100_watched_actions',

        'date_start',
        'date_stop'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Query Parameters
    |--------------------------------------------------------------------------
    */

    $params = array_merge([
        'fields' => $fields,
        'date_preset' => $preset,
        'limit' => 1
    ], $extra);

    Log::info('META_INSIGHTS_REQUEST', [
        'object_id' => $objectId,
        'preset' => $preset,
        'params' => $params
    ]);

    /*
    |--------------------------------------------------------------------------
    | Call Meta API
    |--------------------------------------------------------------------------
    */

    $response = $this->get("{$objectId}/insights", $params);

    /*
    |--------------------------------------------------------------------------
    | If breakdown requested → return raw rows (for audience/device tables)
    |--------------------------------------------------------------------------
    */

    if (isset($extra['breakdowns'])) {
        return $response['data'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Normal Dashboard Metrics
    |--------------------------------------------------------------------------
    */

    $data = $response['data'][0] ?? [];

    return [

        'impressions' => (int)($data['impressions'] ?? 0),

        'clicks' => (int)($data['clicks'] ?? 0),

        'spend' => (float)($data['spend'] ?? 0),

        'reach' => (int)($data['reach'] ?? 0),

        'ctr' => (float)($data['ctr'] ?? 0),

        'cpm' => (float)($data['cpm'] ?? 0),

        'cpc' => (float)($data['cpc'] ?? 0),

        'frequency' => (float)($data['frequency'] ?? 0),

        'inline_link_clicks' => (int)($data['inline_link_clicks'] ?? 0),

        'actions' => $data['actions'] ?? [],

        'action_values' => $data['action_values'] ?? [],

        'video_25' => $data['video_p25_watched_actions'] ?? [],
        'video_50' => $data['video_p50_watched_actions'] ?? [],
        'video_75' => $data['video_p75_watched_actions'] ?? [],
        'video_100' => $data['video_p100_watched_actions'] ?? [],

        'date_start' => $data['date_start'] ?? null,
        'date_stop' => $data['date_stop'] ?? null,

        'raw' => $response
    ];
}
/*
|--------------------------------------------------------------------------
| GET CREATIVE
|--------------------------------------------------------------------------
| Fetch minimal Creative info from Meta
*/

public function getCreative(string $creativeId): array
{
    return $this->get($creativeId, [

        'fields' => implode(',', [

            'id',
            'name',
            'status'

        ])
    ]);
}
/*
|--------------------------------------------------------------------------
| GET SINGLE CAMPAIGN
|--------------------------------------------------------------------------
*/

public function getCampaign(string $campaignId): array
{
    return $this->get($campaignId, [
        'fields' => 'id,name,status,effective_status,objective,configured_status',
    ]);
}
/**
 * Get a single ad set with fields used for validation.
 */
public function getAdSet(string $adsetId): array
{
    $this->ensureConfigured();

    return $this->get($adsetId, [
        'fields' => implode(',', [
            'id',
            'name',
            'status',
            'optimization_goal',
            'billing_event',
            'promoted_object',
            'targeting',
            'destination_type',
        ]),
    ]);
}

/*
|--------------------------------------------------------------------------
| UPDATE ADSET
|--------------------------------------------------------------------------
*/

public function updateAdSet(string $adsetId, array $data): array
{
    Log::info('META_ADSET_UPDATE',[
        'adset_id'=>$adsetId,
        'payload'=>$data
    ]);

    return $this->post($adsetId,$data);
}
/*
|--------------------------------------------------------------------------
| ACCESS TOKEN
|--------------------------------------------------------------------------
*/

protected function getAccessToken(): string
{
    $this->ensureConfigured();

    return $this->accessToken;
}
public function updateCreative(string $creativeId,array $data):array
{
    return $this->post($creativeId,$data);
}
public function getCreativeInsights(string $creativeId): array
{
    return $this->get("{$creativeId}/insights", [
        'fields' => 'impressions,clicks,spend,ctr',
        'date_preset' => 'maximum'
    ]);
}
public function getBillingInfo(string $accountId)
{
    $this->ensureConfigured();

    $accountId = $this->formatAccount($accountId);

    $timeout = (int) config('services.meta.http_timeout', 90);
    $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

    $response = Http::timeout($timeout)
        ->connectTimeout($connectTimeout)
        ->get("{$this->baseUrl}/{$accountId}", [
            'fields' => implode(',', [
                'id',
                'name',
                'account_status',
                'currency',
                'timezone_name',
                'amount_spent',
                'spend_cap',
                'funding_source_details',
            ]),
            'access_token' => $this->accessToken,
        ]);

    if(!$response->successful()){
        $this->handleError($response,'billing_info');
    }

    return $response->json();
}
/*
|--------------------------------------------------------------------------
| 🔥 GET INSIGHTS BATCH (CRITICAL FOR SYNC)
|--------------------------------------------------------------------------
| Fetch all ads insights in ONE request (avoids rate limit)
*/

public function getInsightsBatch(string $accountId): array
{
    return $this->getAdInsightsMap($accountId, 'today');
}

/**
 * Fetch ad-level insights in one Meta request, keyed by Meta ad id.
 *
 * @return array<string, array<string, mixed>>
 */
public function getAdInsightsMap(?string $accountId = null, string $preset = 'maximum'): array
{
    $this->ensureConfigured();

    $accountId = $this->formatAccount($accountId ?? $this->defaultAccount);

    $response = $this->get("{$accountId}/insights", [
        'level' => 'ad',
        'fields' => implode(',', [
            'ad_id',
            'impressions',
            'clicks',
            'spend',
            'ctr',
        ]),
        'date_preset' => $preset,
        'limit' => 500,
    ]);

    $map = [];

    foreach ($response['data'] ?? [] as $row) {
        $adId = (string) ($row['ad_id'] ?? '');

        if ($adId !== '') {
            $map[$adId] = $row;
        }
    }

    return $map;
}

/**
 * Ad-level insights split by publisher_platform (facebook, instagram, …).
 *
 * @return array<string, array<string, array{impressions: int, clicks: int, spend: float}>>
 */
public function getAdPlacementInsightsMap(?string $accountId = null, string $preset = 'maximum'): array
{
    $this->ensureConfigured();

    $accountId = $this->formatAccount($accountId ?? $this->defaultAccount);

    $response = $this->get("{$accountId}/insights", [
        'level' => 'ad',
        'breakdowns' => 'publisher_platform',
        'fields' => implode(',', ['ad_id', 'impressions', 'clicks', 'spend']),
        'date_preset' => $preset,
        'limit' => 500,
    ]);

    $map = [];

    foreach ($response['data'] ?? [] as $row) {
        $adId = (string) ($row['ad_id'] ?? '');
        $platform = strtolower((string) ($row['publisher_platform'] ?? 'unknown'));

        if ($adId === '') {
            continue;
        }

        if (! isset($map[$adId])) {
            $map[$adId] = [];
        }

        $map[$adId][$platform] = [
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'spend' => (float) ($row['spend'] ?? 0),
        ];
    }

    return $map;
}

public function getAccountStatus($accountId)
{
    $this->ensureConfigured();

    $accountId = str_starts_with($accountId, 'act_')
        ? $accountId
        : "act_{$accountId}";

    $timeout = (int) config('services.meta.http_timeout', 90);
    $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

    $response = Http::timeout($timeout)
        ->connectTimeout($connectTimeout)
        ->get("{$this->baseUrl}/{$accountId}", [
            'fields' => 'account_status',
            'access_token' => $this->accessToken,
        ]);

    if (!$response->successful()) {
        throw new \Exception($response->body());
    }

    return $response->json();
}

    /*
    |--------------------------------------------------------------------------
    | CLICK-TO-WHATSAPP MARKETING
    |--------------------------------------------------------------------------
    */

    public function createWhatsAppCampaign(string $accountId, array $data): array
    {
        $data['objective'] = $data['objective'] ?? 'OUTCOME_ENGAGEMENT';

        return $this->createCampaign($accountId, $data);
    }

    /**
     * Fetch iframe HTML for an existing Meta ad preview.
     *
     * @see https://developers.facebook.com/docs/marketing-api/generatepreview
     */
    public function fetchAdPreviewHtml(string $adId, string $adFormat = 'MOBILE_FEED_STANDARD'): ?string
    {
        $this->ensureConfigured();

        try {
            $res = $this->get($adId.'/previews', [
                'ad_format' => $adFormat,
            ]);
            $body = data_get($res, 'data.0.body')
                ?? data_get($res, 'data.0.iframe')
                ?? null;

            return is_string($body) && $body !== '' ? $body : null;
        } catch (Throwable $e) {
            Log::info('META_AD_PREVIEW_SKIP', [
                'ad_id' => $adId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function createWhatsAppAdSet(string $accountId, array $data): array
    {
        $data['optimization_goal'] = $data['optimization_goal'] ?? 'CONVERSATIONS';
        $data['billing_event'] = $data['billing_event'] ?? 'IMPRESSIONS';
        $data['destination_type'] = $data['destination_type'] ?? 'WHATSAPP';

        $pageId = (string) ($data['page_id'] ?? data_get($data, 'promoted_object.page_id') ?? '');
        $phoneDigits = preg_replace('/\D+/', '', (string) (
            $data['whatsapp_phone_number']
            ?? data_get($data, 'promoted_object.whatsapp_phone_number')
            ?? ''
        )) ?: '';
        $phoneNumberId = (string) (
            $data['whats_app_business_phone_number_id']
            ?? $data['whatsapp_phone_number_id']
            ?? data_get($data, 'promoted_object.whats_app_business_phone_number_id')
            ?? ''
        );

        $promoted = is_array($data['promoted_object'] ?? null)
            ? $data['promoted_object']
            : [];

        if ($pageId !== '') {
            $promoted['page_id'] = $pageId;
        }
        if ($phoneDigits !== '') {
            $promoted['whatsapp_phone_number'] = $phoneDigits;
        }
        if ($phoneNumberId !== '' && ctype_digit($phoneNumberId) && ! str_starts_with($phoneNumberId, 'display:')) {
            $promoted['whats_app_business_phone_number_id'] = $phoneNumberId;
        }

        $destinationType = strtoupper((string) (
            $data['destination_type']
            ?? 'WHATSAPP'
        ));

        if (
            ($destinationType === 'WHATSAPP' || str_contains($destinationType, 'WHATSAPP'))
            && $phoneDigits === ''
            && empty($promoted['whats_app_business_phone_number_id'])
        ) {
            throw new Exception(
                'WhatsApp Business number missing for Click-to-WhatsApp — select a business phone before publishing (Meta error 2446885 if the Page only has a personal WhatsApp).'
            );
        }

        if ($promoted !== []) {
            $data['promoted_object'] = $promoted;
        }

        return $this->createAdSet($accountId, $data);
    }

    /**
     * @param  array<string, mixed>  $payload  Must include object_story_spec from ClickToWhatsAppCreativeBuilder
     */
    public function createClickToWhatsAppCreative(string $accountId, array $payload): array
    {
        try {
            return $this->createCreative($accountId, $payload);
        } catch (Throwable $first) {
            $payload = $this->stripWelcomeMessageFromCreativePayload($payload);
            $msg = strtolower($first->getMessage());
            $spec = $payload['object_story_spec'] ?? null;
            $hasIg = is_array($spec) && ! empty($spec['instagram_user_id']);
            $looksLikeIgAuth = $hasIg && (
                str_contains($msg, 'instagram')
                || str_contains($msg, 'permission')
                || str_contains($msg, '(#10)')
                || str_contains($msg, '(#200)')
                || str_contains($msg, 'does not have')
            );

            Log::warning('META_CTWA_CREATIVE_RETRY', [
                'error' => $first->getMessage(),
                'without_welcome' => true,
                'without_instagram' => $looksLikeIgAuth,
            ]);

            if ($looksLikeIgAuth) {
                unset($payload['object_story_spec']['instagram_user_id']);
            }

            try {
                return $this->createCreative($accountId, $payload);
            } catch (Throwable $second) {
                // Last resort: Facebook placements only — drop IG identity if still present
                if (is_array($payload['object_story_spec'] ?? null)
                    && ! empty($payload['object_story_spec']['instagram_user_id'])) {
                    unset($payload['object_story_spec']['instagram_user_id']);
                    Log::warning('META_CTWA_CREATIVE_RETRY_FB_ONLY', [
                        'error' => $second->getMessage(),
                    ]);

                    return $this->createCreative($accountId, $payload);
                }

                throw $second;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function stripWelcomeMessageFromCreativePayload(array $payload): array
    {
        if (isset($payload['object_story_spec']['link_data']['page_welcome_message'])) {
            unset($payload['object_story_spec']['link_data']['page_welcome_message']);
        }

        return $payload;
    }

    /**
     * Pull insights with messaging metrics when available.
     */
    public function getMarketingInsights(string $objectId, string $level = 'campaign', string $preset = 'maximum'): array
    {
        $fields = [
            'impressions',
            'reach',
            'clicks',
            'spend',
            'ctr',
            'cpc',
            'actions',
            'cost_per_action_type',
        ];

        return $this->getInsights($objectId, [
            'level' => $level,
            'fields' => implode(',', $fields),
            'date_preset' => $preset,
        ]);
    }

    public function humanizeMetaError(Throwable $e): string
    {
        $message = $e->getMessage();
        $lower = strtolower($message);

        // Interest/targeting subcodes (2446394/2446395) must be checked before any "2446" prefix match.
        if ($this->isDetailedTargetingError($e)) {
            return 'Audience targeting needs an update — remove or replace deprecated interests and try again. $5 daily budgets are fine.';
        }

        // Rate limits must win over the generic "WhatsApp … Page" mapping (#80008 contains "WhatsApp").
        if (
            str_contains($message, '80008')
            || str_contains($message, '(#4)')
            || str_contains($lower, 'request limit')
            || str_contains($lower, 'rate limit')
            || str_contains($lower, 'too many calls')
            || str_contains($lower, 'application request limit')
        ) {
            return 'Meta is rate-limiting WhatsApp/Graph calls right now. Wait 30–60 minutes, avoid Sync/Refresh, then publish again. Your draft is fine.';
        }

        if (str_contains($message, 'display:') || str_contains($lower, 'synthetic')) {
            return 'That WhatsApp number is display-only until Meta returns its real Phone Number ID. Pick a Cloud API number (green Verified) or wait for Sync after rate limits clear.';
        }

        return match (true) {
            // Real OAuth/scope failures only — never map every Meta "OAuthException" type here
            // (almost all Marketing API errors are typed OAuthException, including Invalid parameter).
            str_contains($message, '(#10)')
                || str_contains($message, '(#200)')
                || str_contains($lower, 'does not have permission')
                || str_contains($lower, 'permissions error')
                || str_contains($lower, 'missing permission')
                || str_contains($lower, 'permission denied') =>
                'Permission missing — reconnect Meta and grant ads_management, pages_manage_ads, and WhatsApp permissions.',
            str_contains($lower, 'access token') || str_contains($message, '(#190)') =>
                'Invalid or expired token — reconnect Meta in Admin → Meta Connection.',
            str_contains($lower, 'ad account') && str_contains($lower, 'disabled') =>
                'Ad account disabled or restricted — check Meta Business Manager account status.',
            str_contains($lower, 'instagram') && (
                str_contains($lower, 'permission')
                || str_contains($lower, 'not linked')
                || str_contains($lower, 'not connected')
                || str_contains($lower, 'invalid')
            ) =>
                'Instagram identity issue on the creative — confirm the Page’s Instagram account in Meta, or publish Facebook-only first.',
            // Only map true Page↔WhatsApp link failures (not every string that mentions WhatsApp)
            str_contains($lower, 'not connected')
                || str_contains($lower, 'page is not connected')
                || str_contains($lower, 'whatsapp business number is not linked')
                || str_contains($lower, 'link your whatsapp') =>
                'WhatsApp number not connected to Page — link WhatsApp in Meta Business Suite.',
            str_contains($message, '2446885')
                || str_contains($lower, 'personal account')
                || str_contains($lower, 'page with whatsapp business account required') =>
                'Meta still sees a personal WhatsApp on this Page. In Ad Studio pick your WhatsApp Business number (not personal), and in Meta Business Suite link that Business number to the Page. Then publish again.',
            str_contains($message, '1815433')
                || str_contains($lower, 'invalid facebook position')
                || str_contains($lower, 'invalid value reels for the placement field facebook_positions') =>
                'Invalid Facebook placement — Facebook Reels must use facebook_reels (not reels). Instagram Reels keep reels.',
            str_contains($lower, 'placement') =>
                'Placement unsupported for Click-to-WhatsApp — use Facebook/Instagram feed, stories, or reels.',
            // Real budget errors only (do NOT match 2446 — that prefixes interest subcodes 2446394/2446395)
            str_contains($message, '1885272')
                || str_contains($message, '1885650')
                || str_contains($message, '1487901')
                || str_contains($lower, 'budget is too low')
                || str_contains($lower, 'budget too low')
                || str_contains($lower, 'minimum budget') =>
                'Budget too low for this ad set setup — $5/day is normally fine for USD accounts. Try publishing again or check the ad account currency in Meta.',
            str_contains($lower, 'call_to_action')
                || str_contains($lower, 'whatsapp_message')
                || str_contains($lower, 'send_message')
                || str_contains($lower, 'app_destination') =>
                'WhatsApp creative CTA rejected — Ads Manager uses WHATSAPP_MESSAGE. Retry publish after updating Ad Studio.',
            str_contains($lower, 'creative') || str_contains($message, '1487') =>
                'Creative invalid — check image size, text length, and WhatsApp CTA configuration.',
            default => $message,
        };
    }
}