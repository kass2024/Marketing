@extends('layouts.app')

@section('content')

<div class="max-w-4xl mx-auto py-10 space-y-8">

<h1 class="text-2xl font-bold text-gray-900">
General Settings
</h1>

<form method="POST" action="{{ route('admin.settings.general.update') }}" class="space-y-6">

@csrf

<div>
<label class="block text-sm font-medium text-gray-700">
Platform Name
</label>

<input
type="text"
name="platform_name"
value="{{ config('app.name') }}"
class="mt-2 w-full border rounded-lg p-3">
</div>


<div>
<label class="block text-sm font-medium text-gray-700">
Support Email
</label>

<input
type="email"
name="support_email"
value="{{ config('mail.from.address') }}"
class="mt-2 w-full border rounded-lg p-3">
</div>


<div>
<label class="block text-sm font-medium text-gray-700">
Timezone
</label>

<input
type="text"
name="timezone"
value="{{ config('app.timezone') }}"
class="mt-2 w-full border rounded-lg p-3">
</div>


<button
class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">

Save Settings

</button>

</form>

</div>

@endsection