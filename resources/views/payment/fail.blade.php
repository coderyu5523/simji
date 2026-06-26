<x-layouts.app :title="'결제 실패'">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-16 text-center">
      <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-signal-red/20 text-signal-red text-3xl">!</div>
      <h1 class="text-2xl font-extrabold text-deepgreen mt-5">결제가 완료되지 않았습니다</h1>
      <p class="text-navy/60 mt-2">결제가 취소되었거나 처리 중 문제가 발생했습니다. 다시 시도해 주세요.</p>
      <a href="{{ route('catalog.index') }}" class="inline-block mt-8 rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">검사 둘러보기</a>
    </div>
  </div>
</x-layouts.app>
