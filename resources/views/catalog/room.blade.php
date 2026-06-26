<x-layouts.app :title="$room['name'].' · 심리검사'">
  <section class="relative overflow-hidden bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-mint/10"></div>
    <div class="relative max-w-6xl mx-auto px-4 py-14">
      <a href="{{ route('catalog.index') }}" class="text-cream/70 text-sm hover:text-mint transition">← 전체 연령방</a>
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mt-4 mb-3">연령별 마음방</span>
      <h1 class="text-2xl md:text-4xl font-extrabold">{{ $room['name'] }} 방</h1>
      <p class="mt-3 text-cream/80">{{ $room['desc'] }}</p>
      <div class="mt-5 flex flex-wrap gap-2">
        @foreach($room['tags'] as $tag)
          <span class="rounded-full bg-cream/10 text-cream/90 px-3 py-1 text-xs">#{{ $tag }}</span>
        @endforeach
      </div>
    </div>
  </section>

  <section class="bg-cream">
    <div class="max-w-6xl mx-auto px-4 py-14">
      @php $planned = $room['planned_tests'] ?? []; @endphp
      @if($tests->isEmpty() && empty($planned))
        <div class="rounded-3xl bg-white p-12 text-center shadow-sm">
          <p class="text-navy/60">준비 중인 검사입니다. 곧 만나보실 수 있어요.</p>
          <a href="{{ route('catalog.index') }}" class="inline-block mt-5 rounded-xl border border-teal text-teal px-6 py-2.5 font-semibold hover:bg-mint/30 transition">다른 방 보기</a>
        </div>
      @else
        <div class="grid md:grid-cols-3 gap-6">
          {{-- 활성 검사 (응시 가능) --}}
          @foreach($tests as $test) <x-test-card :test="$test"/> @endforeach

          {{-- 준비중 검사 (응시 불가) --}}
          @foreach($planned as $p)
            <div class="rounded-2xl bg-white/60 ring-1 ring-black/5 p-5 opacity-80">
              <div class="h-28 w-full rounded-xl mb-3 bg-gradient-to-br from-cream to-mint/30 flex items-center justify-center">
                <span class="text-navy/30 text-sm">준비중</span>
              </div>
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="font-semibold text-navy/50">{{ $p['name'] }}</h3>
                <span class="rounded-full bg-black/5 text-navy/50 text-[11px] px-2 py-0.5">준비중</span>
              </div>
              <p class="text-xs text-navy/40 mt-1">{{ $p['target'] }}</p>
              @if($p['guardian'])
                <span class="inline-block mt-2 rounded-full bg-signal-yellow/20 text-amber-700 text-[11px] px-2 py-0.5">보호자 동의 필요</span>
              @endif
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </section>
</x-layouts.app>
