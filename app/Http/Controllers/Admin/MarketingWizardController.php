<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\MetaApiLog;
use App\Models\PlatformMetaConnection;
use App\Services\Tenant\TenantConnectionResolver;
use App\Services\Meta\ClickToWhatsAppCreativeBuilder;
use App\Services\Meta\MarketingPreflightValidator;
use App\Services\Meta\MarketingPublishService;
use App\Services\Meta\MetaConnectionValidator;
use App\Services\MetaAdsService;
use App\Support\TenantScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingWizardController extends Controller
{
    public function __construct(
        protected MetaAdsService $meta,
        protected MarketingPublishService $publisher,
        protected MarketingPreflightValidator $preflight,
        protected MetaConnectionValidator $connectionValidator,
        protected ClickToWhatsAppCreativeBuilder $creativeBuilder
    ) {}

    public function index(Request $request): View
    {
        $connection = app(TenantConnectionResolver::class)->forCurrentUser();
        $connectionStatus = $this->connectionValidator->validate($connection);
        $pages = [];

        try {
            $pages = TenantScope::filterPages($this->meta->getPages());
        } catch (Exception) {
            // Pages load after Meta token is configured.
        }

        $draft = $request->session()->get('marketing_wizard', []);
        $step = max(1, min(10, (int) $request->query('step', $draft['step'] ?? 1)));

        return view('admin.marketing.wizard', [
            'step' => $step,
            'draft' => $draft,
            'connection' => $connection,
            'connectionStatus' => $connectionStatus,
            'pages' => $pages,
            'objectives' => ClickToWhatsAppCreativeBuilder::campaignObjectives(),
            'ctaOptions' => [
                ClickToWhatsAppCreativeBuilder::CTA_WHATSAPP_MESSAGE => 'WhatsApp Message',
            ],
            'recentLogs' => MetaApiLog::query()->latest()->limit(5)->get(),
        ]);
    }

    public function saveStep(Request $request): RedirectResponse
    {
        $step = (int) $request->input('step', 1);
        $draft = $request->session()->get('marketing_wizard', []);
        $input = $request->except(['_token', 'step', 'image']);

        if ($request->hasFile('image')) {
            $input['image_path'] = $request->file('image')->store('marketing-wizard', 'public');
        }

        if (! empty($input['countries']) && is_string($input['countries'])) {
            $input['countries'] = array_values(array_filter(array_map(
                fn ($c) => strtoupper(trim($c)),
                explode(',', $input['countries'])
            )));
        }

        $draft = array_merge($draft, array_filter($input, fn ($v) => $v !== null && $v !== ''));
        $draft['step'] = min(10, $step + 1);

        $request->session()->put('marketing_wizard', $draft);

        return redirect()
            ->route('admin.marketing.wizard', ['step' => $draft['step']])
            ->with('success', 'Step saved.');
    }

    public function preflight(Request $request): JsonResponse
    {
        $draft = $request->session()->get('marketing_wizard', []);
        $connection = app(TenantConnectionResolver::class)->forCurrentUser();
        $validation = $this->preflight->validateWizard($draft, $connection);
        $checklist = $this->preflight->checklist($draft, $connection);

        return response()->json([
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'checklist' => $checklist,
            'whatsapp_preview_url' => ! empty($draft['whatsapp_chat_url'] ?? $draft['whatsapp_phone_number'] ?? '')
                ? $this->creativeBuilder->resolveWhatsAppLink(
                    (string) ($draft['whatsapp_chat_url'] ?? $draft['whatsapp_phone_number'] ?? ''),
                    (string) ($draft['whatsapp_prefill_message'] ?? '')
                )
                : null,
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $draft = $request->session()->get('marketing_wizard', []);
        $activate = $request->boolean('activate');

        try {
            $result = $this->publisher->publishFromWizard($draft, $activate);
            $request->session()->forget('marketing_wizard');

            return redirect()
                ->route('admin.campaigns.show', $result['campaign'])
                ->with('success', $activate
                    ? 'Click-to-WhatsApp campaign published and activated on Meta.'
                    : 'Click-to-WhatsApp campaign saved as PAUSED on Meta for review.');
        } catch (Exception $e) {
            return redirect()
                ->route('admin.marketing.wizard', ['step' => 10])
                ->with('error', $this->meta->humanizeMetaError($e));
        }
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        $draft = $request->session()->get('marketing_wizard', []);

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

        $request->session()->forget('marketing_wizard');

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Campaign draft saved locally. Complete publishing from the wizard when ready.');
    }
}
