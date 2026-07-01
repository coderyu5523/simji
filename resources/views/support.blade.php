<x-layouts.app :title="'고객센터 · 심지'">

  @php
    $channels = [
      ['t'=>'전화 문의','v'=>'1577-0000','sub'=>'평일 10:00~18:00','p'=>'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z'],
      ['t'=>'이메일 문의','v'=>'help@simji.org','sub'=>'24시간 접수','p'=>'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6'],
      ['t'=>'카카오 채널','v'=>'@심지','sub'=>'실시간 상담','p'=>'M12 3C6.5 3 2 6.6 2 11c0 2.8 1.9 5.3 4.7 6.7-.2.7-.7 2.6-.8 3 0 .3.2.3.4.2.2-.1 2.5-1.7 3.5-2.4.7.1 1.4.2 2.2.2 5.5 0 10-3.6 10-8S17.5 3 12 3z'],
    ];
    $faqs = [
      ['q'=>'검사는 어떻게 받나요?','a'=>'상단 "심리검사"에서 연령방·검사를 고른 뒤, 동의 → 응시하면 됩니다. 별도 설치 없이 웹에서 바로 진행됩니다.'],
      ['q'=>'결과는 언제 나오나요?','a'=>'검사를 마치면 즉시 자동 채점되어, 신호등 결과 리포트를 바로 확인할 수 있습니다.'],
      ['q'=>'아이 검사도 가능한가요?','a'=>'네. 다만 만 14세 미만 대상 검사는 개인정보보호법에 따라 보호자(법정대리인) 동의 후 진행됩니다.'],
      ['q'=>'기관·단체로 도입하려면 어떻게 하나요?','a'=>'상단 "기관·단체" 페이지에서 도입 문의를 남겨주시면 담당자가 연락드립니다. 검사 일괄 발송·집단 리포트를 지원합니다.'],
      ['q'=>'결과 리포트는 어떻게 생겼나요?','a'=>'상단 "리포트샘플" 메뉴에서 실제 결과 리포트 예시를 검사 없이 미리 볼 수 있습니다.'],
      ['q'=>'결제·환불은 어떻게 되나요?','a'=>'검사권 구매 후 아직 사용하지 않은 검사권은 환불 가능합니다. (상세 정책은 준비 중입니다.)'],
    ];
  @endphp

  {{-- 1. HERO --}}
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-5xl mx-auto px-4 py-16 text-center">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-4">고객센터</span>
      <h1 class="text-2xl md:text-4xl font-extrabold">무엇을 도와드릴까요?</h1>
      <p class="mt-4 text-cream/80">궁금한 점이 있으면 아래 채널로 편하게 문의하세요.</p>
    </div>
  </section>

  {{-- 2. 문의 채널 --}}
  <section class="bg-cream">
    <div class="max-w-5xl mx-auto px-4 py-14">
      <div class="grid sm:grid-cols-3 gap-5">
        @foreach($channels as $c)
          <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 text-center">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-mint/40 text-deepgreen mb-4">
              <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $c['p'] }}"/></svg>
            </span>
            <h3 class="font-bold text-deepgreen">{{ $c['t'] }}</h3>
            <p class="text-lg font-extrabold text-navy/80 mt-1">{{ $c['v'] }}</p>
            <p class="text-xs text-navy/50 mt-1">{{ $c['sub'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 3. FAQ --}}
  <section class="bg-white">
    <div class="max-w-3xl mx-auto px-4 py-16">
      <div class="text-center mb-10">
        <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">자주 묻는 질문</span>
        <h2 class="text-2xl md:text-3xl font-extrabold text-deepgreen">FAQ</h2>
      </div>
      <div class="space-y-3">
        @foreach($faqs as $faq)
          <details class="group rounded-2xl bg-cream/60 ring-1 ring-black/5 p-5">
            <summary class="flex items-center justify-between cursor-pointer list-none font-semibold text-deepgreen">
              <span><span class="text-teal">Q.</span> {{ $faq['q'] }}</span>
              <span class="ml-3 text-navy/40 transition group-open:rotate-45 text-xl leading-none">+</span>
            </summary>
            <p class="mt-3 text-sm text-navy/70 leading-relaxed">{{ $faq['a'] }}</p>
          </details>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 4. 운영시간 + 문의 CTA --}}
  <section class="bg-cream">
    <div class="max-w-5xl mx-auto px-4 py-14">
      <div class="rounded-3xl bg-navy text-cream p-8 md:p-10 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-xl font-extrabold">운영시간 안내</h3>
          <p class="mt-2 text-cream/70">평일 10:00 ~ 18:00 (점심 12:00~13:00) · 주말·공휴일 휴무<br>이메일 문의는 24시간 접수되며 순차적으로 답변드립니다.</p>
        </div>
        <a href="{{ route('institution') }}" class="shrink-0 rounded-xl bg-mint text-deepgreen px-7 py-3.5 font-bold hover:brightness-105 transition">기관 도입 문의</a>
      </div>
      <p class="text-center text-xs text-navy/40 mt-6">* 현재는 시연용 화면으로, 표시된 연락처는 예시입니다.</p>
    </div>
  </section>

</x-layouts.app>
