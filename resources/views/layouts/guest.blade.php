<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ config('app.name', 'Parrot Canada') }}</title>

<link rel="icon" href="{{ asset('img/logo.png') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>

@vite(['resources/css/app.css','resources/js/app.js'])

</head>


<body class="font-sans antialiased bg-gray-100">

<div class="min-h-screen flex items-center justify-center px-6">

<div class="w-full max-w-5xl">

<div class="bg-white rounded-3xl shadow-xl overflow-hidden grid lg:grid-cols-2">

{{-- LEFT BRAND PANEL --}}
<div class="hidden lg:flex bg-green-600 text-white items-center justify-center p-12">

<div class="text-center max-w-sm">

<img
src="{{ asset('img/logo.png') }}"
class="w-20 mx-auto mb-6">

<h1 class="text-3xl font-bold mb-4">
Parrot Canada
</h1>

<p class="text-green-100 leading-relaxed text-sm">
AI chatbot automation and Meta Ads management platform.
Manage conversations, campaigns, creatives and marketing
performance from one powerful dashboard.
</p>

</div>

</div>



{{-- RIGHT AUTH FORM --}}
<div class="flex items-center justify-center p-10">

<div class="w-full max-w-md">

{{-- MOBILE BRAND --}}
<div class="lg:hidden text-center mb-8">

<img
src="{{ asset('img/logo.png') }}"
class="w-16 mx-auto mb-3">

<h2 class="text-lg font-semibold text-gray-800">
Parrot Canada
</h2>

</div>


{{ $slot }}

</div>

</div>


</div>

</div>

</div>

</body>
</html>