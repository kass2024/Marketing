<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SystemController extends Controller
{
    public function logs()
    {
        return view('admin.system.logs');
    }

    public function queue()
    {
        return view('admin.system.queue');
    }

    public function cache()
    {
        return view('admin.system.cache');
    }

    public function clearCache()
    {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');

        return back()->with('success','Cache cleared');
    }
}