<x-layouts.app :title="$heading">
  <div class="max-w-3xl mx-auto px-4 py-24 text-center">
    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-mint/40 text-deepgreen mb-6">
      <svg viewBox="0 0 24 24" fill="none" class="h-8 w-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-deepgreen">{{ $heading }}</h1>
    <p class="mt-3 text-navy/60">준비 중인 페이지입니다.<br>곧 더 단단한 마음으로 찾아뵙겠습니다.</p>
    <a href="{{ route('home') }}" class="inline-block mt-8 rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold">홈으로</a>
  </div>
</x-layouts.app>
