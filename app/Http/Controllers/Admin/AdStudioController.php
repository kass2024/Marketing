<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\PlatformMetaConnection;
use App\Services\Tenant\TenantConnectionResolver;
use App\Services\Meta\AdFormatRegistry;
use App\Services\Meta\AdImageGenerator;
use App\Services\Meta\AdImageValidator;
use App\Services\Meta\AdPublishNotifier;
use App\Services\Meta\ClickToWhatsAppCreativeBuilder;
use App\Services\Meta\CreativeCopyGenerator;
use App\Services\Meta\CreativeFromImageAnalyzer;
use App\Services\Meta\CreativeTemplateRegistry;
use App\Services\Meta\MarketingPreflightValidator;
use App\Services\Meta\MarketingPublishService;
use App\Services\Meta\MetaConnectionValidator;
use App\Services\Meta\StockMediaRegistry;
use App\Services\Meta\MetaAutoSyncService;
use App\Services\MetaAdsService;
use App\Services\Meta\WhatsAppBusinessAccountService;
use App\Support\TenantScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AdStudioController extends Controller
{
    public function __construct(
        protected MetaAdsService $meta,
        protected MarketingPublishService $publisher,
        protected MarketingPreflightValidator $preflight,
        protected MetaConnectionValidator $connectionValidator,
        protected ClickToWhatsAppCreativeBuilder $creativeBuilder,
        protected CreativeCopyGenerator $copyGenerator,
        protected CreativeFromImageAnalyzer $creativeAnalyzer,
        protected AdPublishNotifier $notifier,
        protected AdImageGenerator $imageGenerator,
        protected AdImageValidator $imageValidator,
        protected MetaAutoSyncService $autoSync
    ) {}

    public function create(): View
    {
        if (config('platform.whatsapp_always_sync', true)) {
            $this->autoSync->syncAlways();
        } else {
            $this->autoSync->sync(false);
        }

        $connection = app(TenantConnectionResolver::class)->forCurrentUser();
        $connectionStatus = $this->connectionValidator->validate($connection);
        $pages = [];

        try {
            $pages = TenantScope::filterPages($this->meta->getPages());
        } catch (Throwable) {
        }

        $whatsappNumbers = $this->resolveWhatsAppNumbers($connection);

        return view('admin.marketing.create', [
            'connection' => $connection,
            'connectionStatus' => $connectionStatus,
            'pages' => $pages,
            'whatsappNumbers' => $whatsappNumbers,
            'countryOptions' => config('meta.countries', []),
            'metaAutoSynced' => true,
            'objectives' => ClickToWhatsAppCreativeBuilder::campaignObjectives(),
            'templates' => CreativeTemplateRegistry::templates(),
            'placementOptions' => CreativeTemplateRegistry::placements(),
            'stockImages' => StockMediaRegistry::images(),
            'stockByFormat' => StockMediaRegistry::byFormat(),
            'imageFormats' => AdFormatRegistry::formats(),
            'defaultImageFormat' => AdFormatRegistry::defaultKey(),
            'performanceGoals' => [
                'CONVERSATIONS' => 'Maximize number of conversations',
                'LINK_CLICKS' => 'Maximize link clicks',
                'IMPRESSIONS' => 'Maximize impressions',
            ],
        ]);
    }

    public function whatsappNumbers(): JsonResponse
    {
        $this->autoSync->syncAlways();
        $connection = app(TenantConnectionResolver::class)->forCurrentUser();

        return response()->json([
            'success' => true,
            'data' => $this->resolveWhatsAppNumbers($connection),
            'synced_from_meta' => true,
            'auto_sync' => true,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function preflight(Request $request): JsonResponse
    {
        $draft = $this->normalizePayload($request);
        $connection = app(TenantConnectionResolver::class)->forCurrentUser();
        $validation = $this->preflight->validateWizard($draft, $connection);
        $checklist = $this->preflight->checklist($draft, $connection);
        $score = $this->computeScore($checklist);

        return response()->json([
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'checklist' => $checklist,
            'score' => $score,
            'whatsapp_preview_url' => ! empty($draft['whatsapp_chat_url'] ?? $draft['whatsapp_phone_number'] ?? '')
                ? $this->creativeBuilder->resolveWhatsAppLink(
                    (string) ($draft['whatsapp_chat_url'] ?? $draft['whatsapp_phone_number'] ?? ''),
                    (string) ($draft['whatsapp_prefill_message'] ?? '')
                )
                : null,
        ]);
    }

    public function validateMedia(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:4096',
            'image_format' => 'nullable|string',
        ]);

        $result = $this->imageValidator->validateUpload(
            $request->file('image'),
            $request->input('image_format')
        );

        return response()->json($result);
    }

    public function generateImage(Request $request): JsonResponse
    {
        $input = $request->validate([
            'image_format' => 'nullable|string',
            'ai_image_prompt' => 'nullable|string|max:1000',
            'service_name' => 'nullable|string|max:255',
            'target_audience' => 'nullable|string|max:500',
            'main_benefit' => 'nullable|string|max:500',
            'headline' => 'nullable|string|max:255',
            'offer_discount' => 'nullable|string|max:255',
            'primary_text' => 'nullable|string|max:2200',
        ]);

        try {
            $result = $this->imageGenerator->generate($input);

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function generate(Request $request): JsonResponse
    {
        $input = $request->validate([
            'service_name' => 'nullable|string|max:255',
            'campaign_goal' => 'nullable|string|max:255',
            'target_audience' => 'nullable|string|max:500',
            'pain_point' => 'nullable|string|max:500',
            'main_benefit' => 'nullable|string|max:500',
            'offer_discount' => 'nullable|string|max:255',
            'template_key' => 'nullable|string|max:64',
            'whatsapp_phone_number' => 'nullable|string|max:32',
            'whatsapp_chat_url' => 'nullable|string|max:512',
            'variant' => 'nullable|string|in:A,B,C,all',
        ]);

        if (($input['variant'] ?? 'A') === 'all') {
            return response()->json([
                'variants' => $this->copyGenerator->generateAllVariants($input),
            ]);
        }

        return response()->json(
            $this->copyGenerator->generate($input, $input['variant'] ?? 'A')
        );
    }

    public function analyzeCreative(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:4096',
            'image_format' => 'nullable|string',
        ]);

        try {
            $result = $this->creativeAnalyzer->analyzeUpload(
                $request->file('image'),
                $request->input('image_format')
            );

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function publish(Request $request): RedirectResponse
    {
        $draft = $this->normalizePayload($request);
        $activate = $request->boolean('activate');

        try {
            $result = $this->publisher->publishFromWizard($draft, $activate);

            $connection = app(TenantConnectionResolver::class)->forCurrentUser();
            if ($connection) {
                $this->notifier->notifyPublished(
                    $result['campaign'],
                    $result['ad'],
                    $connection,
                    $draft
                );
            }

            $message = $activate
                ? 'Campaign published to Meta as ACTIVE — ready to deliver. Synced to Campaigns.'
                : 'Campaign published to Meta as PAUSED (synced). Click Activate · Deliver when ready.';

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', $message);
        } catch (Exception $e) {
            return redirect()
                ->route('admin.marketing.create')
                ->withInput()
                ->with('error', $this->meta->humanizeMetaError($e));
        }
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        $draft = $this->normalizePayload($request);

        Campaign::create([
            'ad_account_id' => TenantScope::requireAdAccount()->id,
            'client_id' => TenantScope::clientId(),
            'name' => $draft['name'] ?? 'Draft Campaign',
            'objective' => $draft['objective'] ?? 'OUTCOME_ENGAGEMENT',
            'marketing_channel' => 'click_to_whatsapp',
            'status' => Campaign::STATUS_DRAFT,
            'wizard_state' => $draft,
            'daily_budget' => (int) ($draft['daily_budget'] ?? 0),
        ]);

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Campaign draft saved. Return to Ad Studio to publish when ready.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizePayload(Request $request): array
    {
        $data = $request->except(['_token', 'image', 'activate']);

        if ($request->hasFile('image')) {
            $validation = $this->imageValidator->validateUpload(
                $request->file('image'),
                $request->input('image_format')
            );
            if (! $validation['valid']) {
                throw new Exception(implode(' ', $validation['errors']));
            }
            $data['image_path'] = $request->file('image')->store('marketing-wizard', 'public');
            $data['image_format'] = $validation['format'] ?? $request->input('image_format');
        } elseif ($request->filled('ai_image_path')) {
            $aiPath = (string) $request->input('ai_image_path');
            if (Storage::disk('public')->exists($aiPath)) {
                $data['image_path'] = $aiPath;
                $data['image_format'] = $request->input('image_format');
            }
        } elseif ($request->filled('stock_image_id')) {
            $absolute = StockMediaRegistry::absolutePath((string) $request->input('stock_image_id'));
            if ($absolute && File::exists($absolute)) {
                $filename = 'stock-'.basename($absolute);
                $dest = 'marketing-wizard/'.$filename;
                Storage::disk('public')->put($dest, File::get($absolute));
                $data['image_path'] = $dest;
                $stock = StockMediaRegistry::find((string) $request->input('stock_image_id'));
                $data['image_format'] = $stock['format'] ?? $request->input('image_format');
            }
        }

        if (! empty($data['countries']) && is_string($data['countries'])) {
            $data['countries'] = array_values(array_filter(array_map(
                fn ($c) => strtoupper(trim($c)),
                explode(',', $data['countries'])
            )));
        } elseif (! empty($data['countries']) && is_array($data['countries'])) {
            $data['countries'] = array_values(array_filter(array_map(
                fn ($c) => strtoupper(trim((string) $c)),
                $data['countries']
            )));
        }

        if (! empty($data['cities_json']) && is_string($data['cities_json'])) {
            $decoded = json_decode($data['cities_json'], true);
            $data['cities'] = is_array($decoded) ? $decoded : [];
            unset($data['cities_json']);
        }

        if (! empty($data['regions_json']) && is_string($data['regions_json'])) {
            $decoded = json_decode($data['regions_json'], true);
            $data['regions'] = is_array($decoded) ? $decoded : [];
            unset($data['regions_json']);
        }

        if (($data['geo_mode'] ?? '') === 'countries_only') {
            $data['cities'] = [];
            $data['regions'] = [];
        }

        if (! empty($data['daily_budget_dollars'])) {
            $data['daily_budget'] = (int) round((float) $data['daily_budget_dollars'] * 100);
        }

        if (! empty($data['placements']) && is_array($data['placements'])) {
            $data['placements'] = $this->buildPlacementsFromKeys($data['placements']);
        }

        $data['call_to_action'] = 'WHATSAPP_MESSAGE';
        $data['adset_name'] = $data['adset_name'] ?? (($data['name'] ?? 'Campaign').' — Ad Set');
        $data['ad_name'] = $data['ad_name'] ?? (($data['name'] ?? 'Campaign').' — Ad');
        $data['creative_name'] = $data['creative_name'] ?? (($data['name'] ?? 'Campaign').' — Creative');

        if (! empty($data['set_end_date']) && empty($data['end_date'])) {
            $data['end_date'] = null;
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function buildPlacementsFromKeys(array $keys): array
    {
        $options = CreativeTemplateRegistry::placements();
        $platforms = [];
        $facebook = [];
        $instagram = [];

        foreach ($keys as $key) {
            $pl = $options[$key] ?? null;
            if (! $pl) {
                continue;
            }
            $platforms[] = $pl['platform'];
            if ($pl['platform'] === 'facebook') {
                $facebook[] = $pl['position'];
            }
            if ($pl['platform'] === 'instagram') {
                $instagram[] = $pl['position'];
            }
        }

        return array_filter([
            'publisher_platforms' => array_values(array_unique($platforms)),
            'facebook_positions' => array_values(array_unique($facebook)),
            'instagram_positions' => array_values(array_unique($instagram)),
            'device_platforms' => ['mobile', 'desktop'],
        ]);
    }

    /**
     * @param  array<int, array{label: string, ok: bool}>  $checklist
     */
    protected function computeScore(array $checklist): int
    {
        if ($checklist === []) {
            return 0;
        }

        $ok = collect($checklist)->where('ok', true)->count();

        return (int) round(($ok / count($checklist)) * 100);
    }

    /**
     * @return array<int, array{id: string, label: string, phone: string, display: string, phone_number_id: string, verified: bool, waba_name?: string}>
     */
    protected function resolveWhatsAppNumbers(?PlatformMetaConnection $connection): array
    {
        $numbers = [];
        $seen = [];

        $push = function (string $id, string $label, string $digits, bool $verified = false, ?string $wabaName = null) use (&$numbers, &$seen) {
            if ($digits === '' || isset($seen[$digits])) {
                return;
            }
            $seen[$digits] = true;
            $numbers[] = [
                'id' => $id,
                'label' => $label,
                'phone' => $digits,
                'display' => $this->formatDisplayPhone($digits),
                'phone_number_id' => $id,
                'verified' => $verified,
                'waba_name' => $wabaName,
            ];
        };

        $phones = [];
        try {
            $wabaService = app(WhatsAppBusinessAccountService::class);
            // Force BM discovery before listing so all owned/client WABAs appear
            $wabaService->resolveBusinessManagerId($connection);
            $phones = $wabaService->listAllPhoneNumbers();
        } catch (Throwable $e) {
            Log::warning('AD_STUDIO_WA_LIST_FAILED', ['error' => $e->getMessage()]);
            $cacheKey = 'meta_wa_phone_directory_'.($connection?->id ?? 'platform');
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $phones = $cached;
            }
        }

        foreach ($phones as $phone) {
            if (! is_array($phone)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) ($phone['display_phone_number'] ?? '')) ?? '';
            $name = trim((string) ($phone['verified_name'] ?? ''));
            $wabaName = trim((string) ($phone['waba_name'] ?? ''));
            $label = $name !== '' ? $name : ($wabaName !== '' ? $wabaName : 'WhatsApp number');
            if ($wabaName !== '' && $name !== '' && ! str_contains(strtolower($name), strtolower($wabaName))) {
                $label = $name.' · '.$wabaName;
            }
            $verified = array_key_exists('verified', $phone)
                ? (bool) $phone['verified']
                : app(WhatsAppBusinessAccountService::class)->isPhoneVerified($phone);
            $push((string) ($phone['id'] ?? ''), $label, $digits, $verified, $wabaName !== '' ? $wabaName : null);
        }

        if ($connection?->whatsapp_phone_number) {
            $digits = preg_replace('/\D+/', '', $connection->whatsapp_phone_number) ?? '';
            $push(
                (string) ($connection->whatsapp_phone_number_id ?: 'platform'),
                $connection->page_name ?? $connection->business_name ?? 'Platform default',
                $digits,
                true
            );
        }

        return $numbers;
    }

    protected function formatDisplayPhone(string $digits): string
    {
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+1 '.substr($digits, 1, 3).'-'.substr($digits, 4, 3).'-'.substr($digits, 7);
        }

        return '+'.$digits;
    }
}
