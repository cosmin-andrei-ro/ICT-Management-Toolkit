@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    {{ $slot }}

    {{-- AGPL-3.0 §13: users interacting over a network must be offered the source code. --}}
    <footer class="py-6 text-center text-xs text-gray-500 print:hidden">
        {{ config('app.name') }} ·
        <a href="{{ config('app.source_url') }}" class="underline hover:text-gray-700" rel="noopener">{{ __('core::layout.source_code') }}</a>
        · {{ __('core::layout.license') }}
    </footer>
</body>
</html>
