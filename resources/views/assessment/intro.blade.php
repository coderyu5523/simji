<x-layouts.app :title="'안내 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-12 text-center">
      <p class="text-sm text-teal font-semibold"><span class="text-navy/30">1 동의 ·</span> 2 안내 · <span class="text-navy/30">3 검사</span></p>
      <img src="{{ asset('images/etc/intro.png') }}" class="h-44 w-full max-w-md mx-auto object-cover rounded-3xl shadow-lg ring-1 ring-black/5 mt-6 mb-6" alt="검사 안내">
      <h1 class="text-2xl font-extrabold text-deepgreen">{{ $test->title_easy }}</h1>
      <p class="text-navy/50 text-sm mt-1">약 {{ $test->duration_min }}분 · {{ $test->item_count }}문항</p>

      <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 text-left space-y-3 text-sm text-navy/70">
        <p class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-teal shrink-0"></span>정답은 없습니다. 최근 2주간 나에게 더 가까운 쪽을 솔직하게 선택하세요.</p>
        <p class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-teal shrink-0"></span>한 번 시작하면 끝까지 응답하는 것을 권장합니다.</p>
        <p class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-teal shrink-0"></span>결과는 신호등 리포트로 바로 확인할 수 있습니다.</p>
      </div>

      <form method="POST" action="{{ route('assessment.start', $test->code) }}" class="mt-8">
        @csrf
        <button class="rounded-xl bg-deepgreen text-cream px-12 py-3.5 font-bold shadow-lg hover:brightness-110 transition">검사 시작</button>
      </form>
    </div>
  </div>
</x-layouts.app>
