<x-layouts.app :title="'리포트 샘플 · 심지'">

  {{-- 목데이터 (문서 7장 예시 — 검사 없이 보는 샘플 리포트) --}}
  @php
    $sample = [
      'test'      => '초등 마음안전선별검사',
      'test_pro'  => '아동 정서·행동 선별평가',
      'signal'    => 'yellow',
      'level'     => '관심과 조기지원이 필요한 단계입니다.',
      'interpret' => '전반적으로 양호하나 일부 영역에서 주의가 필요합니다. 특히 스마트폰 사용과 주의집중 영역에서 조기 개입을 권장합니다.',
      'areas'     => [
        ['name'=>'불안',        'score'=>72, 'signal'=>'yellow'],
        ['name'=>'우울감',      'score'=>41, 'signal'=>'green'],
        ['name'=>'주의집중',    'score'=>68, 'signal'=>'yellow'],
        ['name'=>'또래관계',    'score'=>33, 'signal'=>'green'],
        ['name'=>'스마트폰 사용','score'=>85, 'signal'=>'red'],
      ],
      'recommend' => [
        '스마트폰 사용 조절 부모코칭',
        '주의집중 향상 4주 프로그램',
        '담임교사용 지도 가이드',
      ],
    ];
  @endphp

  {{-- 샘플 안내 배너 --}}
  <div class="bg-navy text-cream">
    <div class="max-w-3xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
      <span class="flex items-center gap-2"><span class="rounded-full bg-mint text-deepgreen text-xs font-bold px-2.5 py-0.5">SAMPLE</span> 실제 검사 결과가 어떻게 나오는지 보여주는 예시입니다.</span>
      <a href="{{ route('catalog.index') }}" class="font-semibold text-mint hover:underline">직접 검사 받아보기 →</a>
    </div>
  </div>

  {{-- 결과 헤더 --}}
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-3xl mx-auto px-4 py-12">
      <p class="text-cream/70 text-sm">{{ $sample['test'] }} <span class="text-cream/40">· {{ $sample['test_pro'] }}</span></p>
      <h1 class="text-2xl md:text-3xl font-extrabold mt-1">나의 마음상태 결과</h1>
      <div class="mt-5 flex items-center gap-3">
        <span class="text-cream/80">전체 위험도</span>
        <x-signal-badge :signal="$sample['signal']"/>
      </div>
      <p class="mt-3 font-semibold">현재 상태: {{ $sample['level'] }}</p>
      <p class="mt-1 text-sm text-cream/70 leading-relaxed">{{ $sample['interpret'] }}</p>
    </div>
  </section>

  <div class="bg-cream">
    <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">
      {{-- 그래프 --}}
      <div class="rounded-3xl bg-white p-6 shadow-sm">
        <h2 class="font-bold text-deepgreen mb-4">영역별 점수</h2>
        <canvas id="sampleChart" height="160"></canvas>
      </div>

      {{-- 영역별 신호등 --}}
      <div class="rounded-3xl bg-white p-6 shadow-sm">
        <h2 class="font-bold text-deepgreen mb-2">영역별 결과</h2>
        <ul class="divide-y divide-black/5">
          @foreach($sample['areas'] as $area)
            <li class="flex items-center justify-between py-3">
              <span class="text-navy/80">{{ $area['name'] }}</span>
              <span class="flex items-center gap-3 text-sm text-navy/50">{{ $area['score'] }}점 <x-signal-badge :signal="$area['signal']"/></span>
            </li>
          @endforeach
        </ul>
      </div>

      {{-- 추천 솔루션 --}}
      <div class="rounded-3xl bg-mint/40 p-6">
        <h2 class="font-bold text-deepgreen mb-3">추천 솔루션</h2>
        <ol class="space-y-2 text-navy/80">
          @foreach($sample['recommend'] as $rec)
            <li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-teal shrink-0"></span>{{ $rec }}</li>
          @endforeach
        </ol>
        <p class="text-xs text-navy/50 mt-4">* 강의·코칭 연결은 추후 제공됩니다.</p>
      </div>

      <div class="flex flex-wrap gap-3 pt-2">
        <a href="{{ route('catalog.index') }}" class="rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">직접 검사 받아보기</a>
        <a href="{{ route('home') }}" class="rounded-xl border border-teal text-teal px-6 py-3 font-semibold hover:bg-mint/30 transition">홈으로</a>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
    new Chart(document.getElementById('sampleChart'), {
      type: 'bar',
      data: {
        labels: @json(array_column($sample['areas'], 'name')),
        datasets: [{ label: '영역 점수', data: @json(array_column($sample['areas'], 'score')), backgroundColor: '#2E7D6B', borderRadius: 6 }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 }, x: { grid: { display: false } } } }
    });
  </script>
</x-layouts.app>
