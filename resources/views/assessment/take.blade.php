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
            $legacy5pt = ['전혀 아니다', '아니다', '보통', '그렇다', '매우 그렇다'];
            if (is_array($item->options) && count($item->options)) {
                $options = collect($item->options)->map(fn ($label, $i) => ['value' => $i, 'label' => $label])->all();
            } elseif ($test->scoring_engine === 'oy_msi') {
                // OY_MSI 문항은 반드시 4점 척도 options 를 갖는다(Task 2 시딩). 없으면 시딩 누락이고,
                // 조용히 1~5로 렌더하면 SAF 문항의 0-based 값이 깨져 안전등급 계산이 조용히 틀어진다 — 드러낸다.
                throw new \RuntimeException(
                    "OY_MSI 문항 {$item->item_code}(id={$item->id})에 options가 없습니다 — ".
                    '안전등급 계산이 어긋날 수 있어 1~5로 조용히 대체하지 않습니다.'
                );
            } else {
                $options = collect(range(1, 5))->map(fn ($v) => ['value' => $v, 'label' => $legacy5pt[$v - 1]])->all();
            }
            $isSafety = $item->area === 'SAF';
          @endphp
          {{-- 문항 텍스트를 legend 로 두면 브라우저가 fieldset 테두리 위에 얹어 렌더한다(기본 동작).
               박스의 p-6 패딩도 legend 에는 걸리지 않아 흰 박스와 배경 경계에 걸쳐 보였다.
               일반 요소로 바꿔 박스 안에 흐르게 하고, 그룹 이름은 aria-labelledby 로 유지한다
               (라디오 묶음의 이름이 사라지면 스크린리더에서 문항을 알 수 없다). --}}
          <fieldset class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" data-q
                    aria-labelledby="q-label-{{ $item->id }}">
            <p id="q-label-{{ $item->id }}" class="font-semibold text-navy flex gap-2"><span class="text-teal">{{ $item->no }}.</span> {{ $item->text }}</p>

            {{-- 기간 안내는 따로 붙이지 않는다 (TakeTimeframeTest 가 근거를 고정한다).
                 12개월 기준 문항(SAF05·SAF06)은 문항 텍스트에 "최근 12개월 동안"이 들어 있고,
                 2주 기준은 안내 화면이 알린다. --}}

            <div class="gap-1.5 mt-5 {{ count($options) === 4 ? 'grid grid-cols-4' : 'flex justify-between' }}">
              @foreach($options as $opt)
                <label class="flex-1 cursor-pointer">
                  <input type="radio" name="answers[{{ $item->id }}]" value="{{ $opt['value'] }}" class="peer sr-only" required>
                  <span class="block text-center text-xs rounded-xl border border-black/10 py-2.5 px-1 text-navy/60 transition peer-checked:bg-deepgreen peer-checked:text-cream peer-checked:border-deepgreen hover:border-teal">{{ $opt['label'] }}</span>
                </label>
              @endforeach
            </div>

            @if($isSafety)
              <label class="mt-2 inline-flex items-center gap-2 cursor-pointer text-sm text-navy/55">
                <input type="radio" name="answers[{{ $item->id }}]" value="PREFER_NOT">
                응답하기 어려움
              </label>
            @endif
          </fieldset>
        @endforeach
        <button data-submit-once class="w-full rounded-xl bg-deepgreen text-cream py-4 font-bold shadow-lg hover:brightness-110 transition disabled:opacity-60">제출하고 결과 보기</button>
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

    // 이중 제출 방지. 버튼을 두 번 누르면 채점 요청이 두 번 나가고, 두 번째는 서버가
    // 결과로 흘려보내지만 애초에 보내지 않는 것이 낫다. submit 이벤트가 이미 발생한
    // 뒤에 비활성화하므로 폼 전송 자체는 정상 진행된다(버튼에 name 이 없어 값도 필요없다).
    (function () {
      var form = document.getElementById('qform');
      var btn = form.querySelector('[data-submit-once]');
      var sent = false;
      form.addEventListener('submit', function (e) {
        if (sent) { e.preventDefault(); return; }
        sent = true;
        if (btn) { btn.disabled = true; btn.textContent = '제출 중…'; }
      });
    })();
  </script>

  {{-- 검사 중 안전 안내(모달·배너)는 두지 않는다. OY_MSI 는 표준화 전 데이터 수집용
       사전검사이고, 응답 중 개입 장치를 얹지 않기로 했다(2026-07-31 결정).
       안전 평가 자체는 그대로다 — SafetyEvaluator 가 서버에서 등급을 매기고,
       결과 화면(oymsi/result.blade.php)이 안전 안내를 맨 위에 보여준다. --}}
</x-layouts.app>
