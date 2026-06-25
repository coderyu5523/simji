<x-layouts.app :title="'simji 심지 · 마음을 검사하고, 삶을 코칭하다'">

  {{-- 1. HERO --}}
  <section class="relative overflow-hidden bg-gradient-to-br from-deepgreen via-deepgreen to-teal text-cream">
    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-mint/10"></div>
    <div class="absolute -left-16 bottom-0 h-56 w-56 rounded-full bg-teal/30"></div>
    <div class="relative max-w-6xl mx-auto px-4 py-16 md:py-24 grid md:grid-cols-2 gap-10 items-center">
      <div>
        <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-5">전 생애 마음건강 플랫폼</span>
        <h1 class="text-3xl md:text-5xl font-extrabold leading-[1.2]">마음을 검사하고,<br><span class="text-mint">삶을 코칭하다</span></h1>
        <p class="mt-5 text-cream/80 text-base md:text-lg leading-relaxed">대학생부터 실버 세대까지, 연령별 마음상태를 과학적으로 확인하고<br class="hidden md:block"> 결과 이후 맞춤 강의·코칭으로 연결합니다.</p>
        <div class="mt-8 flex flex-wrap gap-3">
          <a href="{{ route('catalog.index') }}" class="rounded-xl bg-mint text-deepgreen px-7 py-3.5 font-bold shadow-lg hover:brightness-105 transition">심리검사 시작하기</a>
          <a href="{{ route('coaching') }}" class="rounded-xl border border-cream/40 text-cream px-7 py-3.5 font-semibold hover:bg-cream/10 transition">강의·코칭 둘러보기</a>
        </div>
      </div>
      <div class="relative">
        <img src="{{ asset('images/rooms/worker.png') }}" alt="심지 마음건강" class="rounded-3xl shadow-2xl w-full object-cover aspect-[4/3] ring-1 ring-cream/10">
        <div class="absolute -bottom-5 -left-5 hidden sm:flex items-center gap-2 rounded-2xl bg-cream text-deepgreen px-4 py-3 shadow-xl">
          <span class="h-3 w-3 rounded-full bg-signal-green"></span>
          <span class="h-3 w-3 rounded-full bg-signal-yellow"></span>
          <span class="h-3 w-3 rounded-full bg-signal-red"></span>
          <span class="text-sm font-bold ml-1">신호등 결과 리포트</span>
        </div>
      </div>
    </div>
  </section>

  {{-- 2. 가치 밴드 --}}
  <section class="bg-cream">
    <div class="max-w-6xl mx-auto px-4 py-12 grid sm:grid-cols-3 gap-5">
      @php
        $values = [
          ['t'=>'연령별 마음방','d'=>'대학생·직장인·실버 맞춤 구성','p'=>'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6'],
          ['t'=>'신호등 결과 리포트','d'=>'초록·노랑·빨강으로 한눈에','p'=>'M12 2a4 4 0 0 1 4 4 4 4 0 1 1-8 0 4 4 0 0 1 4-4zM12 10a4 4 0 1 1 0 8 4 4 0 0 1 0-8z'],
          ['t'=>'검사 → 코칭 연결','d'=>'결과 이후의 변화까지','p'=>'M5 12h14M13 6l6 6-6 6'],
        ];
      @endphp
      @foreach($values as $v)
        <div class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-sm">
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-mint/40 text-deepgreen">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $v['p'] }}"/></svg>
          </span>
          <div><p class="font-bold text-deepgreen">{{ $v['t'] }}</p><p class="text-sm text-navy/60 mt-0.5">{{ $v['d'] }}</p></div>
        </div>
      @endforeach
    </div>
  </section>

  {{-- 3. 연령별 마음방 --}}
  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="text-center mb-10">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">연령별 마음방</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">나는 지금 어느 방에 있나요?</h2>
        <p class="mt-3 text-navy/60">복잡한 검사 목록 대신, 나에게 맞는 방부터 고르세요.</p>
      </div>
      <div class="grid md:grid-cols-3 gap-6">
        @foreach($rooms as $room)
          <a href="{{ route('catalog.room', $room['code']) }}" class="group rounded-3xl bg-cream/60 ring-1 ring-black/5 overflow-hidden hover:-translate-y-1 hover:shadow-xl transition">
            <div class="overflow-hidden"><img src="{{ asset('images/rooms/'.$room['code'].'.png') }}" alt="{{ $room['name'] }}" class="h-44 w-full object-cover group-hover:scale-105 transition duration-500"></div>
            <div class="p-6">
              <h3 class="font-bold text-lg text-deepgreen">{{ $room['name'] }}</h3>
              <p class="text-sm text-navy/60 mt-1">{{ $room['desc'] }}</p>
              <span class="inline-flex items-center gap-1 text-sm font-semibold text-teal mt-4">방 입장 <span class="group-hover:translate-x-1 transition">→</span></span>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 4. 검사 프로세스 (큰 흐름) --}}
  <section class="bg-gradient-to-b from-mint/30 to-cream">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="text-center mb-12">
        <span class="inline-block rounded-full bg-deepgreen/10 text-deepgreen px-3 py-1 text-xs font-semibold mb-3">검사 진행</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">검사는 이렇게 진행돼요</h2>
        <p class="mt-3 text-navy/60">쉽고 안전하게, 4단계면 충분합니다.</p>
      </div>
      @php
        $steps = [
          ['n'=>'01','t'=>'연령방·검사 선택','d'=>'고민이나 연령으로 나에게 맞는 검사를 고릅니다.','p'=>'M4 6h16M4 12h16M4 18h10'],
          ['n'=>'02','t'=>'동의 후 응시','d'=>'개인정보 동의 후 문항에 솔직하게 답합니다.','p'=>'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
          ['n'=>'03','t'=>'신호등 결과 리포트','d'=>'영역별 결과를 신호등으로 즉시 확인합니다.','p'=>'M12 3v18M5 8l7-5 7 5'],
          ['n'=>'04','t'=>'맞춤 코칭 연결','d'=>'결과에 맞는 강의·코칭으로 변화를 시작합니다.','p'=>'M5 12h14M13 6l6 6-6 6'],
        ];
      @endphp
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach($steps as $s)
          <div class="relative rounded-3xl bg-white p-6 shadow-sm">
            <span class="text-3xl font-black text-mint">{{ $s['n'] }}</span>
            <span class="absolute top-6 right-6 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal/10 text-teal">
              <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $s['p'] }}"/></svg>
            </span>
            <h3 class="font-bold text-deepgreen mt-3">{{ $s['t'] }}</h3>
            <p class="text-sm text-navy/60 mt-1.5 leading-relaxed">{{ $s['d'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 5. 신호등 결과 미리보기 --}}
  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-16 grid md:grid-cols-2 gap-10 items-center">
      <div>
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">결과 리포트</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">결과는 선명하게,<br>다음 행동은 분명하게</h2>
        <p class="mt-4 text-navy/70 leading-relaxed">전문 용어 대신 <b>초록·노랑·빨강 신호등</b>으로 내 마음 상태를 한눈에. 영역별 그래프와 쉬운 해석문, 그리고 <b>맞춤 솔루션</b>까지 이어집니다.</p>
        <ul class="mt-5 space-y-2 text-sm text-navy/70">
          <li class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-signal-green"></span> 영역별 신호등 + 막대그래프</li>
          <li class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-signal-yellow"></span> 부모·본인이 바로 이해하는 해석문</li>
          <li class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-signal-red"></span> 결과 기반 강의·코칭 추천</li>
        </ul>
        @if($recommended->first())
          <p class="mt-7 text-sm text-navy/50">지금 받아볼 수 있는 검사</p>
          <a href="{{ route('catalog.show', $recommended->first()->code) }}" class="inline-flex items-center gap-2 mt-2 rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">{{ $recommended->first()->title_easy }} 받아보기 →</a>
        @endif
      </div>
      {{-- 결과 미리보기 카드 (UI 목업) --}}
      <div class="rounded-3xl bg-cream p-6 shadow-xl ring-1 ring-black/5">
        <div class="flex items-center justify-between">
          <p class="font-bold text-deepgreen">나의 마음상태 결과</p>
          <span class="rounded-full bg-signal-yellow text-white text-xs font-bold px-3 py-1">노랑</span>
        </div>
        <p class="text-xs text-navy/50 mt-1">관심과 조기지원이 필요한 단계</p>
        <div class="mt-5 space-y-3">
          @foreach([['스트레스',70,'bg-signal-red'],['우울감',40,'bg-signal-green'],['불안',62,'bg-signal-yellow'],['회복탄력성',35,'bg-signal-green']] as [$label,$w,$c])
            <div>
              <div class="flex justify-between text-xs text-navy/60 mb-1"><span>{{ $label }}</span></div>
              <div class="h-2.5 rounded-full bg-black/5 overflow-hidden"><div class="h-2.5 rounded-full {{ $c }}" style="width: {{ $w }}%"></div></div>
            </div>
          @endforeach
        </div>
        <div class="mt-5 rounded-2xl bg-mint/40 p-4 text-sm text-deepgreen">
          <p class="font-semibold">추천 솔루션</p>
          <p class="text-navy/70 mt-1">· 스트레스 관리 4주 프로그램</p>
        </div>
      </div>
    </div>
  </section>

  {{-- 6. 고민으로 찾기 --}}
  <section class="bg-cream">
    <div class="max-w-6xl mx-auto px-4 py-14 text-center">
      <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">고민으로 찾기</span>
      <h2 class="text-2xl font-extrabold text-deepgreen">요즘, 어떤 게 가장 신경 쓰이세요?</h2>
      <div class="mt-6 flex flex-wrap justify-center gap-2.5">
        @foreach(collect($rooms)->flatMap(fn($r) => $r['tags'])->unique() as $tag)
          <a href="{{ route('catalog.index') }}" class="rounded-full bg-white ring-1 ring-black/5 text-deepgreen px-5 py-2 text-sm hover:bg-mint/40 transition">#{{ $tag }}</a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 7. 기관 배너 + 마무리 CTA --}}
  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-14">
      <div class="rounded-3xl bg-navy text-cream p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-xl md:text-2xl font-extrabold">학교·기업·복지관이라면, 단체로 도입하세요</h3>
          <p class="mt-2 text-cream/70">검사 일괄 발송 · 집단 결과 리포트 · 위험군 분류 · 맞춤 프로그램 연계</p>
        </div>
        <a href="{{ route('institution') }}" class="shrink-0 rounded-xl bg-mint text-deepgreen px-7 py-3.5 font-bold hover:brightness-105 transition">기관 도입 문의</a>
      </div>
    </div>
  </section>

  <section class="bg-gradient-to-r from-deepgreen to-teal text-cream">
    <div class="max-w-6xl mx-auto px-4 py-16 text-center">
      <h2 class="text-2xl md:text-3xl font-extrabold">검사는 시작입니다. 변화는 심지에서 완성됩니다.</h2>
      <p class="mt-3 text-cream/80">지금 내 마음 상태를 확인해 보세요.</p>
      <a href="{{ route('catalog.index') }}" class="inline-block mt-8 rounded-xl bg-mint text-deepgreen px-8 py-4 font-bold text-lg shadow-lg hover:brightness-105 transition">무료로 시작하기</a>
    </div>
  </section>

</x-layouts.app>
