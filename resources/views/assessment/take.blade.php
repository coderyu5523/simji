<x-layouts.app :title="'검사 진행 · '.$test->title_easy">
  <div class="bg-cream">
    {{-- 상단 고정 진행바 --}}
    <div class="sticky top-16 z-40 bg-cream/90 backdrop-blur border-b border-black/5">
      <div class="max-w-2xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between text-xs text-navy/50 mb-1.5">
          <span class="font-semibold text-deepgreen">{{ $test->title_easy }}</span>
          <span><span id="done">0</span> / {{ $test->items->count() }}</span>
        </div>
        <div class="h-2 bg-black/5 rounded-full overflow-hidden"><div id="bar" class="h-2 bg-gradient-to-r from-teal to-mint w-0 transition-all"></div></div>
      </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-8">
      <form method="POST" action="{{ $submitUrl ?? route('assessment.submit', [$test->code, $attempt->id]) }}" id="qform" class="space-y-5">
        @csrf
        @foreach($test->items as $item)
          <fieldset class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" data-q>
            <legend class="font-semibold text-navy flex gap-2"><span class="text-teal">{{ $item->no }}.</span> {{ $item->text }}</legend>
            <div class="flex justify-between gap-1.5 mt-5">
              @foreach(['전혀 아니다'=>1,'아니다'=>2,'보통'=>3,'그렇다'=>4,'매우 그렇다'=>5] as $label => $v)
                <label class="flex-1 cursor-pointer">
                  <input type="radio" name="answers[{{ $item->id }}]" value="{{ $v }}" required class="peer sr-only">
                  <span class="block text-center text-xs rounded-xl border border-black/10 py-2.5 px-1 text-navy/60 transition peer-checked:bg-deepgreen peer-checked:text-cream peer-checked:border-deepgreen hover:border-teal">{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </fieldset>
        @endforeach
        <button class="w-full rounded-xl bg-deepgreen text-cream py-4 font-bold shadow-lg hover:brightness-110 transition">제출하고 결과 보기</button>
      </form>
    </div>
  </div>
  <script>
    const total = {{ $test->items->count() }};
    document.getElementById('qform').addEventListener('change', () => {
      const done = document.querySelectorAll('[data-q] input:checked').length;
      document.getElementById('done').textContent = done;
      document.getElementById('bar').style.width = (done/total*100) + '%';
    });
  </script>
</x-layouts.app>
