<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ config('app.name') }}</title>

<link rel="icon" href="{{ asset('img/logo.png') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>

@vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-slate-100 text-slate-900">

<div class="min-h-screen flex items-center justify-center px-4 py-10 sm:px-6">

<div class="w-full max-w-5xl">

<div class="bg-white rounded-3xl shadow-xl overflow-hidden grid lg:grid-cols-2 lg:min-h-[620px]">

{{-- LEFT BRAND PANEL — solid forest green + Parrot logo --}}
<div class="hidden lg:flex flex-col justify-center bg-xander-navy text-white px-10 py-14 xl:px-14">

<div class="text-center max-w-md mx-auto w-full">

<img
src="{{ asset('img/logo.png') }}"
alt="{{ config('app.name') }}"
class="w-44 h-auto max-w-[min(100%,280px)] mx-auto mb-8 drop-shadow-lg rounded-full ring-2 ring-white/25"
>

<h1 class="text-3xl xl:text-4xl font-bold mb-4 tracking-tight leading-tight">
{{ config('app.name') }}
</h1>

<p class="text-white/90 leading-relaxed text-sm">
AI chatbot automation and Meta Ads management platform.
Manage advertising campaigns, automate WhatsApp conversations,
and track marketing performance from one powerful dashboard.
</p>

<div class="mt-10">
<span class="inline-block rounded-lg bg-xander-gold px-5 py-2.5 text-sm font-semibold text-white shadow-md">
Smart Marketing Automation
</span>
</div>

</div>

</div>


{{-- RIGHT AUTH FORM --}}
<div class="flex items-center justify-center p-8 sm:p-10 lg:p-12">

<div class="w-full max-w-md">

{{-- MOBILE BRAND --}}
<div class="lg:hidden text-center mb-8">

<img
src="{{ asset('img/logo.png') }}"
alt="{{ config('app.name') }}"
class="w-28 h-auto max-w-[220px] mx-auto mb-4 rounded-full ring-2 ring-xander-navy/15"
>

<h2 class="text-lg font-semibold text-slate-800">
{{ config('app.name') }}
</h2>

<p class="text-slate-500 text-sm mt-1">
Ads Management &amp; AI Chatbot Platform
</p>

</div>


{{ $slot }}

</div>

</div>


</div>

</div>

</div>

</body>
</html>
