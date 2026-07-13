<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class MetaConnectionController extends Controller
{
    public function connect(): RedirectResponse
    {
        return redirect()->route('client.profile.edit');
    }

    public function callback(): RedirectResponse
    {
        return redirect()->route('client.profile.edit');
    }

    public function disconnect(): RedirectResponse
    {
        return redirect()
            ->route('client.profile.edit')
            ->with('info', 'Ads use your assigned Facebook Page and WhatsApp. Publishing is controlled by the platform account.');
    }
}
