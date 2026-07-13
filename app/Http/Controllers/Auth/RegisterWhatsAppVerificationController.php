<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantWhatsAppSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterWhatsAppVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $client = $request->user()?->client;

        if (! $client) {
            return redirect()->route('login');
        }

        if ($client->isWhatsAppVerified()) {
            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Your WhatsApp business number is already verified.');
        }

        return view('auth.register-whatsapp-verify', [
            'client' => $client,
        ]);
    }

    public function verify(Request $request, TenantWhatsAppSyncService $sync): RedirectResponse
    {
        $client = $request->user()?->client;

        abort_if(! $client, 403);

        $validated = $request->validate([
            'whatsapp_verification_code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        try {
            $result = $sync->verifyCodeAndRegister($client, $validated['whatsapp_verification_code']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', $result['message']);
    }

    public function resend(Request $request, TenantWhatsAppSyncService $sync): RedirectResponse
    {
        $client = $request->user()?->client;

        abort_if(! $client, 403);

        try {
            $result = $sync->provisionAndRequestCode($client->fresh());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', $result['message']);
    }
}
