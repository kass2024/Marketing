<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @hasSection('title')
            @yield('title') | {{ config('app.name', 'Xander Global Scholars') }}
        @else
            {{ config('app.name', 'Xander Global Scholars') }}
        @endif
    </title>

    <meta name="description" content="@yield('meta_description', 'Meta Ads management for Xander Global Scholars')">
    <meta name="author" content="{{ config('app.name', 'Xander Global Scholars') }}">

    <meta property="og:title" content="@yield('title', config('app.name','Xander Global Scholars'))">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name','Xander Global Scholars') }}">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')

    <style>
        html { scroll-behavior: smooth; }
        body { min-height: 100vh; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100 text-gray-900">
    @isset($slot)
        {{ $slot }}
    @else
        @yield('content')
    @endisset

    @stack('scripts')
</body>
</html>
