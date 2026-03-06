<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

```
{{-- Core Meta --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

{{-- Security --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Dynamic Page Title --}}
<title>
    @hasSection('title')
        @yield('title') | {{ config('app.name', 'MetaPanel') }}
    @else
        {{ config('app.name', 'MetaPanel') }}
    @endif
</title>

{{-- SEO Meta --}}
<meta name="description" content="@yield('meta_description', 'Meta Ads Management Platform')">
<meta name="author" content="{{ config('app.name', 'MetaPanel') }}">

{{-- Open Graph --}}
<meta property="og:title" content="@yield('title', config('app.name','MetaPanel'))">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name','MetaPanel') }}">

{{-- Performance --}}
<meta http-equiv="X-UA-Compatible" content="IE=edge">

{{-- Fonts --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

{{-- Application Styles & Scripts --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Page Specific Styles --}}
@stack('styles')

{{-- Global Layout Styles --}}
<style>
    html {
        scroll-behavior: smooth;
    }

    body {
        min-height: 100vh;
    }
</style>
```

</head>

<body class="font-sans antialiased bg-gray-100 text-gray-900">

```
{{-- Support both Blade Components and Classic Layouts --}}
{{-- DO NOT REMOVE (used by your project) --}}
@isset($slot)
    {{ $slot }}
@else
    @yield('content')
@endisset

{{-- Page Specific Scripts --}}
@stack('scripts')
```

</body>

</html>
