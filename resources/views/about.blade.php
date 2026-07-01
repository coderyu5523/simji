<x-layouts.app :title="'심지 소개 · simji'">

  @php
    // 검사 → 해석 → 강의·코칭 → 변화관리 (문서 1장)
    $flow = [
      ['t'=>'검사',      'd'=>'연령별 마음상태를 과학적으로 확인'],
      ['t'=>'해석',      'd'=>'신호등 리포트로 쉽게 이해'],
      ['t'=>'강의·코칭', 'd'=>'결과에 맞는 변화 프로그램 연결'],
      ['t'=>'변화관리',  'd'=>'재검사로 성장을 추적'],
    ];
    // 차별화 포인트 (문서 11장)
    $compare = [
      ['기존 심리검사 사이트'=>'검사 구매 중심',   '심지 플랫폼'=>'검사 후 변화관리 중심'],
      ['기존 심리검사 사이트'=>'검사 목록 중심',   '심지 플랫폼'=>'연령별 마음방 중심'],
      ['기존 심리검사 사이트'=>'결과 리포트 제공', '심지 플랫폼'=>'결과 + 강의 + 코칭 연결'],
      ['기존 심리검사 사이트'=>'전문가 중심 언어', '심지 플랫폼'=>'부모·학생·직장인이 이해하는 언어'],
      ['기존 심리검사 사이트'=>'단회성 검사',     '심지 플랫폼'=>'누적 변화 추적'],
      ['기존 심리검사 사이트'=>'기관 단체검사',   '심지 플랫폼'=>'기관 맞춤형 강의 패키지'],
    ];
    $rooms = \App\Support\Rooms::all();
  @endphp

  {{-- 1. HERO --}}
  <section class="relative overflow-hidden bg-gradient-to-br from-deepgreen via-deepgreen to-teal text-cream">
    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-mint/10"></div>
    <div class="relative max-w-6xl mx-auto px-4 py-16 md:py-24">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-5">심지 소개</span>
      <h1 class="text-3xl md:text-5xl font-extrabold leading-[1.2]">마음을 검사하고,<br><span class="text-mint">삶을 코칭하다</span></h1>
      <p class="mt-5 text-cream/80 text-base md:text-lg leading-relaxed max-w-2xl">심지(simji)는 초등학생부터 실버 세대까지, 마음검사와 강의·코칭을 연결하는 <b class="text-mint">전 생애 마음성장 플랫폼</b>입니다.</p>
    </div>
  </section>

  {{-- 2. 우리가 하는 일 (흐름) --}}
  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="text-center mb-12">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">심지가 하는 일</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">검사에서 끝나지 않습니다</h2>
        <p class="mt-3 text-navy/60">검사는 시작이고, 변화까지 이어드립니다.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach($flow as $i => $f)
          <div class="relative rounded-3xl bg-cream/60 ring-1 ring-black/5 p-6">
            <span class="text-3xl font-black text-mint">{{ sprintf('%02d', $i+1) }}</span>
            <h3 class="font-bold text-deepgreen mt-2 text-lg">{{ $f['t'] }}</h3>
            <p class="text-sm text-navy/60 mt-1.5 leading-relaxed">{{ $f['d'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 3. 심지의 차별점 --}}
  <section class="bg-gradient-to-b from-mint/30 to-cream">
    <div class="max-w-4xl mx-auto px-4 py-16">
      <div class="text-center mb-10">
        <span class="inline-block rounded-full bg-deepgreen/10 text-deepgreen px-3 py-1 text-xs font-semibold mb-3">무엇이 다른가</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">검사 사이트를 넘어, 마음성장 플랫폼</h2>
      </div>
      <div class="rounded-3xl bg-white shadow-sm ring-1 ring-black/5 overflow-hidden">
        <div class="grid grid-cols-2 text-sm">
          <div class="bg-black/[0.03] px-5 py-3 font-bold text-navy/50">기존 심리검사 사이트</div>
          <div class="bg-deepgreen px-5 py-3 font-bold text-cream">심지 플랫폼</div>
          @foreach($compare as $row)
            <div class="px-5 py-4 border-t border-black/5 text-navy/60">{{ $row['기존 심리검사 사이트'] }}</div>
            <div class="px-5 py-4 border-t border-black/5 text-deepgreen font-semibold bg-mint/10">{{ $row['심지 플랫폼'] }}</div>
          @endforeach
        </div>
      </div>
    </div>
  </section>

  {{-- 4. 마음의 방 은유 --}}
  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="text-center mb-10">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">마음의 방</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">심지는 연령별 '마음의 방'입니다</h2>
        <p class="mt-3 text-navy/60">"검사센터"보다 덜 무섭고, "상담소"보다 더 쉽고, "플랫폼"보다 더 따뜻하게.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach($rooms as $room)
          <a href="{{ route('catalog.room', $room['code']) }}" class="rounded-3xl bg-cream/60 ring-1 ring-black/5 p-5 hover:-translate-y-1 hover:shadow-lg transition">
            <h3 class="font-bold text-deepgreen">{{ $room['name'] }}</h3>
            <p class="text-sm text-navy/60 mt-1.5 leading-relaxed">{{ $room['desc'] }}</p>
          </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 5. 슬로건 CTA --}}
  <section class="bg-gradient-to-r from-deepgreen to-teal text-cream">
    <div class="max-w-6xl mx-auto px-4 py-16 text-center">
      <h2 class="text-2xl md:text-3xl font-extrabold">검사는 시작입니다. 변화는 심지에서 완성됩니다.</h2>
      <p class="mt-3 text-cream/80">내 마음을 알고, 내 삶을 단단하게.</p>
      <a href="{{ route('catalog.index') }}" class="inline-block mt-8 rounded-xl bg-mint text-deepgreen px-8 py-4 font-bold text-lg shadow-lg hover:brightness-105 transition">심리검사 시작하기</a>
    </div>
  </section>

</x-layouts.app>
