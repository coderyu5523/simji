<x-layouts.app :title="'동의 · '.$test->title_easy">
  <div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-xl font-bold text-deepgreen">검사 전 동의</h1>
    <div class="mt-6 rounded-2xl bg-white p-6 text-sm text-navy/80 space-y-3 shadow-sm">
      <p>본 검사는 <b>민감정보(건강에 관한 정보)</b>를 수집·처리합니다. 수집된 응답은 결과 산출 및 보관 목적에만 이용됩니다.</p>
      <p>수집 항목: 검사 응답, 결과. 보관 기간: 회원 탈퇴 시 또는 법정 보관기간까지.</p>
    </div>
    <form method="POST" action="{{ route('assessment.agree', $test->code) }}" class="mt-6">
      @csrf
      <label class="flex items-center gap-2"><input type="checkbox" name="agree" value="1" required> 위 내용에 동의합니다 (필수)</label>
      <button class="mt-6 rounded-xl bg-deepgreen text-cream px-8 py-3 font-semibold">동의하고 계속</button>
    </form>
  </div>
</x-layouts.app>
