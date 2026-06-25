<x-layouts.app :title="'내 검사함'">
  <div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold text-deepgreen">내 검사함</h1>
    @if($attempts->isEmpty())
      <div class="mt-12 text-center">
        <img src="{{ asset('images/etc/empty.png') }}" class="h-32 mx-auto mb-4 bg-cream rounded-2xl" alt="">
        <p class="text-navy/60">아직 받은 검사가 없어요. 마음방에서 검사를 시작해 보세요.</p>
        <a href="{{ route('catalog.index') }}" class="inline-block mt-4 rounded-xl bg-deepgreen text-cream px-6 py-2">심리검사 보러가기</a>
      </div>
    @else
      <ul class="mt-6 space-y-3">
        @foreach($attempts as $a)
          <li class="rounded-2xl bg-white p-4 shadow-sm flex items-center justify-between">
            <div>
              <p class="font-medium text-navy">{{ $a->test->title_easy }}</p>
              <p class="text-xs text-navy/50">{{ $a->submitted_at?->format('Y.m.d') }}</p>
            </div>
            <div class="flex items-center gap-3">
              @if($a->result) <x-signal-badge :signal="$a->result->overall_signal"/> @endif
              <a href="{{ route('result.show', $a->id) }}" class="text-sm text-teal">결과 보기</a>
            </div>
          </li>
        @endforeach
      </ul>
    @endif
  </div>
</x-layouts.app>
