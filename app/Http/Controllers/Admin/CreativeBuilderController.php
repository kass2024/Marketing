<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\PlatformMetaConnection;
use App\Services\Tenant\TenantConnectionResolver;
use App\Services\Meta\CampaignAdLinker;
use App\Services\Meta\ClickToWhatsAppCreativeBuilder;
use App\Services\Meta\CreativeBuilderValidator;
use App\Services\Meta\CreativeContextResolver;
use App\Services\Meta\CreativeCopyGenerator;
use App\Services\Meta\CreativeTemplateRegistry;
use App\Services\MetaAdsService;
use App\Support\TenantScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CreativeBuilderController extends Controller
{
    public function __construct(
        protected MetaAdsService $meta,
        protected ClickToWhatsAppCreativeBuilder $whatsAppBuilder,
        protected CreativeCopyGenerator $copyGenerator,
        protected CreativeBuilderValidator $validator,
        protected CreativeContextResolver $contextResolver,
        protected CampaignAdLinker $adLinker
    ) {}

    public function create(Request $request): View
    {
        $reuse = null;
        if ($request->filled('reuse')) {
            $reuse = TenantScope::creatives(Creative::query())->find($request->integer('reuse'));
        }

        $connection = app(TenantConnectionResolver::class)->forCurrentUser();
        $pages = [];

        try {
            $pages = TenantScope::filterPages($this->meta->getPages());
        } catch (Throwable) {
        }

        $selectedCampaign = null;
        $selectedAdset = null;

        $campaignId = $request->integer('campaign_id') ?: $reuse?->campaign_id;
        $adsetId = $request->integer('adset_id') ?: $reuse?->adset_id;

        if ($campaignId) {
            $selectedCampaign = TenantScope::campaigns(
                Campaign::query()->with(['adsets' => fn ($q) => $q->withCount(['ads', 'creatives'])->latest()])
            )->find($campaignId);
        }

        if ($adsetId) {
            $selectedAdset = TenantScope::adSets(AdSet::query()->with('campaign'))->find($adsetId);
            $selectedCampaign = $selectedCampaign ?? $selectedAdset?->campaign;
        }

        $context = $this->contextResolver->resolve($selectedCampaign, $selectedAdset, $connection);

        $campaigns = TenantScope::campaigns(
            Campaign::query()->withCount('adsets')->latest()
        )->get();

        return view('admin.creatives.builder', [
            'templates' => CreativeTemplateRegistry::templates(),
            'goals' => CreativeTemplateRegistry::goals(),
            'placementOptions' => CreativeTemplateRegistry::placements(),
            'campaigns' => $campaigns,
            'selectedCampaign' => $selectedCampaign,
            'selectedAdset' => $selectedAdset,
            'context' => $context,
            'pages' => $pages,
            'connection' => $connection,
            'reuse' => $reuse,
            'reusableCreatives' => TenantScope::creatives(
                Creative::query()->where('is_reusable', true)->latest()->limit(20)
            )->get(),
        ]);
    }

    public function adsetsForCampaign(Campaign $campaign): JsonResponse
    {
        TenantScope::assertCampaign($campaign);

        return response()->json([
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'objective' => $campaign->objective,
                'meta_id' => $campaign->meta_id,
                'context' => $this->contextResolver->resolve($campaign, null),
            ],
            'adsets' => $this->contextResolver->adsetsPayload($campaign),
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        $campaign = null;
        $adset = null;

        if ($request->filled('campaign_id')) {
            $campaign = TenantScope::campaigns(Campaign::query())->find($request->integer('campaign_id'));
        }

        if ($request->filled('adset_id')) {
            $adset = TenantScope::adSets(AdSet::query()->with('campaign'))->find($request->integer('adset_id'));
            $campaign = $campaign ?? $adset?->campaign;
        }

        if (! $campaign && ! $adset) {
            return response()->json(['error' => 'campaign_id or adset_id required'], 422);
        }

        return response()->json($this->contextResolver->resolve($campaign, $adset));
    }

    public function generate(Request $request): JsonResponse
    {
        $input = $request->validate([
            'template_key' => 'nullable|string',
            'service_name' => 'nullable|string|max:255',
            'campaign_goal' => 'nullable|string|max:500',
            'target_audience' => 'nullable|string|max:500',
            'pain_point' => 'nullable|string|max:1000',
            'main_benefit' => 'nullable|string|max:1000',
            'offer_discount' => 'nullable|string|max:255',
            'whatsapp_phone_number' => 'nullable|string|max:30',
            'variant' => 'nullable|string|in:A,B,C,all',
        ]);

        if (($input['variant'] ?? 'all') === 'all') {
            return response()->json([
                'variants' => $this->copyGenerator->generateAllVariants($input),
            ]);
        }

        return response()->json(
            $this->copyGenerator->generate($input, $input['variant'] ?? 'A')
        );
    }

    public function validatePreview(Request $request): JsonResponse
    {
        $result = $this->validator->validate(
            $request->all(),
            $request->file('image'),
            $request->file('video')
        );

        return response()->json($result);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'adset_id' => 'required|exists:ad_sets,id',
            'page_id' => 'required|string',
            'template_key' => 'nullable|string|max:64',
            'service_name' => 'required|string|max:255',
            'campaign_goal' => 'nullable|string|max:500',
            'target_audience' => 'nullable|string|max:500',
            'pain_point' => 'nullable|string|max:1000',
            'main_benefit' => 'nullable|string|max:1000',
            'offer_discount' => 'nullable|string|max:255',
            'primary_text' => 'required|string|max:2200',
            'headline' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'call_to_action' => 'required|string|in:WHATSAPP_MESSAGE,SEND_MESSAGE',
            'whatsapp_phone_number' => 'nullable|string|max:30',
            'whatsapp_chat_url' => 'nullable|string|max:2048',
            'whatsapp_prefill_message' => 'nullable|string|max:1000',
            'placements' => 'nullable|array',
            'placements.*' => 'string',
            'active_variant' => 'nullable|string|in:A,B,C',
            'create_ab_variants' => 'nullable|boolean',
            'publish_ads' => 'nullable|boolean',
            'ad_status' => 'nullable|string|in:ACTIVE,PAUSED',
            'variant_a_primary' => 'nullable|string',
            'variant_a_headline' => 'nullable|string',
            'variant_a_description' => 'nullable|string',
            'variant_a_whatsapp' => 'nullable|string',
            'variant_b_primary' => 'nullable|string',
            'variant_b_headline' => 'nullable|string',
            'variant_b_description' => 'nullable|string',
            'variant_b_whatsapp' => 'nullable|string',
            'variant_c_primary' => 'nullable|string',
            'variant_c_headline' => 'nullable|string',
            'variant_c_description' => 'nullable|string',
            'variant_c_whatsapp' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime|max:51200',
            'sync_meta' => 'nullable|boolean',
            'is_reusable' => 'nullable|boolean',
            'name' => 'nullable|string|max:255',
        ]);

        $campaign = Campaign::findOrFail($data['campaign_id']);
        TenantScope::assertCampaign($campaign);

        $adset = AdSet::query()
            ->where('campaign_id', $campaign->id)
            ->findOrFail($data['adset_id']);

        TenantScope::assertAdSet($adset);

        $validation = $this->validator->validate($data, $request->file('image'), $request->file('video'));
        if (! $validation['valid']) {
            return back()->withInput()->withErrors(
                collect($validation['errors'])->mapWithKeys(fn ($e) => [$e['field'] => $e['message'].' '.$e['fix']])->all()
            );
        }

        try {
            $groupId = (string) Str::uuid();
            $variants = $this->resolveVariants($data, $request->boolean('create_ab_variants'));
            $saved = [];
            $linkedAds = 0;
            $adStatus = $request->input('ad_status', 'PAUSED');

            DB::transaction(function () use ($request, $data, $variants, $groupId, $adset, $adStatus, &$saved, &$linkedAds) {
                foreach ($variants as $variant => $copy) {
                    $creative = $this->persistCreative($request, $data, $copy, $variant, $groupId, count($variants) > 1);
                    $saved[] = $creative;

                    if ($request->boolean('publish_ads') && $request->boolean('sync_meta')) {
                        $this->adLinker->linkCreativeToAdSet($adset, $creative, $adStatus);
                        $linkedAds++;
                    }
                }
            });

            $count = count($saved);
            $message = $count > 1
                ? "{$count} creatives saved for ad set “{$adset->name}”."
                : "Creative saved for ad set “{$adset->name}”.";

            if ($linkedAds > 0) {
                $message .= " {$linkedAds} ad(s) published to Meta ({$adStatus}).";
            }

            return redirect()
                ->route('admin.campaigns.show', $campaign)
                ->with('success', $message);
        } catch (Exception $e) {
            Log::error('CREATIVE_BUILDER_STORE_FAILED', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{primary_text: string, headline: string, description: string, whatsapp_prefill_message: string}>
     */
    protected function resolveVariants(array $data, bool $createAb): array
    {
        if (! $createAb) {
            $variant = $data['active_variant'] ?? 'A';

            return [$variant => [
                'primary_text' => $data['primary_text'],
                'headline' => $data['headline'],
                'description' => $data['description'] ?? '',
                'whatsapp_prefill_message' => $data['whatsapp_prefill_message'] ?? '',
            ]];
        }

        $variants = [];
        foreach (['A', 'B', 'C'] as $v) {
            $key = strtolower($v);
            $variants[$v] = [
                'primary_text' => $data["variant_{$key}_primary"] ?? $data['primary_text'],
                'headline' => $data["variant_{$key}_headline"] ?? $data['headline'],
                'description' => $data["variant_{$key}_description"] ?? ($data['description'] ?? ''),
                'whatsapp_prefill_message' => $data["variant_{$key}_whatsapp"] ?? ($data['whatsapp_prefill_message'] ?? ''),
            ];
        }

        return $variants;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{primary_text: string, headline: string, description: string, whatsapp_prefill_message: string}  $copy
     */
    protected function persistCreative(Request $request, array $data, array $copy, string $variant, string $groupId, bool $isAbTest): Creative
    {
        $campaign = Campaign::findOrFail($data['campaign_id']);
        $adset = AdSet::query()->where('campaign_id', $campaign->id)->findOrFail($data['adset_id']);

        if ($tenantPageId = TenantScope::pageId()) {
            $data['page_id'] = $tenantPageId;
        } elseif (empty($data['page_id']) && $campaign->meta_page_id) {
            $data['page_id'] = $campaign->meta_page_id;
        }

        $account = TenantScope::requireAdAccount();
        $accountId = str_starts_with($account->meta_id, 'act_') ? $account->meta_id : 'act_'.$account->meta_id;

        $imagePath = null;
        $imageHash = null;
        $metaImageUrl = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('creatives', 'public');
            $fullPath = storage_path('app/public/'.$imagePath);

            if ($request->boolean('sync_meta')) {
                $upload = $this->meta->uploadImage($accountId, $fullPath);
                $image = current($upload['images'] ?? []);
                $imageHash = $image['hash'] ?? null;
                $metaImageUrl = $image['url'] ?? $image['url_256'] ?? null;
                if (! $imageHash) {
                    throw new Exception('Meta image upload failed.');
                }
            }
        }

        $instagramUserId = $this->meta->resolveInstagramUserId($data['page_id'], $account->meta_id);
        $waDestination = trim((string) ($data['whatsapp_chat_url'] ?? $data['whatsapp_phone_number'] ?? ''));
        if ($waDestination === '') {
            $waDestination = (string) (app(TenantConnectionResolver::class)->whatsappPhoneNumber() ?? '');
        }

        $waLink = $this->whatsAppBuilder->resolveWhatsAppLink(
            $waDestination,
            $copy['whatsapp_prefill_message']
        );
        $waPhone = $this->whatsAppBuilder->phoneFromLink($waDestination) ?? preg_replace('/\D+/', '', $waDestination);

        $creativeInput = [
            'page_id' => $data['page_id'],
            'instagram_user_id' => $instagramUserId,
            'headline' => $copy['headline'],
            'primary_text' => $copy['primary_text'],
            'description' => $copy['description'],
            'image_hash' => $imageHash,
            'whatsapp_chat_url' => $waDestination,
            'whatsapp_phone_number' => $waPhone,
            'whatsapp_prefill_message' => $copy['whatsapp_prefill_message'],
        ];

        $baseName = $data['name'] ?? $data['service_name'];
        $creativeName = $isAbTest ? "{$baseName} — Variant {$variant}" : $baseName;
        $payload = $this->whatsAppBuilder->buildCreativePayload($creativeName, $creativeInput);
        if ($metaImageUrl) {
            $payload['meta_image_url'] = $metaImageUrl;
        }

        $metaCreativeId = null;
        if ($request->boolean('sync_meta')) {
            $response = $this->meta->createClickToWhatsAppCreative($accountId, $payload);
            $metaCreativeId = $response['id'] ?? null;
        }

        $placements = $data['placements'] ?? $this->contextResolver->placementsFromTargeting(
            is_array($adset->targeting) ? $adset->targeting : []
        );

        return Creative::create([
            'campaign_id' => $campaign->id,
            'adset_id' => $adset->id,
            'name' => $creativeName,
            'service_name' => $data['service_name'],
            'campaign_goal' => $data['campaign_goal'] ?? null,
            'target_audience' => $data['target_audience'] ?? null,
            'pain_point' => $data['pain_point'] ?? null,
            'main_benefit' => $data['main_benefit'] ?? null,
            'offer_discount' => $data['offer_discount'] ?? null,
            'template_key' => $data['template_key'] ?? null,
            'ab_variant' => $isAbTest ? $variant : ($data['active_variant'] ?? null),
            'creative_group_id' => $isAbTest ? $groupId : null,
            'placements' => $placements,
            'builder_inputs' => array_merge($data, ['linked_adset_name' => $adset->name, 'linked_campaign_name' => $campaign->name]),
            'is_reusable' => $request->boolean('is_reusable', true),
            'headline' => $copy['headline'],
            'body' => $copy['primary_text'],
            'description' => $copy['description'],
            'call_to_action' => $data['call_to_action'],
            'creative_format' => 'click_to_whatsapp',
            'page_id' => $data['page_id'],
            'instagram_user_id' => $instagramUserId,
            'whatsapp_phone_number' => $waPhone,
            'whatsapp_prefill_message' => $copy['whatsapp_prefill_message'],
            'whatsapp_fallback_url' => $waLink,
            'whatsapp_chat_url' => str_starts_with($waDestination, 'http') ? $waLink : null,
            'destination_url' => $waLink,
            'image_url' => $imagePath,
            'image_hash' => $imageHash,
            'meta_id' => $metaCreativeId,
            'json_payload' => $payload,
            'status' => Creative::STATUS_ACTIVE,
        ]);
    }
}
