<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function updateGeneral(Request $request)
    {
        // Save settings later if needed
        return back()->with('success','General settings updated.');
    }

    public function updateMeta(Request $request)
    {
        // Save Meta API settings later
        return back()->with('success','Meta settings updated.');
    }
}