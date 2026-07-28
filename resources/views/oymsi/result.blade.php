{{--
  OY_MSI 청소년용 결과 화면.
  $sections 는 ReportComposer 가 005 부록1 §2 순서(안전 → 종합 → 영역별 → 상위3 →
  강점 → 실천 → 재검 → 고지문)로 이미 정렬해 준다. 여기서는 순서를 바꾸지 않는다.
  SAF(자해·자살 안전) 요인은 FACTORS 에 애초에 담기지 않으므로 원점수가 찍힐 곳이 없다.
--}}
@php
  $bandText = ['GREEN' => 'text-signal-green', 'YELLOW' => 'text-signal-yellow', 'RED' => 'text-signal-red'];
  $bandBg = ['GREEN' => 'bg-signal-green', 'YELLOW' => 'bg-signal-yellow', 'RED' => 'bg-signal-red'];
  $bandLabel = ['GREEN' => '초록', 'YELLOW' => '노랑', 'RED' => '빨강'];
@endphp

<x-layouts.app :title="'검사 결과 · '.$test->title_easy">
<div class="bg-cream min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-10 space-y-6">

    <div>
      <p class="text-sm text-navy/50">{{ $test->title_easy }}</p>
      <h1 class="mt-1 text-2xl font-extrabold text-deepgreen">{{ $attempt->nickname }}의 마음상태</h1>
      <p class="mt-1 text-sm text-navy/50">{{ optional($attempt->submitted_at)->format('Y년 n월 j일') }} 검사</p>
    </div>

    @foreach($sections as $s)

      @if($s['type'] === 'SAFETY_NOTICE')
        {{-- 안전 안내는 언제나 맨 위. 자살안전(S) 문안 다음에 환경위험(E) 문안이 온다. --}}
        <section class="rounded-3xl bg-red-50 ring-2 ring-signal-red/40 p-6">
          <h2 class="font-bold text-signal-red">먼저 읽어야 할 안내</h2>

          <div class="mt-3">
            @include('oymsi.partials.lines', ['lines' => $s['safety_lines']])
          </div>

          @if($s['environment_lines'])
            <div class="mt-5 border-t border-signal-red/20 pt-4">
              <p class="text-xs font-semibold text-signal-red/80">주변 환경 안전</p>
              <div class="mt-2">
                @include('oymsi.partials.lines', ['lines' => $s['environment_lines']])
              </div>
            </div>
          @endif

          <div class="mt-5 grid grid-cols-2 gap-2">
            <a href="tel:109" class="rounded-xl bg-signal-red text-white py-3 text-center font-bold">자살예방 상담전화 109</a>
            <a href="tel:1388" class="rounded-xl bg-teal text-white py-3 text-center font-bold">청소년상담 1388</a>
            <a href="tel:112" class="rounded-xl bg-navy/10 py-3 text-center font-semibold">경찰 112</a>
            <a href="tel:119" class="rounded-xl bg-navy/10 py-3 text-center font-semibold">구급 119</a>
          </div>
        </section>

      @elseif($s['type'] === 'OVERALL')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <p class="text-sm text-navy/50">종합 마음상태</p>
          <p class="mt-1 text-3xl font-extrabold {{ $bandText[$s['band']] }}">{{ $bandLabel[$s['band']] }}</p>
          <p class="text-sm text-navy/50">전체 위험지수 {{ $s['risk_index'] }}점</p>
          @if($s['has_safety_alert'])
            {{-- 007 §68 "안전경보가 최종판정보다 우선한다" / §246 "전체 지수는 특정 요인의
                 고위험을 상쇄할 수 있다". 종합 신호등만 크게 남지 않도록 한 줄 덧붙인다. --}}
            <p class="mt-3 rounded-2xl bg-signal-red/10 px-4 py-3 text-sm font-semibold text-signal-red">
              이 종합 신호에는 안전에 관한 문항이 들어가 있지 않아. 위에 있는 안전 안내를 먼저 읽어 줘.
            </p>
          @endif
          @if($s['score_status'] !== 'COMPLETE')
            <p class="mt-2 text-xs text-signal-yellow">응답하지 않은 문항이 있어 일부 영역은 참고용으로만 봐야 해.</p>
          @endif
          <p class="mt-3 text-navy/80 leading-relaxed">{!! nl2br(e($s['text'])) !!}</p>
        </section>

      @elseif($s['type'] === 'FACTORS')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <h2 class="font-bold text-deepgreen">영역별 마음상태</h2>
          <div class="mt-4 space-y-3">
            @foreach($s['items'] as $f)
              <div>
                <div class="flex justify-between text-sm">
                  <span class="text-navy/80">{{ $f['name'] }}</span>
                  <span class="text-navy/50">
                    @if($f['raw'] === null)
                      측정 안 됨
                    @else
                      {{ $f['raw'] }}/{{ $f['max'] }}
                    @endif
                  </span>
                </div>
                <div class="mt-1 h-2 rounded-full bg-navy/10">
                  <div class="h-2 rounded-full {{ $bandBg[$f['band']] ?? 'bg-navy/20' }}"
                       style="width: {{ $f['risk_index'] ?? 0 }}%"></div>
                </div>
              </div>
            @endforeach
          </div>
          <p class="mt-4 text-xs text-navy/40">막대는 0~18점을 100으로 환산한 값이야.</p>
        </section>

      @elseif($s['type'] === 'PRIORITY')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 space-y-6">
          <h2 class="font-bold text-deepgreen">지금 먼저 살펴볼 3가지</h2>
          @foreach($s['items'] as $p)
            <div>
              <p class="font-bold text-navy">
                {{ $p['rank'] }}. {{ $p['name'] }}
                <span class="ml-1 align-middle text-xs font-semibold {{ $bandText[$p['band']] ?? 'text-navy/50' }}">
                  {{ $bandLabel[$p['band']] ?? '' }}
                </span>
              </p>
              <p class="mt-1 text-sm text-navy/75 leading-relaxed">{!! nl2br(e($p['meaning'])) !!}</p>
              <div class="mt-3">
                @include('oymsi.partials.lines', ['lines' => $p['actions']])
              </div>
              @if(!empty($p['avoid']))
                <p class="mt-4 text-sm font-semibold text-signal-red">피해야 할 반응</p>
                @include('oymsi.partials.lines', ['lines' => $p['avoid']])
              @endif
            </div>
          @endforeach
        </section>

      @elseif($s['type'] === 'STRENGTH')
        <section class="rounded-3xl bg-mint/30 p-6">
          <h2 class="font-bold text-deepgreen">나에게 남아 있는 강점</h2>
          <ul class="mt-2 space-y-1 pl-5 list-disc text-sm text-navy/80">
            @foreach($s['items'] as $t)<li class="marker:text-teal">{{ $t }}</li>@endforeach
          </ul>
        </section>

      @elseif($s['type'] === 'SOLUTIONS')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <h2 class="font-bold text-deepgreen">이번 주 작은 실천</h2>
          @foreach($s['items'] as $sol)
            <div class="mt-4">
              <p class="text-sm font-semibold text-navy">{{ $sol['title'] }}</p>
              @include('oymsi.partials.lines', ['lines' => $sol['steps']])
            </div>
          @endforeach
        </section>

      @elseif($s['type'] === 'RECHECK')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <h2 class="font-bold text-deepgreen">다시 확인할 시점</h2>
          <p class="mt-1 text-sm text-navy/70">{{ $s['days'] }}일 뒤에 다시 해보면 변화를 확인할 수 있어.</p>
        </section>

      @elseif($s['type'] === 'DISCLAIMER')
        <section class="space-y-1 text-xs text-navy/50 leading-relaxed">
          @foreach($s['lines'] as $line)<p>{{ $line }}</p>@endforeach
        </section>
      @endif

    @endforeach

    {{-- 도움받을 수 있는 곳 — 설계 §5.1 #6 (109 · 1388 · 112 · 119 + 꿈드림).
         안전등급과 관계없이 언제나 보인다. 출처: 005 18페이지 "도움받을 수 있는 곳". --}}
    <section class="rounded-3xl bg-deepgreen/5 ring-1 ring-deepgreen/10 p-6">
      <h2 class="font-bold text-deepgreen">도움받을 수 있는 곳</h2>
      <p class="mt-1 text-sm text-navy/70">혼자 참지 않아도 돼. 아래는 언제든 연락할 수 있는 곳이야.</p>

      <div class="mt-4 grid grid-cols-2 gap-2">
        <a href="tel:109" class="rounded-xl bg-signal-red text-white py-3 text-center font-bold">자살예방 상담 109</a>
        <a href="tel:1388" class="rounded-xl bg-teal text-white py-3 text-center font-bold">청소년상담 1388</a>
        <a href="tel:112" class="rounded-xl bg-navy/10 py-3 text-center font-semibold">경찰 112</a>
        <a href="tel:119" class="rounded-xl bg-navy/10 py-3 text-center font-semibold">구급·응급 119</a>
      </div>

      <ul class="mt-4 space-y-1 text-sm text-navy/70">
        <li><b class="text-navy">자살예방 상담 109</b> · 24시간 · 죽고 싶거나 자해하고 싶은 생각이 들 때</li>
        <li><b class="text-navy">청소년상담 1388</b> · 24시간 365일 · 전화·문자·온라인. 가출, 학교중단, 가족갈등도 상담할 수 있어</li>
        <li><b class="text-navy">경찰 112 · 구급 119</b> · 지금 나나 다른 사람이 다칠 것 같을 때 바로</li>
        <li>
          <b class="text-navy">학교 밖 청소년 지원센터 꿈드림</b> · 상담, 검정고시, 학습·진로·취업 지원,
          건강검진, 자립지원을 받을 수 있어. 1388에 물어보면 가까운 곳을 연결해 줘
        </li>
      </ul>
    </section>

    <div class="grid grid-cols-2 gap-2 print:hidden">
      <button type="button" onclick="window.print()" class="rounded-xl bg-navy/10 py-3 font-semibold">인쇄하기</button>
      {{-- 보호자 공유는 Task 18 에서 붙는다. 라우트가 생기기 전까지는 죽은 버튼을 내보내지 않는다. --}}
      @if(Route::has('oymsi.share.form'))
        <a href="{{ route('oymsi.share.form', $attempt->id) }}"
           class="rounded-xl bg-deepgreen text-cream py-3 text-center font-bold">보호자와 공유하기</a>
      @else
        <a href="{{ route('my.index') }}"
           class="rounded-xl bg-deepgreen text-cream py-3 text-center font-bold">내 검사함으로</a>
      @endif
    </div>

  </div>
</div>
</x-layouts.app>
