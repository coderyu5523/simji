<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '심지 관리자' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $nav = [
        ['label' => '대시보드',   'route' => 'admin.dashboard'],
        ['label' => '회원',       'route' => 'admin.members'],
        ['label' => '주문·결제',  'route' => 'admin.orders'],
        ['label' => '검사',       'route' => 'admin.tests'],
    ];
@endphp
<body class="min-h-screen bg-cream">
    {{-- 임시 배너 --}}
    <div class="bg-signal-red text-white text-center text-xs py-1.5 px-4">
        ⚠️ 임시 관리자 페이지 · 인증 없음(URL만 알면 접근 가능) — 실서비스 전 로그인 가드 필수
    </div>

    <div class="flex min-h-screen">
        {{-- 사이드바 (데스크탑) --}}
        <aside class="hidden md:flex w-56 shrink-0 flex-col bg-deepgreen text-cream">
            <a href="{{ route('admin.dashboard') }}" class="px-6 py-5 text-lg font-extrabold border-b border-cream/10">심지 <span class="text-mint">Admin</span></a>
            <nav class="flex-1 p-3 space-y-1">
                @foreach($nav as $n)
                    <a href="{{ route($n['route']) }}" class="block rounded-lg px-4 py-2.5 text-sm transition {{ request()->routeIs($n['route']) ? 'bg-mint text-deepgreen font-bold' : 'text-cream/80 hover:bg-cream/10' }}">{{ $n['label'] }}</a>
                @endforeach
            </nav>
            <a href="{{ route('home') }}" class="px-6 py-4 text-xs text-cream/50 hover:text-cream border-t border-cream/10">← 사이트로 돌아가기</a>
        </aside>

        {{-- 메인 --}}
        <div class="flex-1 min-w-0">
            {{-- 모바일 상단 nav --}}
            <div class="md:hidden bg-deepgreen text-cream">
                <div class="px-4 pt-3 font-extrabold">심지 <span class="text-mint">Admin</span></div>
                <nav class="flex gap-1 overflow-x-auto px-3 py-2">
                    @foreach($nav as $n)
                        <a href="{{ route($n['route']) }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 text-sm {{ request()->routeIs($n['route']) ? 'bg-mint text-deepgreen font-bold' : 'bg-cream/10 text-cream/80' }}">{{ $n['label'] }}</a>
                    @endforeach
                </nav>
            </div>

            <main class="p-5 md:p-8">
                <h1 class="text-2xl font-extrabold text-deepgreen mb-6">{{ $heading ?? '' }}</h1>
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
