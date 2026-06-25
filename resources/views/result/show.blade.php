<x-layouts.app :title="'결과 · '.$test->title_easy">
  <div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold text-deepgreen">나의 마음상태 결과</h1>
    <div class="mt-4 flex items-center gap-3">
      <span>전체 위험도</span> <x-signal-badge :signal="$result->overall_signal"/>
    </div>
    <p class="mt-3 text-navy/80">현재 상태: {{ $result->overall_level }}</p>
    <p class="mt-1 text-sm text-navy/60">{{ $result->interpretation }}</p>

    <div class="mt-8 rounded-2xl bg-white p-5 shadow-sm">
      <canvas id="areaChart" height="160"></canvas>
    </div>

    <div class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
      <h2 class="font-semibold mb-3">영역별 결과</h2>
      <ul class="divide-y">
        @foreach($result->area_signals as $area => $sig)
          <li class="flex items-center justify-between py-2">
            <span>{{ $area }}</span>
            <span class="flex items-center gap-2 text-sm text-navy/60">{{ $result->area_scores[$area] }}점 <x-signal-badge :signal="$sig"/></span>
          </li>
        @endforeach
      </ul>
    </div>

    <div class="mt-6 rounded-2xl bg-mint/30 p-5">
      <h2 class="font-semibold mb-3">추천 솔루션</h2>
      <ol class="list-decimal list-inside space-y-1 text-navy/80">
        @foreach($result->recommendations as $rec) <li>{{ $rec }}</li> @endforeach
      </ol>
      <p class="text-xs text-navy/50 mt-3">* 강의/코칭 연결은 추후 제공됩니다.</p>
    </div>

    <a href="{{ route('my.index') }}" class="inline-block mt-8 text-teal">내 검사함으로 →</a>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
    new Chart(document.getElementById('areaChart'), {
      type: 'bar',
      data: {
        labels: @json(array_keys($result->area_scores)),
        datasets: [{ label: '영역 점수', data: @json(array_values($result->area_scores)), backgroundColor: '#2E7D6B' }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  </script>
</x-layouts.app>
