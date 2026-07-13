<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Tenant\TenantMetaPageValidator;
use App\Services\Tenant\TenantWhatsAppSyncService;
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

    public function store(
        Request $request,
        TenantMetaPageValidator $pages,
        TenantWhatsAppSyncService $whatsappSync
    ): RedirectResponse {
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
            'meta_page_name' => ['required', 'string', 'max:255'],
            'whatsapp_phone_number' => ['required', 'string', 'max:32'],
        ]);

        $validated['email'] = strtolower(trim($validated['email']));

        if (! TenantScope::platformAdAccountMetaId()) {
            return back()->withErrors([
                'registration_error' => 'Registration is temporarily unavailable. Platform Meta ad account is not configured.',
            ])->withInput();
        }

        try {
            $pages->assertPageIsAllowed($validated['meta_page_id']);
            $pageName = $pages->resolvePageName(
                $validated['meta_page_id'],
                $validated['meta_page_name'] ?? null
            );
            $whatsapp = $pages->assertWhatsAppNumber($validated['whatsapp_phone_number']);
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
                'email_verified_at' => now(),
            ]);

            $client = Client::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'business_email' => $user->email,
                'phone' => $validated['phone'] ?? null,
                'subscription_plan' => Client::PLAN_FREE,
                'subscription_status' => Client::STATUS_ACTIVE,
                'meta_page_id' => $validated['meta_page_id'],
                'meta_page_name' => $pageName,
                'whatsapp_phone_number' => $whatsapp,
                'whatsapp_verification_status' => 'pending',
            ]);

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
            'whatsapp' => $whatsapp,
        ]);

        event(new Registered($user));

        Auth::login($user);

        try {
            $waResult = $whatsappSync->provisionAndRequestCode($client);
        } catch (ValidationException $e) {
            return redirect()
                ->route('register.whatsapp.verify')
                ->withErrors($e->errors());
        }

        if ($waResult['status'] === 'verified') {
            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', "Welcome! Your WhatsApp number is verified as \"{$client->fresh()->whatsapp_verified_name}\". Ads will run on {$pageName}.");
        }

        return redirect()
            ->route('register.whatsapp.verify')
            ->with('success', $waResult['message']);
    }
}
