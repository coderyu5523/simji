<x-layouts.app :title="'simji 심지 · 마음을 검사하고, 삶을 코칭하다'">
  <section class="bg-deepgreen text-cream">
    <div class="max-w-6xl mx-auto px-4 py-20 text-center">
      <h1 class="text-3xl md:text-4xl font-bold leading-snug">마음을 검사하고, 삶을 코칭하다</h1>
      <p class="mt-4 text-cream/80">성인부터 실버 세대까지, 연령별 마음상태를 검사하고 맞춤 솔루션으로 연결합니다.</p>
      <a href="{{ route('catalog.index') }}" class="inline-block mt-8 rounded-xl bg-mint text-deepgreen px-8 py-3 font-semibold">심리검사 시작하기</a>
    </div>
  </section>

  <section class="max-w-6xl mx-auto px-4 py-14">
    <h2 class="text-xl font-bold mb-6">연령별 마음방</h2>
    <div class="grid md:grid-cols-3 gap-6">
      @foreach($rooms as $room)
        <a href="{{ route('catalog.room', $room['code']) }}" class="rounded-2xl bg-white shadow-sm p-6 hover:shadow-md transition">
          <img src="{{ asset('images/rooms/'.$room['code'].'.png') }}" alt="{{ $room['name'] }}" class="h-32 w-full object-cover rounded-xl mb-4 bg-cream">
          <h3 class="font-bold text-lg text-deepgreen">{{ $room['name'] }}</h3>
          <p class="text-sm text-navy/70 mt-1">{{ $room['desc'] }}</p>
        </a>
      @endforeach
    </div>
  </section>

  <section class="max-w-6xl mx-auto px-4 pb-16">
    <h2 class="text-xl font-bold mb-6">오늘의 추천 검사</h2>
    <div class="grid md:grid-cols-3 gap-6">
      @foreach($recommended as $test)
        <x-test-card :test="$test"/>
      @endforeach
    </div>
  </section>
</x-layouts.app>
