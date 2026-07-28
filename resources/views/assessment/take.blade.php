<x-layouts.app :title="'검사 진행 · '.$test->title_easy">
  <div class="bg-cream">
    {{-- 상단 고정 진행바 --}}
    <div class="sticky top-16 z-40 bg-cream/90 backdrop-blur border-b border-black/5">
      <div class="max-w-2xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between text-xs text-navy/50 mb-1.5">
          <span class="font-semibold text-deepgreen">{{ $test->title_easy }}</span>
          <span><span id="done">0</span> / {{ $test->items->count() }}</span>
        </div>
        <div class="h-2 bg-black/5 rounded-full overflow-hidden"><div id="bar" class="h-2 bg-gradient-to-r from-teal to-mint w-0 transition-all"></div></div>
      </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-8">
      <form method="POST" action="{{ $submitUrl ?? route('assessment.submit', [$test->code, $attempt->id]) }}" id="qform" class="space-y-5">
        @csrf
        @foreach($test->items as $item)
          @php
            $options = is_array($item->options) && count($item->options)
                ? collect($item->options)->map(fn ($label, $i) => ['value' => $i, 'label' => $label])->all()
                : collect(range(1, 5))->map(fn ($v) => ['value' => $v, 'label' => (string) $v])->all();
            $isSafety = $item->area === 'SAF';
          @endphp
          <fieldset class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" data-q>
            <legend class="font-semibold text-navy flex gap-2"><span class="text-teal">{{ $item->no }}.</span> {{ $item->text }}</legend>

            @if($item->timeframe_code === 'PAST_12_MONTHS')
              <p class="text-xs font-semibold text-amber-700 mb-2 mt-2">최근 12개월 동안을 기준으로 답해 줘</p>
            @elseif($item->timeframe_code === 'PAST_2_WEEKS')
              <p class="text-xs text-navy/45 mb-2 mt-2">최근 2주 동안을 기준으로 답해 줘</p>
            @endif

            <div class="grid gap-1.5 mt-5 {{ count($options) === 4 ? 'grid-cols-4' : 'flex justify-between' }}">
              @foreach($options as $opt)
                <label class="flex-1 cursor-pointer">
                  <input type="radio" name="answers[{{ $item->id }}]" value="{{ $opt['value'] }}" class="peer sr-only js-answer" @if($isSafety) data-item-code="{{ $item->item_code }}" @endif required>
                  <span class="block text-center text-xs rounded-xl border border-black/10 py-2.5 px-1 text-navy/60 transition peer-checked:bg-deepgreen peer-checked:text-cream peer-checked:border-deepgreen hover:border-teal">{{ $opt['label'] }}</span>
                </label>
              @endforeach
            </div>

            @if($isSafety)
              <label class="mt-2 inline-flex items-center gap-2 cursor-pointer text-sm text-navy/55">
                <input type="radio" name="answers[{{ $item->id }}]" value="PREFER_NOT"
                       class="js-answer" data-item-code="{{ $item->item_code }}">
                응답하기 어려움
              </label>
            @endif
          </fieldset>
        @endforeach
        <button class="w-full rounded-xl bg-deepgreen text-cream py-4 font-bold shadow-lg hover:brightness-110 transition">제출하고 결과 보기</button>
      </form>
    </div>
  </div>
  <script>
    const total = {{ $test->items->count() }};
    document.getElementById('qform').addEventListener('change', () => {
      const done = document.querySelectorAll('[data-q] input:checked').length;
      document.getElementById('done').textContent = done;
      document.getElementById('bar').style.width = (done/total*100) + '%';
    });
  </script>

  @if($test->scoring_engine === 'oy_msi')
  <div id="safety-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl">
      <h2 id="safety-title" class="text-lg font-extrabold text-deepgreen"></h2>
      <p id="safety-body" class="mt-3 text-sm text-navy/75"></p>
      <div class="mt-5 grid gap-2">
        <a href="tel:109" class="rounded-xl bg-signal-red text-white py-3 text-center font-bold">109 자살예방 상담</a>
        <a href="tel:1388" class="rounded-xl bg-teal text-white py-3 text-center font-bold">1388 청소년 상담</a>
        <button type="button" id="safety-continue"
                class="rounded-xl bg-navy/5 py-3 font-semibold text-navy/70">검사 계속하기</button>
      </div>
    </div>
  </div>

  {{-- resources/js/oymsi-safety-alert.js 를 그대로 인라인한다 (별도 정적 자산 URL이 아니므로
       브라우저 캐시로 인한 미반영 문제가 없다). 같은 파일을 tests/js/oymsi-safety-alert.test.js 가
       직접 검증하므로 화면 코드와 테스트 대상이 항상 같다. --}}
  <script>{!! file_get_contents(resource_path('js/oymsi-safety-alert.js')) !!}</script>
  @endif
</x-layouts.app>
