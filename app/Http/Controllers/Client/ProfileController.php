<?php



namespace App\Http\Controllers\Client;



use App\Http\Controllers\Controller;

use App\Services\Tenant\TenantMetaPageValidator;

use App\Services\Tenant\TenantWhatsAppSyncService;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;

use Illuminate\View\View;



class ProfileController extends Controller

{

    public function edit(): View

    {

        $client = auth()->user()?->client;



        abort_if(! $client, 403);



        return view('client.profile.edit', compact('client'));

    }



    public function update(

        Request $request,

        TenantMetaPageValidator $pages,

        TenantWhatsAppSyncService $whatsappSync

    ): RedirectResponse {

        $client = auth()->user()?->client;



        abort_if(! $client, 403);



        $validated = $request->validate([

            'meta_page_id' => ['required', 'string', 'max:64'],

            'meta_page_name' => ['required', 'string', 'max:255'],

            'whatsapp_phone_number' => ['required', 'string', 'max:32'],

            'whatsapp_verification_code' => ['nullable', 'string', 'min:4', 'max:10'],

        ]);



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



        $numberChanged = $whatsapp !== $client->whatsapp_phone_number;



        $client->update([

            'meta_page_id' => $validated['meta_page_id'],

            'meta_page_name' => $pageName,

            'whatsapp_phone_number' => $whatsapp,

            ...( $numberChanged ? [

                'whatsapp_phone_number_id' => null,

                'whatsapp_verified_name' => null,

                'whatsapp_verification_status' => 'pending',

                'whatsapp_verified_at' => null,

                'whatsapp_meta_synced_at' => null,

            ] : []),

        ]);



        if ($numberChanged) {

            try {

                $waResult = $whatsappSync->provisionAndRequestCode($client->fresh());

            } catch (ValidationException $e) {

                return back()->withErrors($e->errors())->withInput();

            }



            if ($waResult['status'] === 'verified') {

                return redirect()

                    ->route('admin.campaigns.index')

                    ->with('success', 'Profile updated. WhatsApp number verified and synced with Meta.');

            }



            return redirect()

                ->route('register.whatsapp.verify')

                ->with('success', $waResult['message']);

        }



        if ($client->needsWhatsAppVerification() && filled($validated['whatsapp_verification_code'] ?? null)) {

            try {

                $whatsappSync->verifyCodeAndRegister($client->fresh(), $validated['whatsapp_verification_code']);

            } catch (ValidationException $e) {

                return back()->withErrors($e->errors())->withInput();

            }



            return redirect()

                ->route('admin.campaigns.index')

                ->with('success', 'Your Facebook Page and verified WhatsApp destination were updated.');

        }



        if ($client->fresh()->needsWhatsAppVerification()) {

            try {

                $waResult = $whatsappSync->provisionAndRequestCode($client->fresh());

            } catch (ValidationException $e) {

                return back()->withErrors($e->errors())->withInput();

            }



            return redirect()

                ->route('register.whatsapp.verify')

                ->with('success', $waResult['message']);

        }



        return redirect()

            ->route('admin.campaigns.index')

            ->with('success', 'Your Facebook Page and WhatsApp destination were updated.');

    }

}

