@extends('layouts.admin')

@section('content')

@php($branding = \App\Support\PlatformSettings::all())

<div class="max-w-4xl mx-auto py-10 space-y-8 px-4">

<div class="flex flex-wrap items-start justify-between gap-4">
<div>
<h1 class="text-2xl font-bold text-gray-900">
General Settings
</h1>
<p class="text-sm text-gray-500 mt-1">Platform identity and Xander Global Scholars contact details</p>
</div>
</div>

<form method="POST" action="{{ route('admin.settings.general.update') }}" class="space-y-6">

@csrf

<div>
<label class="block text-sm font-medium text-gray-700">
Platform Name
</label>

<input
type="text"
name="platform_name"
value="{{ old('platform_name', config('app.name')) }}"
class="mt-2 w-full border rounded-lg p-3">
<p class="text-xs text-gray-500 mt-1">Update <code class="text-xs">APP_NAME</code> in <code class="text-xs">.env</code> to apply everywhere.</p>
</div>


<div>
<label class="block text-sm font-medium text-gray-700">
Support Email
</label>

<input
type="email"
name="support_email"
value="{{ old('support_email', config('mail.from.address')) }}"
class="mt-2 w-full border rounded-lg p-3">
</div>


<div>
<label class="block text-sm font-medium text-gray-700">
Timezone
</label>

<input
type="text"
name="timezone"
value="{{ old('timezone', config('app.timezone')) }}"
class="mt-2 w-full border rounded-lg p-3">
</div>

<div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-5 space-y-4">
<h2 class="text-sm font-semibold text-indigo-900">Xander Global Scholars</h2>

<div>
<label class="block text-sm font-medium text-gray-700">
Contact name
</label>
<input
type="text"
name="xander_name"
value="{{ old('xander_name', $branding['xander_name']) }}"
required
class="mt-2 w-full border rounded-lg p-3"
placeholder="e.g. Xander Admissions Team">
</div>

<div>
<label class="block text-sm font-medium text-gray-700">
Contact email
</label>
<input
type="email"
name="xander_email"
value="{{ old('xander_email', $branding['xander_email']) }}"
required
class="mt-2 w-full border rounded-lg p-3"
placeholder="contact@xanderglobalscholars.org">
</div>
</div>


<button
type="submit"
class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">

Save Settings

</button>

</form>

</div>

@endsection