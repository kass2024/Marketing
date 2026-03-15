<x-guest-layout>

<div class="w-full max-w-md mx-auto">

<x-auth-session-status class="mb-6" :status="session('status')" />

<div class="text-center mb-8">
<h2 class="text-2xl font-bold text-gray-900">
Sign in to your account
</h2>

<p class="text-sm text-gray-500 mt-1">
Access your Parrot Canada dashboard
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
class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 py-3 px-4"
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

<x-text-input
id="password"
class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 py-3 px-4"
type="password"
name="password"
required
autocomplete="current-password"
/>

<x-input-error :messages="$errors->get('password')" class="mt-2" />

</div>


{{-- OPTIONS --}}
<div class="flex items-center justify-between text-sm">

<label class="flex items-center text-gray-600">

<input
id="remember_me"
type="checkbox"
class="rounded border-gray-300 text-green-600 focus:ring-green-500"
name="remember"
/>

<span class="ml-2">Remember me</span>

</label>

@if (Route::has('password.request'))
<a
class="text-green-600 hover:text-green-700 font-medium"
href="{{ route('password.request') }}">
Forgot password?
</a>
@endif

</div>


{{-- LOGIN BUTTON --}}
<button
type="submit"
class="w-full bg-green-600 hover:bg-green-700 active:bg-green-800 text-white font-semibold py-3 rounded-xl transition shadow-sm">

Log in

</button>


</form>

</div>

</x-guest-layout>