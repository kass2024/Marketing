<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify WhatsApp — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F0F2F5] font-sans text-slate-800 antialiased">
<div class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-12">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-wide text-xander-navy">Meta sync</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Verify your business WhatsApp</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">
            We added <strong>+{{ $client->whatsapp_phone_number }}</strong> to the platform WhatsApp Business account
            as <strong>{{ $client->whatsapp_verified_name ?: $client->company_name }}</strong>.
            Meta sent a verification code by SMS — enter it below to finish syncing.
        </p>

        @if(session('success'))
            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.whatsapp.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="whatsapp_verification_code">SMS verification code</label>
                <input id="whatsapp_verification_code" name="whatsapp_verification_code" type="text" inputmode="numeric" required autofocus
                    maxlength="10" placeholder="6-digit code from Meta"
                    class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-center text-lg tracking-widest focus:border-xander-navy focus:ring focus:ring-xander-navy/20">
            </div>
            <button type="submit"
                class="w-full rounded-xl bg-xander-navy px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-xander-navy/20 transition hover:bg-xander-secondary">
                Verify &amp; sync with Meta
            </button>
        </form>

        <form method="POST" action="{{ route('register.whatsapp.resend') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm font-semibold text-xander-navy hover:underline">
                Resend verification code
            </button>
        </form>
    </div>
</div>
</body>
</html>
