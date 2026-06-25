@php
  $menu = [
    ['label' => '심지소개',  'route' => 'about'],
    ['label' => '심리검사',  'route' => 'catalog.index'],
    ['label' => '강의·코칭', 'route' => 'coaching'],
    ['label' => '기관·단체', 'route' => 'institution'],
    ['label' => '리포트샘플','route' => 'report.sample'],
    ['label' => '고객센터',  'route' => 'support'],
  ];
@endphp
<header class="sticky top-0 z-50 bg-gradient-to-r from-deepgreen via-deepgreen to-teal text-cream shadow-lg">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-3">
    {{-- 로고 --}}
    <a href="{{ route('home') }}" class="flex items-center gap-2.5 group shrink-0">
      <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-mint/20 ring-1 ring-mint/40 group-hover:bg-mint/30 transition">
        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 text-mint" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20c0-5 0-8 6-11-1 6-3 8-6 8z"/><path d="M12 20c0-4-1-6-5-8 1 4 2 6 5 6z"/><path d="M12 20v-3"/>
        </svg>
      </span>
      <span class="leading-tight">
        <span class="block text-lg font-extrabold tracking-tight">simji <span class="text-mint">심지</span></span>
        <span class="hidden lg:block text-[11px] text-cream/70">마음을 검사하고, 삶을 코칭하다</span>
      </span>
    </a>

    {{-- 데스크탑 메뉴 --}}
    <nav class="hidden md:flex items-center gap-0.5 text-sm">
      @foreach($menu as $m)
        <a href="{{ route($m['route']) }}" class="px-3 py-2 rounded-lg hover:bg-cream/10 transition whitespace-nowrap">{{ $m['label'] }}</a>
      @endforeach
    </nav>

    {{-- 우측 인증 + 모바일 토글 --}}
    <div class="flex items-center gap-2 shrink-0">
      @auth
        <a href="{{ route('my.index') }}" class="hidden md:inline px-3 py-2 rounded-lg hover:bg-cream/10 transition text-sm">내 검사함</a>
      @endauth
      @guest
        <a href="{{ route('login') }}" class="hidden sm:inline px-4 py-2 rounded-full bg-mint text-deepgreen font-semibold hover:brightness-105 transition text-sm">로그인</a>
      @endguest

      {{-- 모바일 메뉴 (JS 불필요, details/summary) --}}
      <details class="md:hidden relative">
        <summary class="list-none inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-cream/10 cursor-pointer">
          <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </summary>
        <div class="absolute right-0 mt-2 w-56 rounded-xl bg-deepgreen shadow-xl ring-1 ring-cream/15 py-2 z-50">
          @foreach($menu as $m)
            <a href="{{ route($m['route']) }}" class="block px-4 py-2.5 text-sm hover:bg-cream/10">{{ $m['label'] }}</a>
          @endforeach
          <div class="my-1 border-t border-cream/15"></div>
          @auth <a href="{{ route('my.index') }}" class="block px-4 py-2.5 text-sm hover:bg-cream/10">내 검사함</a> @endauth
          @guest <a href="{{ route('login') }}" class="block px-4 py-2.5 text-sm font-semibold text-mint hover:bg-cream/10">로그인</a> @endguest
        </div>
      </details>
    </div>
  </div>
  <div class="h-0.5 bg-gradient-to-r from-mint via-cream/40 to-transparent"></div>
</header>
