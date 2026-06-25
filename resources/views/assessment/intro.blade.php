<x-layouts.app :title="'안내 · '.$test->title_easy">
  <div class="max-w-2xl mx-auto px-4 py-12 text-center">
    <img src="{{ asset('images/etc/intro.png') }}" class="h-40 mx-auto bg-cream rounded-2xl mb-6" alt="검사 안내">
    <h1 class="text-xl font-bold text-deepgreen">{{ $test->title_easy }}</h1>
    <ul class="mt-6 text-navy/80 text-sm space-y-2">
      <li>약 {{ $test->duration_min }}분 · {{ $test->item_count }}문항</li>
      <li>정답은 없습니다. 최근 2주간 나에게 더 가까운 쪽을 솔직하게 선택하세요.</li>
      <li>한 번 시작하면 끝까지 응답하는 것을 권장합니다.</li>
    </ul>
    <form method="POST" action="{{ route('assessment.start', $test->code) }}" class="mt-8">
      @csrf
      <button class="rounded-xl bg-deepgreen text-cream px-10 py-3 font-semibold">검사 시작</button>
    </form>
  </div>
</x-layouts.app>
