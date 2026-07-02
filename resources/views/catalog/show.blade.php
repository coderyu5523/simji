<x-layouts.app :title="$test->title_easy">
  <div class="bg-cream">
    <div class="max-w-4xl mx-auto px-4 py-10">
      <a href="{{ route('catalog.room', $test->room) }}" class="text-navy/50 text-sm hover:text-teal transition">← 다른 검사 보기</a>

      @error('issue')
        <div class="mt-4 rounded-xl bg-signal-red/10 text-signal-red px-4 py-3 text-sm font-semibold">{{ $message }}</div>
      @enderror

      <div class="mt-4 grid md:grid-cols-2 gap-8 items-start">
        <img src="{{ asset($test->thumbnail ?: 'images/tests/placeholder.png') }}" class="h-56 md:h-64 w-full object-cover rounded-3xl shadow-lg ring-1 ring-black/5" alt="{{ $test->title_easy }}">
        <div>
          <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">심리검사</span>
          <h1 class="text-2xl md:text-3xl font-extrabold text-deepgreen">{{ $test->title_easy }}</h1>
          <p class="text-sm text-navy/40 mt-1">{{ $test->title_pro }}</p>
          <p class="mt-4 text-navy/70 leading-relaxed">{{ $test->description }}</p>
        </div>
      </div>

      <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([['대상',$test->target],['소요시간','약 '.$test->duration_min.'분'],['문항수',$test->item_count.'문항'],['결과형태','신호등 리포트']] as [$k,$v])
          <div class="rounded-2xl bg-white p-4 shadow-sm">
            <dt class="text-xs text-navy/50">{{ $k }}</dt>
            <dd class="font-bold text-deepgreen mt-1">{{ $v }}</dd>
          </div>
        @endforeach
      </div>

      <div class="mt-5 rounded-2xl bg-white p-5 shadow-sm">
        <p class="text-xs text-navy/50 mb-2">평가영역</p>
        <div class="flex flex-wrap gap-2">
          @foreach($test->areas as $area)
            <span class="rounded-full bg-mint/40 text-deepgreen px-4 py-1.5 text-sm font-medium">{{ $area }}</span>
          @endforeach
        </div>
      </div>

      {{-- 두 가지 이용 방법을 한눈에: 직접 응시 | 여러 명에게 발급 --}}
      <div class="mt-8 grid md:grid-cols-2 gap-4">

        {{-- ① 직접 응시 --}}
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 flex flex-col">
          <h2 class="font-bold text-deepgreen">직접 응시하기</h2>
          <p class="text-sm text-navy/60 mt-2 flex-1">내가 바로 검사를 받고 신호등 결과를 확인해요.</p>
          <div class="mt-4">
            @auth
              @if(!$product || $hasVoucher)
                <a href="{{ route('assessment.consent', $test->code) }}" class="block text-center rounded-xl bg-deepgreen text-cream px-6 py-3.5 font-bold shadow-lg hover:brightness-110 transition">검사 시작</a>
                @if($product)<p class="mt-2 text-xs text-navy/40">보유 검사권 1개가 차감됩니다.</p>@endif
              @else
                <div class="rounded-xl bg-black/5 text-navy/50 px-4 py-3.5 text-sm text-center">보유한 검사권이 없어요.<br>오른쪽에서 발급 후 이용해 주세요.</div>
              @endif
            @else
              <a href="{{ route('login') }}" class="block text-center rounded-xl bg-deepgreen text-cream px-6 py-3.5 font-bold shadow-lg hover:brightness-110 transition">로그인하고 검사 시작</a>
            @endauth
          </div>
        </div>

        {{-- ② 여러 명에게 발급 (직접 응시 카드와 동일 톤) --}}
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 flex flex-col">
          <h2 class="font-bold text-deepgreen">여러 명에게 발급하기</h2>
          <p class="text-sm text-navy/60 mt-2 flex-1">응시 링크를 만들어 전달해요. 받은 분은 로그인 없이 응시하고, 결과는 <a href="{{ route('my.index') }}" class="text-teal font-semibold">내 검사함</a>에서 관리.</p>
          <div class="mt-4">
            @auth
              <form method="POST" action="{{ route('my.issue') }}" class="flex flex-wrap gap-2 items-end">
                @csrf
                <input type="hidden" name="test_id" value="{{ $test->id }}">
                <div class="w-24">
                  <label class="block text-xs font-semibold text-navy/60 mb-1.5">수량</label>
                  <input type="number" name="qty" value="1" min="1" max="100" required class="w-full rounded-xl border border-black/10 px-3 py-3 text-sm bg-white focus:border-teal focus:ring-teal">
                </div>
                <button class="flex-1 rounded-xl bg-teal text-white px-5 py-3 font-bold shadow-lg hover:brightness-110 transition whitespace-nowrap">검사권 발급</button>
              </form>
              <button type="button" onclick="alert('준비 중인 기능입니다.')" class="mt-2 w-full rounded-xl border border-teal/50 text-teal px-4 py-2.5 text-sm font-semibold hover:bg-mint/20 transition">여러 명 한 번에 (엑셀) <span class="text-xs align-top">준비중</span></button>
              @if($product)<p class="mt-2 text-xs text-navy/40">유료 검사는 보유 검사권에서 차감됩니다.</p>@endif
            @else
              <a href="{{ route('login') }}" class="block text-center rounded-xl bg-teal text-white px-6 py-3.5 font-bold hover:brightness-110 transition">로그인하고 발급하기</a>
            @endauth
          </div>
        </div>
      </div>

      {{-- 검사 소개 (상세 이미지 + 결과 예시 그래프) --}}
      <div class="mt-12 space-y-6">
        <h2 class="text-xl font-extrabold text-deepgreen">검사 소개</h2>

        {{-- 상세 소개 이미지 (현업 이미지 예정 — 자리 표시) --}}
        <div class="grid sm:grid-cols-3 gap-4">
          @foreach(['이 검사는 무엇을 보나요','어떻게 진행되나요','결과는 이렇게 나와요'] as $cap)
            <div class="rounded-2xl bg-white ring-1 ring-black/5 overflow-hidden">
              <div class="aspect-[4/3] bg-gradient-to-br from-mint/40 to-cream flex flex-col items-center justify-center gap-2 text-navy/30">
                <svg viewBox="0 0 24 24" class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                <span class="text-xs">이미지 영역</span>
              </div>
              <p class="px-4 py-3 text-sm text-navy/60">{{ $cap }}</p>
            </div>
          @endforeach
        </div>
        <p class="text-xs text-navy/40">※ 소개 이미지·설명 문구는 준비되는 대로 교체됩니다.</p>

        {{-- 결과 예시 그래프 --}}
        @if(!empty($test->areas))
          @php
            $palette = [72, 45, 63, 38, 80, 55, 48, 67];
            $introData = [];
            foreach (array_values($test->areas) as $i => $a) { $introData[] = $palette[$i % count($palette)]; }
          @endphp
          <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold text-deepgreen">결과 예시 — 영역별 점수</h3>
              <span class="rounded-full bg-mint text-deepgreen text-xs font-bold px-2.5 py-0.5">예시</span>
            </div>
            <canvas id="introChart" height="140"></canvas>
            <p class="text-xs text-navy/40 mt-3">이 검사의 평가영역({{ implode(' · ', $test->areas) }})을 신호등으로 표시한 예시입니다.</p>
          </div>
        @endif
      </div>

      <div class="mt-8">
        <a href="{{ route('catalog.room', $test->room) }}" class="text-sm text-navy/50 hover:text-teal transition">← 이 방의 다른 검사 보기</a>
      </div>
    </div>
  </div>

  @if(!empty($test->areas))
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
      (function () {
        const data = @json($introData);
        const colors = data.map(v => v >= 70 ? '#E0584E' : (v >= 50 ? '#F2B705' : '#3FAE5A'));
        new Chart(document.getElementById('introChart'), {
          type: 'bar',
          data: { labels: @json($test->areas), datasets: [{ data, backgroundColor: colors, borderRadius: 6 }] },
          options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 }, x: { grid: { display: false } } } }
        });
      })();
    </script>
  @endif
</x-layouts.app>
