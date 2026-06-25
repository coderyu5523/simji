<x-layouts.app :title="$test->title_easy">
  <div class="max-w-3xl mx-auto px-4 py-12">
    <img src="{{ asset($test->thumbnail ?: 'images/tests/placeholder.png') }}" class="h-48 w-full object-cover rounded-2xl bg-cream mb-6" alt="{{ $test->title_easy }}">
    <h1 class="text-2xl font-bold text-deepgreen">{{ $test->title_easy }}</h1>
    <p class="text-sm text-navy/50">{{ $test->title_pro }}</p>
    <p class="mt-4 text-navy/80">{{ $test->description }}</p>
    <dl class="grid grid-cols-2 gap-4 mt-8 text-sm">
      <div><dt class="text-navy/50">대상</dt><dd class="font-medium">{{ $test->target }}</dd></div>
      <div><dt class="text-navy/50">소요시간</dt><dd class="font-medium">약 {{ $test->duration_min }}분</dd></div>
      <div><dt class="text-navy/50">문항수</dt><dd class="font-medium">{{ $test->item_count }}문항</dd></div>
      <div><dt class="text-navy/50">결과형태</dt><dd class="font-medium">신호등 리포트</dd></div>
      <div class="col-span-2"><dt class="text-navy/50">평가영역</dt><dd class="font-medium">{{ implode(', ', $test->areas) }}</dd></div>
    </dl>
    <div class="mt-10 flex gap-3">
      <a href="{{ route('assessment.consent', $test->code) }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3 font-semibold">검사 시작</a>
      <a href="{{ route('catalog.room', $test->room) }}" class="rounded-xl border border-teal text-teal px-6 py-3">다른 검사 보기</a>
    </div>
  </div>
</x-layouts.app>
