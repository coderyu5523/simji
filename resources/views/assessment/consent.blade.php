<x-layouts.app :title="'동의 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-12">
      <p class="text-sm text-teal font-semibold">1 동의 · <span class="text-navy/30">2 안내 · 3 검사</span></p>
      <h1 class="text-2xl font-extrabold text-deepgreen mt-2">검사 전 동의</h1>
      <p class="text-sm text-navy/50 mt-1">{{ $test->title_easy }}</p>

      <div class="mt-6 rounded-3xl bg-white p-6 text-sm text-navy/80 space-y-3 shadow-sm ring-1 ring-black/5">
        <p>본 검사는 <b class="text-deepgreen">민감정보(건강에 관한 정보)</b>를 수집·처리합니다. 수집된 응답은 결과 산출 및 보관 목적에만 이용됩니다.</p>
        <p class="text-navy/60">수집 항목: 검사 응답·결과 · 보관 기간: 회원 탈퇴 시 또는 법정 보관기간까지.</p>
      </div>

      <form method="POST" action="{{ route('assessment.agree', $test->code) }}" class="mt-6">
        @csrf
        <label class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 cursor-pointer">
          <input type="checkbox" name="agree" value="1" required class="h-5 w-5 accent-[#1F4D3F]">
          <span class="text-navy/80">위 내용에 <b>동의</b>합니다 <span class="text-signal-red">(필수)</span></span>
        </label>
        <button class="mt-6 w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">동의하고 계속</button>
      </form>
    </div>
  </div>
</x-layouts.app>
