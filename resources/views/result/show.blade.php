<x-layouts.app :title="'결과 · '.$test->title_easy">
  {{-- 결과 헤더 --}}
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-3xl mx-auto px-4 py-12">
      <p class="text-cream/70 text-sm">{{ $test->title_easy }}</p>
      <h1 class="text-2xl md:text-3xl font-extrabold mt-1">나의 마음상태 결과</h1>
      <div class="mt-5 flex items-center gap-3">
        <span class="text-cream/80">전체 위험도</span>
        <x-signal-badge :signal="$result->overall_signal"/>
      </div>
      <p class="mt-3 font-semibold">현재 상태: {{ $result->overall_level }}</p>
      <p class="mt-1 text-sm text-cream/70 leading-relaxed">{{ $result->interpretation }}</p>
    </div>
  </section>

  <div class="bg-cream">
    <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">
      {{-- 그래프 --}}
      <div class="rounded-3xl bg-white p-6 shadow-sm">
        <h2 class="font-bold text-deepgreen mb-4">영역별 점수</h2>
        <canvas id="areaChart" height="160"></canvas>
      </div>

      {{-- 영역별 신호등 --}}
      <div class="rounded-3xl bg-white p-6 shadow-sm">
        <h2 class="font-bold text-deepgreen mb-2">영역별 결과</h2>
        <ul class="divide-y divide-black/5">
          @foreach($result->area_signals as $area => $sig)
            <li class="flex items-center justify-between py-3">
              <span class="text-navy/80">{{ $area }}</span>
              <span class="flex items-center gap-3 text-sm text-navy/50">{{ $result->area_scores[$area] }}점 <x-signal-badge :signal="$sig"/></span>
            </li>
          @endforeach
        </ul>
      </div>

      {{-- 추천 솔루션 --}}
      <div class="rounded-3xl bg-mint/40 p-6">
        <h2 class="font-bold text-deepgreen mb-3">추천 솔루션</h2>
        <ol class="space-y-2 text-navy/80">
          @foreach($result->recommendations as $rec)
            <li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-teal shrink-0"></span>{{ $rec }}</li>
          @endforeach
        </ol>
        <p class="text-xs text-navy/50 mt-4">* 강의·코칭 연결은 추후 제공됩니다.</p>
      </div>

      <div class="flex flex-wrap gap-3 pt-2">
        <a href="{{ route('my.index') }}" class="rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">내 검사함으로</a>
        <a href="{{ route('catalog.index') }}" class="rounded-xl border border-teal text-teal px-6 py-3 font-semibold hover:bg-mint/30 transition">다른 검사 하기</a>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
    new Chart(document.getElementById('areaChart'), {
      type: 'bar',
      data: {
        labels: @json(array_keys($result->area_scores)),
        datasets: [{ label: '영역 점수', data: @json(array_values($result->area_scores)), backgroundColor: '#2E7D6B', borderRadius: 6 }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } }
    });
  </script>
</x-layouts.app>
