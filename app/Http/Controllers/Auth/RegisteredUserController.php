<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\MetaAdsService;
use App\Support\TenantScope;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'meta_page_id' => ['required', 'string', 'max:64'],
            'meta_page_name' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['email'] = strtolower(trim($validated['email']));

        $platformAdAccountId = TenantScope::platformAdAccountMetaId();

        if (! $platformAdAccountId) {
            return back()->withErrors([
                'registration_error' => 'Registration is temporarily unavailable. Platform Meta ad account is not configured.',
            ])->withInput();
        }

        try {
            $pageName = $this->resolvePageName(
                $validated['meta_page_id'],
                $validated['meta_page_name'] ?? null
            );

            $this->assertPageIsAllowed($validated['meta_page_id']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => User::defaultClientPassword(),
                'role' => User::ROLE_CLIENT,
                'status' => User::STATUS_ACTIVE,
            ]);

            Client::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'business_email' => $user->email,
                'phone' => $validated['phone'] ?? null,
                'subscription_plan' => Client::PLAN_FREE,
                'subscription_status' => Client::STATUS_ACTIVE,
                'meta_page_id' => $validated['meta_page_id'],
                'meta_page_name' => $pageName,
                'meta_ad_account_id' => $platformAdAccountId,
                'meta_ad_account_name' => (string) config('services.meta.ad_account_name', 'Platform Ad Account'),
            ]);

            TenantScope::ensurePlatformAdAccount($pageName);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('CLIENT_REGISTRATION_FAILED', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'registration_error' => 'Registration failed. Please try again or contact support.',
            ])->withInput();
        }

        Log::info('CLIENT_REGISTERED', [
            'user_id' => $user->id,
            'email' => $user->email,
            'page_id' => $validated['meta_page_id'],
            'ad_account_id' => $platformAdAccountId,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Welcome! Your ads workspace is linked to Facebook Page: '.$pageName.'.');
    }

    protected function resolvePageName(string $pageId, ?string $submittedName): string
    {
        if ($submittedName) {
            return $submittedName;
        }

        try {
            foreach (app(MetaAdsService::class)->getPages() as $page) {
                if ((string) ($page['id'] ?? '') === (string) $pageId) {
                    return (string) ($page['name'] ?? 'Facebook Page');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('REGISTER_PAGE_NAME_LOOKUP_FAILED', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }

        if ((string) config('services.meta.page_id') === (string) $pageId) {
            return (string) config('services.meta.page_name', 'Facebook Page');
        }

        return 'Facebook Page';
    }

    protected function assertPageIsAllowed(string $pageId): void
    {
        try {
            $pages = app(MetaAdsService::class)->getPages();
            $allowedIds = collect($pages)->pluck('id')->map(fn ($id) => (string) $id);

            if ($allowedIds->contains((string) $pageId)) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('REGISTER_PAGE_VALIDATION_SKIPPED', [
                'error' => $e->getMessage(),
            ]);
        }

        $fallbackId = config('services.meta.page_id');

        if ($fallbackId && (string) $fallbackId === (string) $pageId) {
            return;
        }

        throw ValidationException::withMessages([
            'meta_page_id' => 'The selected Facebook page is not available. Refresh the page list and try again.',
        ]);
    }
}
