<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'simji 심지' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cream flex flex-col justify-center items-center px-4 py-10">
    <div class="w-full max-w-md">
        {{-- 로고 --}}
        <a href="{{ route('home') }}" class="flex items-center justify-center gap-2.5 mb-6 group">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-deepgreen ring-1 ring-mint/40 group-hover:brightness-110 transition">
                <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6 text-mint" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20c0-5 0-8 6-11-1 6-3 8-6 8z"/><path d="M12 20c0-4-1-6-5-8 1 4 2 6 5 6z"/><path d="M12 20v-3"/>
                </svg>
            </span>
            <span class="text-2xl font-extrabold tracking-tight text-deepgreen">simji <span class="text-teal">심지</span></span>
        </a>

        <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-black/5">
            {{ $slot }}
        </div>

        <p class="mt-6 text-center text-xs text-navy/40">마음을 검사하고, 삶을 코칭하다</p>
    </div>
</body>
</html>
