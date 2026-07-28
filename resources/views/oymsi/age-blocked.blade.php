<x-layouts.app :title="'안내 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      @if($reason === \App\Services\OyMsi\AgeGate::OUT_OF_RANGE)
        <h1 class="text-2xl font-extrabold text-deepgreen">이 검사의 대상 나이가 아니야</h1>
        <p class="mt-3 text-navy/70">
          이 검사는 만 {{ $test->min_age }}~{{ $test->max_age }}세 청소년을 위한 검사야.
          다른 검사를 찾아볼 수 있어.
        </p>
        <a href="{{ route('catalog.index') }}"
           class="mt-6 inline-block rounded-xl bg-deepgreen text-cream px-6 py-3 font-bold">다른 검사 보기</a>

      @elseif($reason === \App\Services\OyMsi\AgeGate::GUARDIAN_PERSONAL)
        <h1 class="text-2xl font-extrabold text-deepgreen">기관을 통해 응시할 수 있어</h1>
        <p class="mt-3 text-navy/70">
          만 {{ $test->guardian_consent_below_age }}세 미만은 법에 따라 보호자(법정대리인)의 동의가 확인되어야 검사할 수 있어.
          가까운 기관에 이야기하면 검사 링크를 받아서 바로 할 수 있어.
        </p>
        <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 space-y-2 text-sm">
          <p class="font-bold text-deepgreen">도움받을 수 있는 곳</p>
          <p>학교 밖 청소년 지원센터 <b>꿈드림</b></p>
          <p>청소년상담 <a href="tel:1388" class="font-bold text-teal">1388</a> · 24시간 365일</p>
          <p>자살예방 상담 <a href="tel:109" class="font-bold text-teal">109</a> · 24시간</p>
        </div>

      @else
        <h1 class="text-2xl font-extrabold text-deepgreen">담당자에게 문의해 줘</h1>
        <p class="mt-3 text-navy/70">
          만 {{ $test->guardian_consent_below_age }}세 미만은 보호자(법정대리인) 동의가 확인되어야 검사할 수 있어.
          이 링크를 준 담당자에게 이야기하면 확인 후 다시 안내해 줄 거야.
        </p>
        <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 space-y-2 text-sm">
          <p>청소년상담 <a href="tel:1388" class="font-bold text-teal">1388</a></p>
          <p>자살예방 상담 <a href="tel:109" class="font-bold text-teal">109</a></p>
        </div>
      @endif
    </div>
  </div>
</x-layouts.app>
