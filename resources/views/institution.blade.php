<x-layouts.app :title="'기관·단체 도입 · 심지'">

  {{-- 목데이터 (백엔드 미연결 — 화면 시연용) --}}
  @php
    $types = ['학교', '대학교', '기업', '복지관', '공공기관', '상담센터'];

    $features = [
      ['t'=>'검사 링크 일괄 발송', 'd'=>'대상자에게 문자·이메일로 검사 링크를 한 번에 전송', 'p'=>'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z'],
      ['t'=>'단체 엑셀 업로드',   'd'=>'명단을 엑셀로 올려 대량 대상자를 손쉽게 등록',   'p'=>'M12 15V3m0 0L8 7m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
      ['t'=>'기관별 관리자 페이지','d'=>'우리 기관 전용 대시보드에서 진행현황을 관리',       'p'=>'M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z'],
      ['t'=>'집단 결과 리포트',   'd'=>'개인 결과를 집단 단위로 집계·비교한 리포트 제공',   'p'=>'M7 16v-4M12 16V8M17 16v-7M3 20h18'],
      ['t'=>'위험군 자동 분류',   'd'=>'신호등 기준으로 관심·고위험 대상을 자동 선별',     'p'=>'M12 9v4m0 4h.01M10.29 3.86 1.82 18.56A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.73-3L13.75 3.86a2 2 0 0 0-3.46 0z'],
      ['t'=>'강의·코칭 연계',     'd'=>'결과에 맞는 특강·집단상담·코칭 프로그램으로 연결',   'p'=>'M5 12h14M13 6l6 6-6 6'],
      ['t'=>'사전·사후 변화분석', 'd'=>'프로그램 전후 재검사로 변화 효과를 데이터로 확인',   'p'=>'M3 17l6-6 4 4 8-8M21 7h-4M21 7v4'],
    ];

    $steps = [
      ['n'=>'01','t'=>'문의 접수','d'=>'아래 양식으로 도입 문의를 남깁니다.'],
      ['n'=>'02','t'=>'도입 상담','d'=>'담당자가 기관 목적·대상을 함께 설계합니다.'],
      ['n'=>'03','t'=>'검사 세팅','d'=>'대상·검사 구성 후 링크를 일괄 발송합니다.'],
      ['n'=>'04','t'=>'집단 리포트','d'=>'결과·위험군 분석 리포트를 제공합니다.'],
      ['n'=>'05','t'=>'코칭 연계','d'=>'맞춤 강의·코칭 프로그램으로 이어집니다.'],
    ];
  @endphp

  {{-- 1. HERO --}}
  <section class="relative overflow-hidden bg-gradient-to-br from-deepgreen via-deepgreen to-teal text-cream">
    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-mint/10"></div>
    <div class="absolute -left-16 bottom-0 h-56 w-56 rounded-full bg-teal/30"></div>
    <div class="relative max-w-6xl mx-auto px-4 py-16 md:py-24">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-5">기관·단체 도입</span>
      <h1 class="text-3xl md:text-5xl font-extrabold leading-[1.2]">학교·기업·복지관을 위한<br><span class="text-mint">마음건강 솔루션</span></h1>
      <p class="mt-5 text-cream/80 text-base md:text-lg leading-relaxed max-w-2xl">검사 일괄 발송부터 집단 결과 분석, 위험군 분류, 강의·코칭 연계까지<br class="hidden md:block"> 기관 단위로 마음건강을 관리하세요.</p>

      <div class="mt-7 flex flex-wrap gap-2">
        @foreach($types as $t)
          <span class="rounded-full bg-cream/10 ring-1 ring-cream/20 text-cream/90 px-4 py-1.5 text-sm">{{ $t }}</span>
        @endforeach
      </div>

      <a href="#inquiry" class="inline-flex items-center gap-2 mt-8 rounded-xl bg-mint text-deepgreen px-7 py-3.5 font-bold shadow-lg hover:brightness-105 transition">도입 문의하기 <span>→</span></a>
    </div>
  </section>

  {{-- 2. 제공 기능 --}}
  <section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="text-center mb-10">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">제공 기능</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">기관에 필요한 기능을 한 곳에</h2>
        <p class="mt-3 text-navy/60">개인 검사를 넘어, 집단을 관리하고 변화를 추적합니다.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($features as $f)
          <div class="rounded-3xl bg-cream/60 ring-1 ring-black/5 p-6 hover:-translate-y-1 hover:shadow-lg transition">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-mint/40 text-deepgreen">
              <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $f['p'] }}"/></svg>
            </span>
            <h3 class="font-bold text-deepgreen text-lg mt-4">{{ $f['t'] }}</h3>
            <p class="text-sm text-navy/60 mt-1.5 leading-relaxed">{{ $f['d'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 3. 도입 프로세스 --}}
  <section class="bg-gradient-to-b from-mint/30 to-cream">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="text-center mb-12">
        <span class="inline-block rounded-full bg-deepgreen/10 text-deepgreen px-3 py-1 text-xs font-semibold mb-3">도입 프로세스</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">문의부터 코칭까지, 5단계</h2>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-5">
        @foreach($steps as $s)
          <div class="rounded-3xl bg-white p-6 shadow-sm">
            <span class="text-3xl font-black text-mint">{{ $s['n'] }}</span>
            <h3 class="font-bold text-deepgreen mt-2">{{ $s['t'] }}</h3>
            <p class="text-sm text-navy/60 mt-1.5 leading-relaxed">{{ $s['d'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 4. 문의 폼 (목업 — 제출 시 감사 메시지 전환, 서버 안 탐) --}}
  <section id="inquiry" class="bg-white scroll-mt-20">
    <div class="max-w-3xl mx-auto px-4 py-16">
      <div class="text-center mb-10">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">도입 문의</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">도입이 궁금하신가요?</h2>
        <p class="mt-3 text-navy/60">문의를 남겨주시면 담당자가 빠르게 연락드립니다.</p>
      </div>

      <div x-data="{ sent: false }" class="rounded-3xl bg-cream p-6 md:p-8 shadow-xl ring-1 ring-black/5">

        {{-- 입력 폼 --}}
        <form x-show="!sent" @submit.prevent="sent = true" class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">기관명 <span class="text-signal-red">*</span></label>
              <input type="text" required placeholder="○○초등학교" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            </div>
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">기관 유형 <span class="text-signal-red">*</span></label>
              <select required class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
                <option value="" disabled selected>선택하세요</option>
                @foreach($types as $t)
                  <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">담당자명 <span class="text-signal-red">*</span></label>
              <input type="text" required placeholder="홍길동" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            </div>
            <div>
              <label class="block text-sm font-semibold text-deepgreen mb-1.5">연락처 <span class="text-signal-red">*</span></label>
              <input type="tel" required placeholder="010-1234-5678" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            </div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-deepgreen mb-1.5">이메일</label>
            <input type="email" placeholder="name@example.com" class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
          </div>
          <div>
            <label class="block text-sm font-semibold text-deepgreen mb-1.5">문의 내용</label>
            <textarea rows="4" placeholder="대상 인원, 검사 목적, 원하시는 일정 등을 적어주세요." class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40"></textarea>
          </div>
          <button type="submit" class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">문의 보내기</button>
          <p class="text-center text-xs text-navy/40">* 현재는 시연용 화면으로, 실제 접수는 되지 않습니다.</p>
        </form>

        {{-- 제출 후 감사 메시지 --}}
        <div x-show="sent" x-cloak class="text-center py-10">
          <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-mint/50 text-deepgreen mb-5">
            <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <h3 class="text-xl font-extrabold text-deepgreen">문의가 접수되었습니다 🎉</h3>
          <p class="mt-2 text-navy/60">담당자가 확인 후 빠르게 연락드리겠습니다.</p>
          <button @click="sent = false" class="mt-6 rounded-xl border border-teal text-teal px-6 py-3 font-semibold hover:bg-mint/30 transition">새 문의 작성</button>
        </div>

      </div>
    </div>
  </section>

</x-layouts.app>
