<x-guest-layout>

<div class="w-full max-w-md mx-auto" x-data="{ showPassword: false }">

<x-auth-session-status class="mb-6" :status="session('status')" />

<div class="text-center mb-8">

<h2 class="text-2xl font-bold text-gray-900">
Sign in to your account
</h2>

<p class="text-sm text-gray-500 mt-1">
Access your {{ config('app.name') }} dashboard
</p>

</div>


<form method="POST" action="{{ route('login') }}" class="space-y-6">
@csrf


{{-- EMAIL --}}
<div>

<x-input-label
for="email"
:value="__('Email Address')"
class="text-sm font-medium text-gray-700"
/>

<x-text-input
id="email"
class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm focus:border-xander-navy focus:ring-xander-navy py-3 px-4"
type="email"
name="email"
:value="old('email')"
required
autofocus
autocomplete="username"
/>

<x-input-error :messages="$errors->get('email')" class="mt-2" />

</div>


{{-- PASSWORD --}}
<div>

<x-input-label
for="password"
:value="__('Password')"
class="text-sm font-medium text-gray-700"
/>

<div class="relative mt-2">
<x-text-input
id="password"
class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-xander-navy focus:ring-xander-navy py-3 pl-4 pr-12"
x-bind:type="showPassword ? 'text' : 'password'"
name="password"
required
autocomplete="current-password"
/>

<button
type="button"
class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-800"
@click="showPassword = !showPassword"
x-bind:aria-pressed="showPassword"
aria-label="Toggle password visibility"
>
<svg x-show="!showPassword" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
</svg>
<svg x-show="showPassword" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
</svg>
</button>
</div>

<x-input-error :messages="$errors->get('password')" class="mt-2" />

</div>


{{-- OPTIONS --}}
<div class="flex items-center justify-between text-sm">

<label class="flex items-center text-gray-600">

<input
id="remember_me"
type="checkbox"
class="rounded border-gray-300 text-xander-navy focus:ring-xander-navy"
name="remember"
/>

<span class="ml-2">Remember me</span>

</label>

@if (Route::has('password.request'))
<a
class="text-xander-navy hover:text-xander-secondary font-medium"
href="{{ route('password.request') }}">
Forgot password?
</a>
@endif

</div>


{{-- LOGIN BUTTON --}}
<button
type="submit"
class="w-full bg-xander-navy hover:bg-xander-secondary active:bg-xander-accent text-white font-semibold py-3 rounded-xl transition shadow-sm">

Log in

</button>


</form>

</div>

</x-guest-layout>