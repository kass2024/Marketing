<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Show registration form
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle registration request
     */
    public function store(Request $request): RedirectResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validate Input
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'name'     => ['required','string','max:255'],

            'email'    => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email'
            ],

            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults()
            ],

            // optional fields
            'company_name' => ['nullable','string','max:255'],
            'phone'        => ['nullable','string','max:255'],

        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Create User
            |--------------------------------------------------------------------------
            */

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role'     => User::ROLE_CLIENT,
                'status'   => User::STATUS_ACTIVE,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Create Client Profile
            |--------------------------------------------------------------------------
            */

            Client::create([

                'user_id' => $user->id,

                'company_name' =>
                    $validated['company_name']
                    ?? ($user->name . "'s Company"),

                'business_email' => $user->email,

                'phone' =>
                    $validated['phone'] ?? null,

                'subscription_plan'   => Client::PLAN_FREE,
                'subscription_status' => Client::STATUS_ACTIVE,

            ]);


            DB::commit();

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('CLIENT_REGISTRATION_FAILED',[
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'registration_error' =>
                'Registration failed. Please try again.'
            ])->withInput();
        }


        /*
        |--------------------------------------------------------------------------
        | Fire Registered Event
        |--------------------------------------------------------------------------
        */

        event(new Registered($user));


        /*
        |--------------------------------------------------------------------------
        | Login User
        |--------------------------------------------------------------------------
        */

        Auth::login($user);


        /*
        |--------------------------------------------------------------------------
        | Redirect Client
        |--------------------------------------------------------------------------
        */

        return redirect()->route('client.dashboard');
    }
}