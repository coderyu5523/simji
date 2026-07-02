<x-layouts.app :title="'검사 시작 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-lg mx-auto px-4 py-12">
      <div class="rounded-3xl bg-white p-8 shadow-lg ring-1 ring-black/5">
        <span class="inline-block rounded-full bg-mint/40 text-deepgreen px-3 py-1 text-xs font-semibold mb-3">전달받은 검사</span>
        <h1 class="text-2xl font-extrabold text-deepgreen">{{ $test->title_easy }}</h1>
        @if($test->description)
          <p class="mt-2 text-navy/60 text-sm leading-relaxed">{{ $test->description }}</p>
        @endif
        <div class="mt-4 flex flex-wrap gap-2 text-xs text-navy/50">
          @if($test->item_count)<span class="rounded-full bg-black/5 px-3 py-1">문항 {{ $test->item_count }}개</span>@endif
          @if($test->duration_min)<span class="rounded-full bg-black/5 px-3 py-1">약 {{ $test->duration_min }}분</span>@endif
          @if($test->target)<span class="rounded-full bg-black/5 px-3 py-1">{{ $test->target }}</span>@endif
        </div>

        <form method="POST" action="{{ route('link.start', $voucher->access_token) }}" class="mt-7 space-y-4">
          @csrf
          <div>
            <label class="block text-sm font-semibold text-navy mb-1.5">응시자 이름 <span class="text-signal-red">*</span></label>
            <input type="text" name="recipient_name" value="{{ old('recipient_name') }}" required maxlength="100"
                   class="w-full rounded-xl border border-black/10 px-4 py-3 focus:border-teal focus:ring-teal"
                   placeholder="검사받는 분의 이름">
            @error('recipient_name')<p class="text-xs text-signal-red mt-1">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="block text-sm font-semibold text-navy mb-1.5">나이 <span class="text-navy/40 font-normal">(선택)</span></label>
            <input type="text" name="recipient_age" value="{{ old('recipient_age') }}" maxlength="20"
                   class="w-full rounded-xl border border-black/10 px-4 py-3 focus:border-teal focus:ring-teal"
                   placeholder="예: 만 8세 / 25">
          </div>
          <button class="w-full rounded-xl bg-deepgreen text-cream py-4 font-bold shadow-lg hover:brightness-110 transition">검사 시작하기</button>
        </form>
        <p class="mt-4 text-xs text-navy/40 text-center">입력하신 정보는 결과 안내 목적으로만 사용됩니다.</p>
      </div>
    </div>
  </div>
</x-layouts.app>
