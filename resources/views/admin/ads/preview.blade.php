<div class="bg-white border rounded-xl overflow-hidden shadow max-w-lg mx-auto">

{{-- FACEBOOK HEADER --}}
<div class="flex items-center gap-3 p-4 border-b bg-gray-50">

<img
src="https://static.xx.fbcdn.net/rsrc.php/yb/r/hLRJ1GG_y0J.ico"
class="w-9 h-9 rounded-full"
alt="Page"
/>

<div class="text-sm">

<div class="font-semibold text-gray-900">
{{ $creative->page_name ?? 'Your Page' }}
</div>

<div class="text-gray-500 text-xs flex items-center gap-1">
Sponsored
<span>•</span>
<span>Just now</span>
</div>

</div>

</div>



{{-- MEDIA --}}
@if($creative->image_url)

<img
src="{{ str_starts_with($creative->image_url,'http') 
        ? $creative->image_url 
        : asset('storage/'.$creative->image_url) }}"
class="w-full object-cover"
/>

@endif


@if($creative->video_url)

<video controls class="w-full">
<source src="{{ $creative->video_url }}">
</video>

@endif



{{-- CONTENT --}}
<div class="p-4 space-y-3">

{{-- BODY --}}
@if($creative->body)

<p class="text-sm text-gray-800 leading-relaxed">
{{ $creative->body }}
</p>

@endif



{{-- HEADLINE --}}
@if($creative->title)

<div class="font-semibold text-gray-900 text-sm">
{{ $creative->title }}
</div>

@endif



{{-- DESTINATION URL --}}
@if($creative->destination_url)

<div class="text-xs text-gray-500 truncate">
{{ parse_url($creative->destination_url, PHP_URL_HOST) }}
</div>

@endif



{{-- CTA --}}
@if($creative->call_to_action)

<div class="pt-2">

<a
href="{{ $creative->destination_url ?? '#' }}"
target="_blank"
class="inline-block bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition"
>

{{ str_replace('_',' ', $creative->call_to_action) }}

</a>

</div>

@endif

</div>

</div>