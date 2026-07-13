<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $query = Client::with('user', 'metaConnection')
            ->withCount('campaigns');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%$search%")
                  ->orWhere('meta_page_name', 'like', "%$search%")
                  ->orWhere('meta_page_id', 'like', "%$search%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('email', 'like', "%$search%")
                        ->orWhere('name', 'like', "%$search%");
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
            'plan'     => 'required|in:free,pro,enterprise',
            'meta_page_id' => 'required|string|max:64',
            'meta_page_name' => 'nullable|string|max:255',
            'whatsapp_phone_number' => 'required|string|max:32',
        ]);

        $pages = app(\App\Services\Tenant\TenantMetaPageValidator::class);
        $pages->assertPageIsAllowed($request->meta_page_id);
        $pageName = $pages->resolvePageName($request->meta_page_id, $request->meta_page_name);
        $whatsapp = $pages->assertWhatsAppNumber($request->whatsapp_phone_number);

        $client = null;

        DB::transaction(function () use ($request, $pageName, $whatsapp, &$client) {

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => User::defaultClientPassword(),
                'role'     => 'client',
                'status'   => 'active',
                'email_verified_at' => now(),
            ]);

            $client = Client::create([
                'user_id'             => $user->id,
                'company_name'        => $request->name,
                'business_email'      => $request->email,
                'subscription_plan'   => $request->plan,
                'subscription_status' => Client::STATUS_ACTIVE,
                'meta_page_id'        => $request->meta_page_id,
                'meta_page_name'      => $pageName,
                'whatsapp_phone_number' => $whatsapp,
                'whatsapp_verification_status' => 'pending',
            ]);
        });

        try {
            app(\App\Services\Tenant\TenantWhatsAppSyncService::class)->provisionAndRequestCode($client);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('admin.clients.index')
                ->with('warning', 'Client created but WhatsApp Meta sync failed: '.collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client created. WhatsApp number added to Meta — tenant must verify via SMS.');
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
            'meta_page_id' => 'required|string|max:64',
            'meta_page_name' => 'nullable|string|max:255',
            'whatsapp_phone_number' => 'required|string|max:32',
            'whatsapp_phone_number_id' => 'nullable|string|max:64',
        ]);

        $pages = app(\App\Services\Tenant\TenantMetaPageValidator::class);
        $pages->assertPageIsAllowed($request->meta_page_id);
        $pageName = $pages->resolvePageName($request->meta_page_id, $request->meta_page_name);
        $whatsapp = $pages->assertWhatsAppNumber($request->whatsapp_phone_number);
        $numberChanged = $whatsapp !== $client->whatsapp_phone_number;

        DB::transaction(function () use ($request, $client, $pageName, $whatsapp, $numberChanged) {

            $client->update([
                'company_name'        => $request->name,
                'subscription_plan'   => $request->plan,
                'meta_page_id'        => $request->meta_page_id,
                'meta_page_name'      => $pageName,
                'whatsapp_phone_number' => $whatsapp,
                'whatsapp_phone_number_id' => $numberChanged ? null : $request->whatsapp_phone_number_id,
                ...( $numberChanged ? [
                    'whatsapp_verified_name' => null,
                    'whatsapp_verification_status' => 'pending',
                    'whatsapp_verified_at' => null,
                    'whatsapp_meta_synced_at' => null,
                ] : []),
            ]);

            $client->user->update([
                'name'  => $request->name,
                'email' => $request->email,
            ]);
        });

        if ($numberChanged) {
            try {
                app(\App\Services\Tenant\TenantWhatsAppSyncService::class)->provisionAndRequestCode($client->fresh());
            } catch (\Illuminate\Validation\ValidationException $e) {
                return redirect()
                    ->route('admin.clients.index')
                    ->with('warning', 'Client updated but WhatsApp Meta sync failed: '.collect($e->errors())->flatten()->first());
            }
        }

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