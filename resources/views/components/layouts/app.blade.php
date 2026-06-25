<!DOCTYPE html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'simji 심지' }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head><body class="min-h-screen flex flex-col">
<x-header/>
<main class="flex-1">{{ $slot }}</main>
<x-footer/>
</body></html>
