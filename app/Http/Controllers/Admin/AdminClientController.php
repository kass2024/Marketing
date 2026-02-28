<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Client;
use App\Models\User;

class AdminClientController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX (List Clients)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = Client::with('user', 'metaConnection');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('email', 'like', "%$search%");
                  });
            });
        }

        $clients = $query->latest()->paginate(15);

        return view('admin.clients.index', compact('clients'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('admin.clients.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE CLIENT
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'plan'     => 'required|in:free,pro,enterprise',
        ]);

        DB::transaction(function () use ($request) {

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => 'client',
            ]);

            Client::create([
                'user_id'           => $user->id,
                'name'              => $request->name,
                'subscription_plan' => $request->plan,
            ]);
        });

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client created successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW CLIENT DETAILS
    |--------------------------------------------------------------------------
    */
    public function show(Client $client)
    {
        $client->load('user', 'metaConnection', 'campaigns', 'chatbots');

        return view('admin.clients.show', compact('client'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT FORM
    |--------------------------------------------------------------------------
    */
    public function edit(Client $client)
    {
        return view('admin.clients.edit', compact('client'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CLIENT
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, Client $client)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'plan'  => 'required|in:free,pro,enterprise',
            'email' => 'required|email|unique:users,email,' . $client->user->id,
        ]);

        DB::transaction(function () use ($request, $client) {

            $client->update([
                'name'              => $request->name,
                'subscription_plan' => $request->plan,
            ]);

            $client->user->update([
                'name'  => $request->name,
                'email' => $request->email,
            ]);
        });

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE CLIENT
    |--------------------------------------------------------------------------
    */
    public function destroy(Client $client)
    {
        DB::transaction(function () use ($client) {

            $client->user->delete();
            $client->delete();
        });

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPERSONATE CLIENT
    |--------------------------------------------------------------------------
    */
    public function impersonate(Client $client)
    {
        if (!$client->user) {
            return back()->with('error', 'Client user not found.');
        }

        session(['impersonator_id' => auth()->id()]);

        Auth::login($client->user);

        return redirect()->route('client.dashboard');
    }

    /*
    |--------------------------------------------------------------------------
    | STOP IMPERSONATION
    |--------------------------------------------------------------------------
    */
    public function stopImpersonation()
    {
        if (!session()->has('impersonator_id')) {
            return redirect()->route('admin.dashboard');
        }

        $adminId = session('impersonator_id');

        session()->forget('impersonator_id');

        Auth::loginUsingId($adminId);

        return redirect()->route('admin.dashboard');
    }
}