<x-layouts.app :title="'연령 확인 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      <h1 class="text-2xl font-extrabold text-deepgreen">먼저 나이를 확인합니다</h1>
      <p class="text-sm text-navy/60 mt-2">
        검사마다 참여할 수 있는 나이가 정해져 있습니다. 생년월일은 나이를 계산하는 데만 쓰고 저장하지 않습니다.
      </p>

      <form method="POST" action="{{ $action }}" class="mt-8">
        @csrf
        <label class="block text-sm font-semibold text-navy/80" for="birthdate">생년월일</label>
        <input id="birthdate" type="date" name="birthdate" required
               class="mt-2 w-full rounded-2xl border-navy/15 p-4 text-lg">
        @error('birthdate')
          <p class="mt-2 text-sm text-signal-red">{{ $message }}</p>
        @enderror

        <button class="mt-6 w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">
          다음
        </button>
      </form>
    </div>
  </div>
</x-layouts.app>
