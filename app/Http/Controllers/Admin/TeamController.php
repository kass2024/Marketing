<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index()
    {
        return view('admin.settings.team');
    }

    public function store(Request $request)
    {
        return back()->with('success','Team member added.');
    }

    public function destroy($user)
    {
        return back()->with('success','Team member removed.');
    }
}