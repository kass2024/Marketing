<x-app-layout>

@php
$route = Route::currentRouteName();

$user = auth()->user();
$isSuperAdmin = $user && $user->isSuperAdmin();

$unreadCount = \App\Models\Message::where('direction','incoming')
    ->where('is_read',0)
    ->count();
@endphp


<div
x-data="{
openAds: {{ str_contains($route,'admin.accounts')
|| str_contains($route,'admin.campaigns')
|| str_contains($route,'admin.adsets')
|| str_contains($route,'admin.ads')
|| str_contains($route,'admin.creatives')
|| str_contains($route,'admin.analytics') ? 'true' : 'false' }},

openSocial: {{ str_contains($route,'admin.instagram')
|| str_contains($route,'admin.messenger')
|| str_contains($route,'admin.whatsapp') ? 'true' : 'false' }},

openAutomation: {{ str_contains($route,'admin.inbox')
|| str_contains($route,'admin.chatbots')
|| str_contains($route,'admin.faq')
|| str_contains($route,'admin.templates')
|| str_contains($route,'admin.leads') ? 'true' : 'false' }},

openSettings: {{ str_contains($route,'admin.settings')
|| str_contains($route,'admin.users') ? 'true' : 'false' }}
}"
class="min-h-screen bg-gray-100 font-sans">


<div class="flex min-h-screen">


{{-- ================= SIDEBAR ================= --}}
<aside class="w-80 bg-white border-r border-gray-200 flex flex-col shadow-sm">

{{-- LOGO --}}
<div class="h-24 flex items-center px-8 border-b bg-gradient-to-r from-blue-600 to-indigo-600">
<div>
<h2 class="text-2xl font-bold text-white tracking-tight">MetaPanel</h2>
<p class="text-sm text-blue-100 mt-1">Enterprise SaaS</p>
</div>
</div>



{{-- ================= MENU ================= --}}
<nav class="flex-1 overflow-y-auto px-6 py-6 space-y-3 text-base">


{{-- SUPER ADMIN SECTION --}}
@if($isSuperAdmin)

<a href="{{ route('admin.dashboard') }}"
class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition
{{ $route == 'admin.dashboard' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-100 text-gray-700' }}">
📊 Dashboard
</a>


<a href="{{ route('admin.meta.index') }}"
class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition
{{ str_contains($route,'admin.meta') ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-100 text-gray-700' }}">
🏢 Business Manager
</a>



{{-- ADS MANAGEMENT --}}
<div>

<button @click="openAds=!openAds"
class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100">

🎯 Ads Management

<svg :class="openAds ? 'rotate-90':''"
class="w-4 h-4 transition-transform"
fill="none"
stroke="currentColor"
stroke-width="2"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
</svg>

</button>

<div x-show="openAds" x-transition class="pl-6 mt-2 space-y-2">

<a href="{{ route('admin.accounts.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Ad Accounts
</a>

<a href="{{ route('admin.campaigns.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Campaigns
</a>

<a href="{{ route('admin.adsets.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Ad Sets
</a>

<a href="{{ route('admin.creatives.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Creatives
</a>

<a href="{{ route('admin.ads.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Ads
</a>

<a href="{{ route('admin.analytics.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Insights & Reports
</a>

</div>
</div>



{{-- SOCIAL CHANNELS --}}
<div>

<button @click="openSocial=!openSocial"
class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100">

🌍 Social Channels

<svg :class="openSocial ? 'rotate-90':''"
class="w-4 h-4 transition-transform"
fill="none"
stroke="currentColor"
stroke-width="2"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
</svg>

</button>

<div x-show="openSocial" x-transition class="pl-6 mt-2 space-y-2">

@if(Route::has('admin.instagram.index'))
<a href="{{ route('admin.instagram.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Instagram
</a>
@endif

@if(Route::has('admin.messenger.index'))
<a href="{{ route('admin.messenger.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Messenger
</a>
@endif

@if(Route::has('admin.whatsapp.index'))
<a href="{{ route('admin.whatsapp.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
WhatsApp
</a>
@endif

</div>
</div>

@endif




{{-- ================= AUTOMATION ================= --}}
<div>

<button @click="openAutomation=!openAutomation"
class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100">

🤖 Automation & CRM

<svg :class="openAutomation ? 'rotate-90':''"
class="w-4 h-4 transition-transform"
fill="none"
stroke="currentColor"
stroke-width="2"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
</svg>

</button>


<div x-show="openAutomation" x-transition class="pl-6 mt-2 space-y-2">


{{-- Conversations (ALL USERS) --}}
<a href="{{ route('admin.inbox.index') }}"
class="flex justify-between items-center py-2 px-3 rounded-lg hover:bg-gray-100">

<span>💬 Conversations</span>

@if($unreadCount>0)
<span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
{{ $unreadCount }}
</span>
@endif

</a>


@if($isSuperAdmin)

<a href="{{ route('admin.chatbots.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Chatbots
</a>

<a href="{{ route('admin.faq.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
FAQ Knowledge Base
</a>

<a href="{{ route('admin.templates.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Templates
</a>

<a href="{{ route('admin.leads.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Leads CRM
</a>

@endif

</div>
</div>



{{-- SYSTEM + SETTINGS --}}
@if($isSuperAdmin)

<a href="{{ route('admin.system.index') }}"
class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100">
🛠 System Monitor
</a>


<div>

<button @click="openSettings=!openSettings"
class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100">

⚙ Settings

<svg :class="openSettings ? 'rotate-90':''"
class="w-4 h-4 transition-transform"
fill="none"
stroke="currentColor"
stroke-width="2"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
</svg>

</button>


<div x-show="openSettings" x-transition class="pl-6 mt-2 space-y-2">

<a href="{{ route('admin.settings.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
Platform Settings
</a>

<a href="{{ route('admin.users.index') }}"
class="block py-2 px-3 rounded-lg hover:bg-gray-100">
User Management
</a>

</div>
</div>

@endif


</nav>
</aside>



{{-- ================= RIGHT PANEL ================= --}}
<div class="flex-1 flex flex-col">


<header class="bg-white border-b px-12 py-8 shadow-sm">

<div class="flex justify-between items-center">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Meta Enterprise Dashboard
</h1>

<p class="text-sm text-gray-500 mt-2">
{{ now()->format('l, d M Y H:i') }}
</p>
</div>


<div class="flex items-center gap-8">

<span class="text-gray-700">
{{ $user->name }}
</span>

<form method="POST" action="{{ route('logout') }}">
@csrf
<button class="text-red-500 hover:underline">Logout</button>
</form>

</div>

</div>
</header>



{{-- ================= MAIN CONTENT ================= --}}
<main class="flex-1 py-12">

<div class="max-w-7xl mx-auto px-12 space-y-10">


{{-- BUSINESS MANAGER --}}
<div class="bg-white p-10 rounded-2xl border shadow-sm flex justify-between items-center">

<div>

<h3 class="text-xl font-semibold text-gray-900">
Business Manager
</h3>

@if(!empty($platformMeta))

<p class="text-base text-gray-600 mt-3">
Business ID:
<strong>{{ $platformMeta->business_id }}</strong>
</p>

<p class="text-green-600 text-base font-medium mt-2">
Verified & Connected
</p>

@else

<p class="text-red-500 text-base mt-3">
No business connected
</p>

@endif

</div>


<div>

@if(empty($platformMeta))

<a href="{{ route('admin.meta.connect') }}"
class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700">
Connect Business
</a>

@else

<form method="POST" action="{{ route('admin.meta.disconnect') }}">
@csrf
<button
class="bg-red-500 text-white px-6 py-3 rounded-xl shadow hover:bg-red-600">
Disconnect
</button>
</form>

@endif

</div>

</div>


</div>
</main>

</div>
</div>
</div>

</x-app-layout>