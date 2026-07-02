<x-layouts.app :title="'결과 준비 중 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-lg mx-auto px-4 py-16">
      <div class="rounded-3xl bg-white p-10 text-center shadow-lg ring-1 ring-black/5">
        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-signal-yellow/20 text-signal-yellow">
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        </div>
        <h1 class="text-xl font-extrabold text-deepgreen">결과 준비 중입니다</h1>
        <p class="mt-3 text-navy/60 text-sm leading-relaxed">{{ $test->title_easy }} 검사가 제출되었습니다.<br>결과는 담당자의 확인 후 공개됩니다.</p>
      </div>
    </div>
  </div>
</x-layouts.app>
