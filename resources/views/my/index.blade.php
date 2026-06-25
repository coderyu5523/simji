<x-layouts.app :title="'내 검사함'">
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-3xl mx-auto px-4 py-12">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-3">내 기록</span>
      <h1 class="text-2xl md:text-3xl font-extrabold">내 검사함</h1>
      <p class="mt-2 text-cream/80">받은 검사 결과를 다시 확인할 수 있어요.</p>
    </div>
  </section>

  <div class="bg-cream min-h-[40vh]">
    <div class="max-w-3xl mx-auto px-4 py-12">
      @if($attempts->isEmpty())
        <div class="rounded-3xl bg-white p-12 text-center shadow-sm ring-1 ring-black/5">
          <img src="{{ asset('images/etc/empty.png') }}" class="h-32 w-full max-w-xs mx-auto object-cover rounded-2xl mb-5" alt="">
          <p class="text-navy/60">아직 받은 검사가 없어요.<br>마음방에서 검사를 시작해 보세요.</p>
          <a href="{{ route('catalog.index') }}" class="inline-block mt-5 rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">심리검사 보러가기</a>
        </div>
      @else
        <ul class="space-y-3">
          @foreach($attempts as $a)
            <li class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5 flex items-center justify-between hover:shadow-md transition">
              <div>
                <p class="font-bold text-deepgreen">{{ $a->test->title_easy }}</p>
                <p class="text-xs text-navy/40 mt-0.5">{{ $a->submitted_at?->format('Y. m. d') }}</p>
              </div>
              <div class="flex items-center gap-4">
                @if($a->result) <x-signal-badge :signal="$a->result->overall_signal"/> @endif
                <a href="{{ route('result.show', $a->id) }}" class="text-sm font-semibold text-teal hover:text-deepgreen transition">결과 보기 →</a>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</x-layouts.app>
