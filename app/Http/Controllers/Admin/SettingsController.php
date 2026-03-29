<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'platform_name' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'timezone' => 'nullable|string|max:64',
            'xander_name' => 'required|string|max:255',
            'xander_email' => 'required|email|max:255',
        ]);

        PlatformSettings::save([
            'xander_name' => $data['xander_name'],
            'xander_email' => $data['xander_email'],
        ]);

        return back()->with('success', 'General settings updated.');
    }

    public function updateMeta(Request $request)
    {
        return back()->with('success', 'Meta settings updated.');
    }
}