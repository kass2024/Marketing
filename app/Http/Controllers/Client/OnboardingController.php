<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\MetaConnection;

class OnboardingController extends Controller
{
    public function meta()
    {
        return view('client.onboarding.meta');
    }

    public function metaCallback(Request $request)
    {
        $user = Auth::user();
        $client = Client::where('user_id', $user->id)->first();

        MetaConnection::updateOrCreate(
            ['client_id' => $client->id],
            [
                'access_token' => $request->token ?? null,
                'business_id' => $request->business_id ?? null,
            ]
        );

        return redirect()->route('onboarding.adaccount');
    }

    public function adAccount()
    {
        return view('client.onboarding.ad-account');
    }

    public function storeAdAccount(Request $request)
    {
        $client = Client::where('user_id', Auth::id())->first();

        $meta = $client->metaConnection;

        $meta->update([
            'ad_account_id' => $request->ad_account_id
        ]);

        return redirect()->route('onboarding.page');
    }

    public function page()
    {
        return view('client.onboarding.page');
    }

    public function storePage(Request $request)
    {
        $client = Client::where('user_id', Auth::id())->first();

        $meta = $client->metaConnection;

        $meta->update([
            'page_id' => $request->page_id
        ]);

        return redirect()->route('onboarding.whatsapp');
    }

    public function whatsapp()
    {
        return view('client.onboarding.whatsapp');
    }

    public function finish()
    {
        return redirect()->route('client.dashboard');
    }
}