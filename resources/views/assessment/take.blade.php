<x-layouts.app :title="'검사 진행 · '.$test->title_easy">
  <div class="max-w-2xl mx-auto px-4 py-10">
    <div class="h-2 bg-cream rounded-full overflow-hidden"><div id="bar" class="h-2 bg-teal w-0 transition-all"></div></div>
    <p class="text-xs text-navy/50 mt-2"><span id="done">0</span> / {{ $test->items->count() }}</p>
    <form method="POST" action="{{ route('assessment.submit', [$test->code, $attempt->id]) }}" id="qform" class="mt-6 space-y-8">
      @csrf
      @foreach($test->items as $item)
        <fieldset class="rounded-2xl bg-white p-5 shadow-sm" data-q>
          <legend class="font-medium text-navy">{{ $item->no }}. {{ $item->text }}</legend>
          <div class="flex justify-between gap-2 mt-4">
            @foreach(['전혀 아니다'=>1,'아니다'=>2,'보통'=>3,'그렇다'=>4,'매우 그렇다'=>5] as $label => $v)
              <label class="flex-1 text-center text-xs cursor-pointer">
                <input type="radio" name="answers[{{ $item->id }}]" value="{{ $v }}" required class="block mx-auto mb-1">
                {{ $label }}
              </label>
            @endforeach
          </div>
        </fieldset>
      @endforeach
      <button class="w-full rounded-xl bg-deepgreen text-cream py-3 font-semibold">제출하고 결과 보기</button>
    </form>
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
