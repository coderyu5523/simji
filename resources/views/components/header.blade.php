<header class="sticky top-0 z-50 bg-gradient-to-r from-deepgreen via-deepgreen to-teal text-cream shadow-lg">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="{{ route('home') }}" class="flex items-center gap-2.5 group">
      <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-mint/20 ring-1 ring-mint/40 group-hover:bg-mint/30 transition">
        {{-- 잎/새싹 마크 — "마음·성장" 모티프 --}}
        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 text-mint" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20c0-5 0-8 6-11-1 6-3 8-6 8z"/>
          <path d="M12 20c0-4-1-6-5-8 1 4 2 6 5 6z"/>
          <path d="M12 20v-3"/>
        </svg>
      </span>
      <span class="leading-tight">
        <span class="block text-lg font-extrabold tracking-tight">simji <span class="text-mint">심지</span></span>
        <span class="hidden sm:block text-[11px] text-cream/70">마음을 검사하고, 삶을 코칭하다</span>
      </span>
    </a>
    <nav class="flex items-center gap-1 sm:gap-2 text-sm">
      <a href="{{ route('catalog.index') }}" class="px-3 py-2 rounded-lg hover:bg-cream/10 transition">심리검사</a>
      @auth
        <a href="{{ route('my.index') }}" class="px-3 py-2 rounded-lg hover:bg-cream/10 transition">내 검사함</a>
      @endauth
      @guest
        <a href="{{ route('login') }}" class="ml-1 px-4 py-2 rounded-full bg-mint text-deepgreen font-semibold hover:brightness-105 transition">로그인</a>
      @endguest
    </nav>
  </div>
  <div class="h-0.5 bg-gradient-to-r from-mint via-cream/40 to-transparent"></div>
</header>
