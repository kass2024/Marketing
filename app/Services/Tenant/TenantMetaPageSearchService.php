<?php

namespace App\Services\Tenant;

use App\Models\PlatformMetaConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TenantMetaPageSearchService
{
    protected string $graphUrl;

    protected string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config('platform.meta.graph_version', config('services.meta.graph_version', 'v19.0'));
        $this->graphUrl = rtrim(config('platform.meta.graph_url', config('services.meta.graph_url', 'https://graph.facebook.com')), '/');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $needle = mb_strtolower($query);
        $catalog = $this->platformPageCatalog();
        $matches = [];

        foreach ($catalog as $page) {
            $name = mb_strtolower((string) ($page['name'] ?? ''));

            if ($name === '' || ! str_contains($name, $needle)) {
                continue;
            }

            $matches[] = [
                'id' => (string) $page['id'],
                'name' => (string) $page['name'],
            ];

            if (count($matches) >= $limit) {
                return $this->rankMatches($matches, $needle);
            }
        }

        if (ctype_digit($query)) {
            foreach ($catalog as $page) {
                if ((string) $page['id'] === $query) {
                    $matches[] = [
                        'id' => (string) $page['id'],
                        'name' => (string) $page['name'],
                    ];
                    break;
                }
            }
        }

        if (count($matches) < $limit) {
            $matches = $this->mergeMatches($matches, $this->searchViaGraphApi($query, $limit), $limit);
        }

        return $this->rankMatches($matches, $needle);
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function resolvePage(string $pageId): ?array
    {
        $pageId = trim($pageId);

        if ($pageId === '') {
            return null;
        }

        foreach ($this->platformPageCatalog() as $page) {
            if ((string) ($page['id'] ?? '') === $pageId) {
                return [
                    'id' => (string) $page['id'],
                    'name' => (string) ($page['name'] ?? 'Facebook Page'),
                ];
            }
        }

        $token = $this->platformToken();

        if (! $token) {
            return null;
        }

        $response = Http::timeout(20)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$pageId}",
            [
                'access_token' => $token,
                'fields' => 'id,name',
            ]
        );

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['id'])) {
            return null;
        }

        return [
            'id' => (string) $data['id'],
            'name' => (string) ($data['name'] ?? 'Facebook Page'),
        ];
    }

    public function assertPageIsAllowed(string $pageId): void
    {
        $allowedIds = collect($this->platformPageCatalog())->pluck('id')->map(fn ($id) => (string) $id);

        if ($allowedIds->contains((string) $pageId)) {
            return;
        }

        $fallbackId = config('platform.meta.page_id') ?: config('services.meta.page_id');

        if ($fallbackId && (string) $fallbackId === (string) $pageId) {
            return;
        }

        throw ValidationException::withMessages([
            'meta_page_id' => 'This Facebook Page was not found under the platform Business Manager. Search and select your page from the suggestions.',
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string, link?: string}>
     */
    protected function searchViaGraphApi(string $query, int $limit): array
    {
        $token = $this->platformToken();

        if (! $token) {
            return [];
        }

        try {
            $response = Http::timeout(20)->get(
                "{$this->graphUrl}/{$this->graphVersion}/pages/search",
                [
                    'access_token' => $token,
                    'q' => $query,
                    'fields' => 'id,name,link',
                    'limit' => min($limit, 10),
                ]
            );

            if (! $response->ok()) {
                return [];
            }

            $allowedIds = collect($this->platformPageCatalog())->pluck('id')->map(fn ($id) => (string) $id);
            $results = [];

            foreach ($response->json('data', []) as $row) {
                if (! is_array($row) || empty($row['id'])) {
                    continue;
                }

                $id = (string) $row['id'];

                if (! $allowedIds->contains($id)) {
                    continue;
                }

                $results[] = [
                    'id' => $id,
                    'name' => (string) ($row['name'] ?? 'Facebook Page'),
                    'link' => (string) ($row['link'] ?? ''),
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('TENANT_PAGE_GRAPH_SEARCH_FAILED', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function platformPageCatalog(): array
    {
        return Cache::remember('platform_meta_page_catalog', now()->addMinutes(5), function () {
            return $this->fetchPlatformPageCatalog();
        });
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function fetchPlatformPageCatalog(): array
    {
        $token = $this->platformToken();

        if (! $token) {
            return $this->configFallbackCatalog();
        }

        $pages = [];
        $businessId = $this->businessId();

        if ($businessId) {
            $pages = array_merge(
                $pages,
                $this->fetchPagedCollection("{$businessId}/owned_pages", $token),
                $this->fetchPagedCollection("{$businessId}/client_pages", $token)
            );
        }

        $pages = array_merge($pages, $this->fetchPagedCollection('me/accounts', $token));

        $unique = [];

        foreach ($pages as $page) {
            $id = (string) ($page['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $unique[$id] = [
                'id' => $id,
                'name' => (string) ($page['name'] ?? 'Facebook Page'),
            ];
        }

        if ($unique === []) {
            return $this->configFallbackCatalog();
        }

        return array_values($unique);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function fetchPagedCollection(string $endpoint, string $token): array
    {
        $results = [];
        $url = "{$this->graphUrl}/{$this->graphVersion}/{$endpoint}";
        $params = [
            'access_token' => $token,
            'fields' => 'id,name',
            'limit' => 100,
        ];

        for ($i = 0; $i < 5; $i++) {
            $response = Http::timeout(25)->get($url, $params);

            if (! $response->ok()) {
                break;
            }

            foreach ($response->json('data', []) as $row) {
                if (is_array($row) && ! empty($row['id'])) {
                    $results[] = $row;
                }
            }

            $next = $response->json('paging.next');

            if (! $next) {
                break;
            }

            $url = $next;
            $params = [];
        }

        return $results;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function configFallbackCatalog(): array
    {
        $pageId = config('platform.meta.page_id') ?: config('services.meta.page_id');

        if (! $pageId) {
            return [];
        }

        return [[
            'id' => (string) $pageId,
            'name' => (string) (config('platform.meta.page_name') ?: config('services.meta.page_name', 'Facebook Page')),
        ]];
    }

    protected function platformToken(): ?string
    {
        $connection = PlatformMetaConnection::query()->platformDefault()->active()->first();
        $token = $connection?->plainAccessToken();

        if ($token) {
            return $token;
        }

        return config('platform.meta.system_user_token') ?: config('services.meta.token');
    }

    protected function businessId(): ?string
    {
        $id = PlatformMetaConnection::query()->platformDefault()->value('business_id');

        return $id ? (string) $id : null;
    }

    /**
     * @param  array<int, array{id: string, name: string}>  $matches
     * @param  array<int, array{id: string, name: string, link?: string}>  $extra
     * @return array<int, array{id: string, name: string}>
     */
    protected function mergeMatches(array $matches, array $extra, int $limit): array
    {
        $indexed = [];

        foreach (array_merge($matches, $extra) as $page) {
            $indexed[(string) $page['id']] = [
                'id' => (string) $page['id'],
                'name' => (string) ($page['name'] ?? 'Facebook Page'),
            ];
        }

        return array_slice(array_values($indexed), 0, $limit);
    }

    /**
     * @param  array<int, array{id: string, name: string}>  $matches
     * @return array<int, array{id: string, name: string}>
     */
    protected function rankMatches(array $matches, string $needle): array
    {
        usort($matches, function (array $a, array $b) use ($needle) {
            $aName = mb_strtolower($a['name']);
            $bName = mb_strtolower($b['name']);

            $aExact = $aName === $needle ? 0 : 1;
            $bExact = $bName === $needle ? 0 : 1;

            if ($aExact !== $bExact) {
                return $aExact <=> $bExact;
            }

            $aStarts = str_starts_with($aName, $needle) ? 0 : 1;
            $bStarts = str_starts_with($bName, $needle) ? 0 : 1;

            if ($aStarts !== $bStarts) {
                return $aStarts <=> $bStarts;
            }

            return mb_strlen($a['name']) <=> mb_strlen($b['name']);
        });

        return $matches;
    }
}
