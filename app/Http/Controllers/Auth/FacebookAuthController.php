<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FacebookLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FacebookAuthController extends Controller
{
    protected FacebookLoginService $facebook;

    public function __construct(FacebookLoginService $facebook)
    {
        $this->facebook = $facebook;
    }

    public function redirect()
    {
        return redirect()->away(
            $this->facebook->getAuthorizationUrl()
        );
    }

    public function callback(Request $request)
    {
        if (!$request->has(['code', 'state'])) {
            return redirect('/login')->with('error', 'Facebook login failed.');
        }

        try {

            $tokenData = $this->facebook->getAccessToken(
                $request->code,
                $request->state
            );

            $userData = $this->facebook->getUser(
                $tokenData['access_token']
            );

            $user = User::firstOrCreate(
                ['email' => $userData['email'] ?? null],
                [
                    'name'     => $userData['name'],
                    'password' => bcrypt(Str::random(24)),
                ]
            );

            Auth::login($user);

            return redirect()->route('client.dashboard');

        } catch (\Throwable $e) {

            return redirect('/login')
                ->with('error', 'Facebook login failed.');
        }
    }
}