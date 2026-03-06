<div class="bg-white border rounded-xl overflow-hidden shadow">

{{-- FACEBOOK HEADER --}}
<div class="flex items-center gap-3 p-3 border-b">

<img
src="https://static.xx.fbcdn.net/rsrc.php/yb/r/hLRJ1GG_y0J.ico"
class="w-8 h-8 rounded-full"
/>

<div class="text-sm">

<div class="font-semibold">
{{ $creative->page_name ?? 'Your Page' }}
</div>

<div class="text-gray-500 text-xs">
Sponsored
</div>

</div>

</div>



{{-- IMAGE / VIDEO --}}
@if($creative->image_url)

<img
src="{{ $creative->image_url }}"
class="w-full"
/>

@endif

@if($creative->video_url)

<video controls class="w-full">
<source src="{{ $creative->video_url }}">
</video>

@endif



{{-- TEXT --}}
<div class="p-4 space-y-3">

@if($creative->body)

<p class="text-sm text-gray-800">
{{ $creative->body }}
</p>

@endif



{{-- HEADLINE --}}
@if($creative->title)

<div class="font-semibold text-sm">
{{ $creative->title }}
</div>

@endif



{{-- CTA --}}
@if($creative->call_to_action)

<button
class="bg-blue-600 text-white px-4 py-2 rounded text-sm"
>

{{ $creative->call_to_action }}

</button>

@endif

</div>

</div>