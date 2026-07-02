<x-layouts.app :title="'검사 완료 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-lg mx-auto px-4 py-16">
      <div class="rounded-3xl bg-white p-10 text-center shadow-lg ring-1 ring-black/5">
        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-mint/40 text-deepgreen">
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h1 class="text-xl font-extrabold text-deepgreen">이미 검사가 완료되었습니다</h1>
        <p class="mt-2 text-navy/60 text-sm">{{ $test->title_easy }}</p>
        @if($voucher->result_visible && $voucher->used_attempt_id)
          <a href="{{ route('result.show', $voucher->used_attempt_id) }}" class="inline-block mt-6 rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">결과 보기 →</a>
        @else
          <p class="mt-6 text-sm text-navy/50">결과는 준비되는 대로 안내됩니다.</p>
        @endif
      </div>
    </div>
  </div>
</x-layouts.app>
