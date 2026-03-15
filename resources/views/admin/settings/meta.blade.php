@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto py-10 space-y-8">

<div class="flex justify-between items-center">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Meta Platform Configuration
</h1>

<p class="text-sm text-gray-500">
Meta Ads, Facebook Login and WhatsApp Cloud API configuration
</p>
</div>

<a href="{{ route('admin.settings.index') }}"
class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
Back
</a>

</div>


<div class="bg-white shadow border rounded-2xl">

<table class="w-full">

<thead class="bg-gray-50 text-xs uppercase text-gray-500">

<tr>
<th class="p-4 text-left">Parameter</th>
<th class="p-4 text-left">Value</th>
<th class="p-4 text-right">Actions</th>
</tr>

</thead>

<tbody class="divide-y">

{{-- META APP ID --}}
<tr>

<td class="p-4 font-medium">Meta App ID</td>

<td class="p-4 font-mono text-sm" id="meta_app_id">
{{ env('META_APP_ID') }}
</td>

<td class="p-4 text-right">
<button onclick="copyValue('meta_app_id')" class="text-blue-600 text-sm">Copy</button>
</td>

</tr>


{{-- META APP SECRET --}}
<tr>

<td class="p-4 font-medium">Meta App Secret</td>

<td class="p-4 font-mono text-sm">

<span id="meta_secret_value">••••••••••••</span>

<span id="meta_secret_real" class="hidden">
{{ env('META_APP_SECRET') }}
</span>

</td>

<td class="p-4 text-right flex justify-end gap-4">

<button onclick="toggleSecret('meta_secret')" class="text-gray-600 text-sm">👁</button>

<button onclick="copyValue('meta_secret_real')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- META SYSTEM USER TOKEN --}}
<tr>

<td class="p-4 font-medium">Meta System User Token</td>

<td class="p-4 font-mono text-sm">

<span id="meta_token_value">••••••••••••••••</span>

<span id="meta_token_real" class="hidden">
{{ env('META_SYSTEM_USER_TOKEN') }}
</span>

</td>

<td class="p-4 text-right flex justify-end gap-4">

<button onclick="toggleSecret('meta_token')" class="text-gray-600 text-sm">👁</button>

<button onclick="copyValue('meta_token_real')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- META AD ACCOUNT --}}
<tr>

<td class="p-4 font-medium">Ad Account ID</td>

<td class="p-4 font-mono text-sm" id="meta_ad_account">
{{ env('META_AD_ACCOUNT_ID') }}
</td>

<td class="p-4 text-right">

<button onclick="copyValue('meta_ad_account')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- META PAGE --}}
<tr>

<td class="p-4 font-medium">Facebook Page ID</td>

<td class="p-4 font-mono text-sm" id="meta_page_id">
{{ env('META_PAGE_ID') }}
</td>

<td class="p-4 text-right">

<button onclick="copyValue('meta_page_id')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- META GRAPH VERSION --}}
<tr>

<td class="p-4 font-medium">Graph API Version</td>

<td class="p-4 font-mono text-sm" id="meta_graph">
{{ env('META_GRAPH_VERSION') }}
</td>

<td class="p-4 text-right">

<button onclick="copyValue('meta_graph')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- META REDIRECT --}}
<tr>

<td class="p-4 font-medium">Meta Redirect URI</td>

<td class="p-4 font-mono text-sm" id="meta_redirect">
{{ env('META_REDIRECT_URI') }}
</td>

<td class="p-4 text-right">

<button onclick="copyValue('meta_redirect')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- WHATSAPP BUSINESS --}}
<tr>

<td class="p-4 font-medium">WhatsApp Business ID</td>

<td class="p-4 font-mono text-sm" id="whatsapp_business">
{{ env('WHATSAPP_BUSINESS_ID') }}
</td>

<td class="p-4 text-right">

<button onclick="copyValue('whatsapp_business')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- WHATSAPP PHONE --}}
<tr>

<td class="p-4 font-medium">WhatsApp Phone Number ID</td>

<td class="p-4 font-mono text-sm" id="whatsapp_phone">
{{ env('WHATSAPP_PHONE_NUMBER_ID') }}
</td>

<td class="p-4 text-right">

<button onclick="copyValue('whatsapp_phone')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- WHATSAPP ACCESS TOKEN --}}
<tr>

<td class="p-4 font-medium">WhatsApp Access Token</td>

<td class="p-4 font-mono text-sm">

<span id="whatsapp_token_value">••••••••••••</span>

<span id="whatsapp_token_real" class="hidden">
{{ env('WHATSAPP_ACCESS_TOKEN') }}
</span>

</td>

<td class="p-4 text-right flex justify-end gap-4">

<button onclick="toggleSecret('whatsapp_token')" class="text-gray-600 text-sm">👁</button>

<button onclick="copyValue('whatsapp_token_real')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- VERIFY TOKEN --}}
<tr>

<td class="p-4 font-medium">WhatsApp Verify Token</td>

<td class="p-4 font-mono text-sm" id="verify_token">
{{ env('WHATSAPP_VERIFY_TOKEN') }}
</td>

<td class="p-4 text-right">

<button onclick="copyValue('verify_token')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>


{{-- APP SECRET --}}
<tr>

<td class="p-4 font-medium">WhatsApp App Secret</td>

<td class="p-4 font-mono text-sm">

<span id="whatsapp_secret_value">••••••••••••</span>

<span id="whatsapp_secret_real" class="hidden">
{{ env('WHATSAPP_APP_SECRET') }}
</span>

</td>

<td class="p-4 text-right flex justify-end gap-4">

<button onclick="toggleSecret('whatsapp_secret')" class="text-gray-600 text-sm">👁</button>

<button onclick="copyValue('whatsapp_secret_real')" class="text-blue-600 text-sm">Copy</button>

</td>

</tr>

</tbody>

</table>

</div>

</div>


<script>

function copyValue(id)
{
    const text = document.getElementById(id).innerText;
    navigator.clipboard.writeText(text);
    alert("Copied!");
}

function toggleSecret(prefix)
{
    const visible = document.getElementById(prefix + "_value");
    const real = document.getElementById(prefix + "_real");

    if(real.classList.contains("hidden"))
    {
        real.classList.remove("hidden");
        visible.classList.add("hidden");
    }
    else
    {
        real.classList.add("hidden");
        visible.classList.remove("hidden");
    }
}

</script>

@endsection