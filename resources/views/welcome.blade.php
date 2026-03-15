<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Parrot Canada</title>

<link rel="icon" href="{{ asset('img/logo.png') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>

@vite(['resources/css/app.css','resources/js/app.js'])

</head>


<body class="font-sans bg-gray-50 text-gray-800">

<!-- NAVBAR -->
<header class="bg-white border-b">

<div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

<div class="flex items-center gap-3">

<img src="{{ asset('img/logo.png') }}" class="w-8">

<span class="font-semibold text-lg">
Parrot Canada
</span>

</div>

<a
href="{{ route('login') }}"
class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg font-medium transition">

Login

</a>

</div>

</header>


<!-- HERO -->
<section class="py-24">

<div class="max-w-6xl mx-auto px-6 text-center">

<h1 class="text-4xl md:text-5xl font-bold mb-6">

AI Chatbot & Meta Ads Management  
<span class="text-green-600">In One Powerful Dashboard</span>

</h1>

<p class="text-lg text-gray-600 max-w-2xl mx-auto mb-10">

Automate WhatsApp conversations, manage Meta advertising campaigns,
track performance, and grow your business with intelligent automation.

</p>

<a
href="{{ route('login') }}"
class="bg-green-600 hover:bg-green-700 text-white px-8 py-4 rounded-xl text-lg font-semibold shadow-md transition">

Access Dashboard

</a>

</div>

</section>


<!-- FEATURES -->
<section class="py-20 bg-white">

<div class="max-w-6xl mx-auto px-6">

<h2 class="text-3xl font-bold text-center mb-14">
Platform Features
</h2>

<div class="grid md:grid-cols-3 gap-8">


<!-- FEATURE -->
<div class="bg-gray-50 p-8 rounded-xl border hover:shadow-lg transition">

<h3 class="font-semibold text-lg mb-3">
Meta Ads Management
</h3>

<p class="text-gray-600 text-sm">
Create campaigns, manage ad sets, monitor performance and control budgets directly from your dashboard.
</p>

</div>


<!-- FEATURE -->
<div class="bg-gray-50 p-8 rounded-xl border hover:shadow-lg transition">

<h3 class="font-semibold text-lg mb-3">
AI Chatbot Automation
</h3>

<p class="text-gray-600 text-sm">
Automatically reply to WhatsApp leads generated from your ads using intelligent chatbot automation.
</p>

</div>


<!-- FEATURE -->
<div class="bg-gray-50 p-8 rounded-xl border hover:shadow-lg transition">

<h3 class="font-semibold text-lg mb-3">
Real-Time Analytics
</h3>

<p class="text-gray-600 text-sm">
Track ad spend, clicks, conversions and conversations with powerful performance insights.
</p>

</div>


</div>

</div>

</section>


<!-- PLATFORM SECTION -->
<section class="py-20">

<div class="max-w-6xl mx-auto px-6 grid md:grid-cols-2 gap-12 items-center">

<div>

<h2 class="text-3xl font-bold mb-6">

Everything Your Business Needs  
To Scale Meta Advertising

</h2>

<p class="text-gray-600 mb-6">

Parrot Canada combines AI chatbot automation with advanced Meta Ads
management to help businesses capture, nurture and convert leads
efficiently.

</p>

<ul class="space-y-3 text-gray-600">

<li>✔ Campaign & Ad management</li>
<li>✔ WhatsApp lead automation</li>
<li>✔ Creative performance tracking</li>
<li>✔ Budget monitoring & analytics</li>

</ul>

</div>


<div class="bg-green-600 rounded-2xl p-10 text-white shadow-lg">

<h3 class="text-2xl font-semibold mb-4">
Start Managing Your Ads Smarter
</h3>

<p class="text-green-100 mb-6">
Login to your dashboard and take full control of your advertising
and chatbot automation platform.
</p>

<a
href="{{ route('login') }}"
class="bg-white text-green-700 px-6 py-3 rounded-lg font-semibold">

Login Now

</a>

</div>

</div>

</section>


<!-- FOOTER -->
<footer class="bg-gray-900 text-gray-400 py-10">

<div class="max-w-6xl mx-auto px-6 text-center">

<p class="mb-4">
© {{ date('Y') }} Parrot Canada. All rights reserved.
</p>

<div class="space-x-6 text-sm">

<a href="/privacy-policy" class="hover:text-white">Privacy Policy</a>
<a href="/terms-of-service" class="hover:text-white">Terms</a>
<a href="/data-deletion" class="hover:text-white">Data Deletion</a>

</div>

</div>

</footer>

</body>
</html>