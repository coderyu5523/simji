<header class="bg-deepgreen text-cream">
  <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
    <a href="{{ route('home') }}" class="text-xl font-bold">simji <span class="text-mint">심지</span></a>
    <nav class="flex gap-4 text-sm">
      <a href="{{ route('catalog.index') }}">심리검사</a>
      @auth <a href="{{ route('my.index') }}">내 검사함</a> @endauth
      @guest <a href="{{ route('login') }}">로그인</a> @endguest
    </nav>
  </div>
</header>
