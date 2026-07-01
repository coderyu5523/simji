<x-layouts.app :title="'전문가·파트너 · 심지'">

  {{-- 목데이터 (백엔드 미연결 — 화면 시연용) --}}
  @php
    $experts = [
      ['name'=>'김소연','field'=>'아동·청소년 상담','intro'=>'정서·행동에 어려움을 겪는 아이들과 함께한 10년','area'=>'서울','tags'=>['아동정서','놀이치료'],'color'=>'bg-teal'],
      ['name'=>'박지훈','field'=>'진로·학습 코칭','intro'=>'청소년 진로설계와 학습전략을 돕습니다','area'=>'경기','tags'=>['진로','학습전략'],'color'=>'bg-deepgreen'],
      ['name'=>'이하늘','field'=>'대학생 심리상담','intro'=>'관계·자기이해 워크숍을 운영합니다','area'=>'부산','tags'=>['대인관계','자기탐색'],'color'=>'bg-navy'],
      ['name'=>'최민아','field'=>'직장인 EAP·코칭','intro'=>'번아웃과 조직 스트레스 관리 전문','area'=>'서울','tags'=>['번아웃','리더십'],'color'=>'bg-teal'],
      ['name'=>'정우성','field'=>'실버 마음건강','intro'=>'회상치료·정서활력 프로그램 진행','area'=>'대구','tags'=>['노인우울','회상'],'color'=>'bg-deepgreen'],
      ['name'=>'한예린','field'=>'부모교육 강사','intro'=>'양육 스트레스와 훈육을 함께 코칭합니다','area'=>'인천','tags'=>['부모코칭','정서'],'color'=>'bg-navy'],
    ];
    $concerns = ['아동 정서·행동','청소년 진로·학습','대학생 관계·진로','직장인 번아웃','실버 마음건강','부모교육'];
    $regions  = ['서울','경기·인천','부산·경남','대구·경북','대전·충청','광주·전라','기타'];
  @endphp

  {{-- 1. HERO --}}
  <section class="relative overflow-hidden bg-gradient-to-br from-deepgreen via-deepgreen to-teal text-cream">
    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-mint/10"></div>
    <div class="relative max-w-6xl mx-auto px-4 py-16 md:py-20">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-5">전문가·파트너</span>
      <h1 class="text-3xl md:text-5xl font-extrabold leading-[1.2]">검사 다음의 변화를<br><span class="text-mint">함께 이끄는 전문가들</span></h1>
      <p class="mt-5 text-cream/80 text-base md:text-lg leading-relaxed max-w-2xl">심지의 상담사·코치·강사가 검사 결과를 실제 변화로 연결합니다. 우리 아이·기관에 맞는 전문가를 찾아보세요.</p>
    </div>
  </section>

  {{-- 2. 전문가 연결 신청 (매칭 CTA — 목업) --}}
  <section class="bg-cream">
    <div class="max-w-4xl mx-auto px-4 py-14">
      <div x-data="{ sent: false }" class="rounded-3xl bg-white p-6 md:p-8 shadow-xl ring-1 ring-black/5">
        <div class="text-center mb-6">
          <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">전문가 연결</span>
          <h2 class="text-2xl font-extrabold text-deepgreen">내게 맞는 전문가를 연결해드려요</h2>
          <p class="mt-2 text-navy/60 text-sm">고민과 지역을 알려주시면 담당자가 적합한 전문가를 매칭해 드립니다.</p>
        </div>

        <form x-show="!sent" @submit.prevent="sent = true" class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">어떤 고민인가요? <span class="text-signal-red">*</span></label>
              <select required class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
                <option value="" disabled selected>선택하세요</option>
                @foreach($concerns as $c)
                  <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">지역 <span class="text-signal-red">*</span></label>
              <select required class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
                <option value="" disabled selected>선택하세요</option>
                @foreach($regions as $r)
                  <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">연락처 <span class="text-signal-red">*</span></label>
              <input type="tel" required placeholder="010-1234-5678" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            </div>
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">이름</label>
              <input type="text" placeholder="홍길동" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            </div>
          </div>
          <button type="submit" class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">전문가 연결 신청</button>
          <p class="text-center text-xs text-navy/40">* 현재는 시연용 화면으로, 실제 접수는 되지 않습니다.</p>
        </form>

        <div x-show="sent" x-cloak class="text-center py-8">
          <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-mint/50 text-deepgreen mb-5">
            <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <h3 class="text-xl font-extrabold text-deepgreen">신청이 접수되었습니다 🎉</h3>
          <p class="mt-2 text-navy/60">담당자가 확인 후 맞춤 전문가를 연결해 드리겠습니다.</p>
          <button @click="sent = false" class="mt-6 rounded-xl border border-teal text-teal px-6 py-3 font-semibold hover:bg-mint/30 transition">새 신청 작성</button>
        </div>
      </div>
    </div>
  </section>

  {{-- 3. 전문가 소개 (디렉토리 — 목업) --}}
  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="text-center mb-10">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">전문가 소개</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">심지와 함께하는 전문가</h2>
        <p class="mt-3 text-navy/60">검사·해석·코칭을 이끄는 분야별 전문가입니다.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($experts as $e)
          <div class="rounded-3xl bg-cream/60 ring-1 ring-black/5 p-6 hover:-translate-y-1 hover:shadow-lg transition">
            <div class="flex items-center gap-4">
              <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $e['color'] }} text-cream text-lg font-extrabold">{{ mb_substr($e['name'], 0, 1) }}</span>
              <div>
                <h3 class="font-bold text-deepgreen text-lg">{{ $e['name'] }}</h3>
                <p class="text-sm text-teal font-semibold">{{ $e['field'] }}</p>
              </div>
            </div>
            <p class="text-sm text-navy/60 mt-4 leading-relaxed">{{ $e['intro'] }}</p>
            <div class="flex flex-wrap items-center gap-2 mt-4">
              <span class="text-xs text-navy/40">📍 {{ $e['area'] }}</span>
              @foreach($e['tags'] as $tag)
                <span class="rounded-full bg-mint/40 text-deepgreen px-3 py-1 text-xs font-medium">#{{ $tag }}</span>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 4. 파트너 합류 CTA (목업) --}}
  <section class="bg-cream">
    <div class="max-w-6xl mx-auto px-4 py-14">
      <div class="rounded-3xl bg-navy text-cream p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-xl md:text-2xl font-extrabold">상담사·강사·코치이신가요?</h3>
          <p class="mt-2 text-cream/70">심지의 전문가·파트너로 함께 활동해보세요. 검사 기반 코칭 콘텐츠와 기관 연계를 지원합니다.</p>
        </div>
        <a href="{{ route('institution') }}" class="shrink-0 rounded-xl bg-mint text-deepgreen px-7 py-3.5 font-bold hover:brightness-105 transition">파트너 문의</a>
      </div>
    </div>
  </section>

</x-layouts.app>
