<x-layouts.app :title="'기본정보 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      <p class="text-sm text-teal font-semibold"><span class="text-navy/40">1 동의 ·</span> 2 기본정보 · <span class="text-navy/40">3 검사</span></p>
      <h1 class="text-2xl font-extrabold text-deepgreen mt-2">어떻게 부르면 좋을까요?</h1>
      <p class="text-sm text-navy/60 mt-2">결과지에 이 이름이 표시됩니다. 실명이 아니어도 괜찮습니다.</p>

      <form method="POST" action="{{ route('oymsi.profile.submit', $test->code) }}" class="mt-8 space-y-6">
        @csrf
        <div>
          <label class="block text-sm font-semibold text-navy/80" for="nickname">이름 또는 별명</label>
          <input id="nickname" name="nickname" type="text" maxlength="50" required
                 value="{{ old('nickname') }}"
                 class="mt-2 w-full rounded-2xl border-navy/15 p-4 text-lg" placeholder="예: 민수">
          @error('nickname')<p class="mt-2 text-sm text-signal-red">{{ $message }}</p>@enderror
        </div>

        <div>
          <span class="block text-sm font-semibold text-navy/80">성별</span>
          <div class="mt-2 grid grid-cols-3 gap-2">
            @foreach(['male' => '남', 'female' => '여', 'no_answer' => '응답하지 않음'] as $value => $label)
              <label class="cursor-pointer">
                <input type="radio" name="gender" value="{{ $value }}" class="peer sr-only"
                       @checked(old('gender') === $value) required>
                <span class="block rounded-2xl bg-white p-4 text-center text-sm ring-1 ring-black/5
                             peer-checked:bg-deepgreen peer-checked:text-cream peer-checked:font-bold">
                  {{ $label }}
                </span>
              </label>
            @endforeach
          </div>
          @error('gender')<p class="mt-2 text-sm text-signal-red">{{ $message }}</p>@enderror
        </div>

        <button class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">
          검사 시작하기
        </button>
      </form>
    </div>
  </div>
</x-layouts.app>
