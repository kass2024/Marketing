<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::beginTransaction();

        try {

            // Create User
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => User::ROLE_CLIENT,
                'status'   => User::STATUS_ACTIVE,
            ]);

            // Create Client (Business Profile)
            Client::create([
                'user_id'            => $user->id,
                'company_name'       => $user->name . "'s Company",
                'business_email'     => $user->email,
                'subscription_plan'  => Client::PLAN_FREE,
                'subscription_status'=> Client::STATUS_ACTIVE,
            ]);

            DB::commit();

        } catch (\Exception $e) {

            DB::rollBack();

            return back()->withErrors([
                'registration_error' => 'Something went wrong. Please try again.',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}