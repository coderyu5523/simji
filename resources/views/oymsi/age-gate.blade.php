<x-layouts.app :title="'연령 확인 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      <h1 class="text-2xl font-extrabold text-deepgreen">먼저 나이를 확인합니다</h1>
      <p class="text-sm text-navy/60 mt-2">
        검사마다 참여할 수 있는 나이가 정해져 있습니다. 생년월일은 나이를 계산하는 데만 쓰고 저장하지 않습니다.
      </p>

      {{-- 달력 피커(type=date) 대신 숫자 입력 세 칸을 쓴다. 피커는 오늘 날짜에서 열려서
           태어난 해까지 연도를 계속 거슬러 올라가야 하는데, 나이가 많을수록 그 거리가
           길어진다. 연도를 직접 타이핑하면 그 거리가 사라진다. --}}
      <form method="POST" action="{{ $action }}" class="mt-8"
            x-data="{
              advance(e, len, next) {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, len);
                if (e.target.value.length === len && next) this.$refs[next].focus();
              }
            }">
        @csrf
        <span class="block text-sm font-semibold text-navy/80">생년월일</span>

        <div class="mt-2 grid grid-cols-[1.4fr_1fr_1fr] gap-2">
          <div>
            <input x-ref="year" @input="advance($event, 4, 'month')"
                   type="text" inputmode="numeric" name="birth_year" required
                   maxlength="4" placeholder="2010" autocomplete="bday-year"
                   value="{{ old('birth_year') }}"
                   aria-label="태어난 해"
                   class="w-full rounded-2xl border-navy/15 p-4 text-lg text-center">
            <span class="mt-1 block text-center text-xs text-navy/50">년</span>
          </div>
          <div>
            <input x-ref="month" @input="advance($event, 2, 'day')"
                   type="text" inputmode="numeric" name="birth_month" required
                   maxlength="2" placeholder="03" autocomplete="bday-month"
                   value="{{ old('birth_month') }}"
                   aria-label="태어난 달"
                   class="w-full rounded-2xl border-navy/15 p-4 text-lg text-center">
            <span class="mt-1 block text-center text-xs text-navy/50">월</span>
          </div>
          <div>
            <input x-ref="day" @input="advance($event, 2, null)"
                   type="text" inputmode="numeric" name="birth_day" required
                   maxlength="2" placeholder="15" autocomplete="bday-day"
                   value="{{ old('birth_day') }}"
                   aria-label="태어난 날"
                   class="w-full rounded-2xl border-navy/15 p-4 text-lg text-center">
            <span class="mt-1 block text-center text-xs text-navy/50">일</span>
          </div>
        </div>

        {{-- 세 칸의 오류를 한 곳에 모아 보여준다(어느 칸이 틀렸든 메시지 위치가 같도록). --}}
        @error('birthdate')
          <p class="mt-3 text-sm text-signal-red">{{ $message }}</p>
        @enderror

        <button class="mt-6 w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">
          다음
        </button>
      </form>
    </div>
  </div>
</x-layouts.app>
