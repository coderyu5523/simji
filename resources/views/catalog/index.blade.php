<x-layouts.app :title="'심리검사 · simji 심지'">
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-6xl mx-auto px-4 py-14 text-center">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-4">심리검사</span>
      <h1 class="text-2xl md:text-4xl font-extrabold">당신에게 맞는 마음검사를 선택하세요</h1>
      <p class="mt-3 text-cream/80">연령방 또는 고민으로 손쉽게 찾을 수 있어요.</p>
    </div>
  </section>

  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-14">
      <div class="text-center mb-8">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-2">연령방으로 찾기</span>
        <h2 class="text-xl md:text-2xl font-extrabold text-deepgreen">연령별 마음방</h2>
      </div>
      <div class="grid md:grid-cols-3 gap-6">
        @foreach($rooms as $room)
          <a href="{{ route('catalog.room', $room['code']) }}" class="group rounded-3xl bg-cream/60 ring-1 ring-black/5 overflow-hidden hover:-translate-y-1 hover:shadow-xl transition">
            <div class="overflow-hidden"><img src="{{ asset('images/rooms/'.$room['code'].'.png') }}" class="h-40 w-full object-cover group-hover:scale-105 transition duration-500" alt="{{ $room['name'] }}"></div>
            <div class="p-6">
              <h3 class="font-bold text-lg text-deepgreen">{{ $room['name'] }}</h3>
              <p class="text-sm text-navy/60 mt-1">{{ $room['desc'] }}</p>
              <span class="inline-flex items-center gap-1 text-sm font-semibold text-teal mt-4">방 입장 <span class="group-hover:translate-x-1 transition">→</span></span>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  </section>

  <section class="bg-cream">
    <div class="max-w-6xl mx-auto px-4 py-14 text-center">
      <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-2">고민으로 찾기</span>
      <h2 class="text-xl md:text-2xl font-extrabold text-deepgreen">어떤 게 가장 신경 쓰이세요?</h2>
      <div class="flex flex-wrap justify-center gap-2.5 mt-6">
        @foreach(collect($rooms)->flatMap(fn($r) => $r['tags'])->unique() as $tag)
          <span class="rounded-full bg-white ring-1 ring-black/5 text-deepgreen px-5 py-2 text-sm">#{{ $tag }}</span>
        @endforeach
      </div>
    </div>
  </section>
</x-layouts.app>
