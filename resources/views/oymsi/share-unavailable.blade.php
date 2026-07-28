{{--
  공유가 막혔을 때 청소년에게 보여 주는 안내 (차단은 유지, 이유만 보여 준다).

  차단 자체를 푸는 화면이 아니다 — 응답 상태코드는 404(미채점)·403(열람 대기) 그대로다.
  기존 result.pending 과 같은 결로, 위기 상태일 수 있는 청소년이 공유를 시도했다가
  프레임워크 기본 오류 페이지를 마주하지 않게 하려는 것이다.
  수신자가 청소년 본인이므로 어투는 반말이다.
--}}
@php
  $copy = [
    'not_scored' => [
      'title' => '아직 결과가 준비되지 않았어',
      'body' => '검사를 끝까지 마치면 결과가 만들어져. 결과가 나온 다음에 보호자와 공유할 수 있어.',
    ],
    'result_hidden' => [
      'title' => '지금은 결과를 공유할 수 없어',
      'body' => '검사를 발급한 곳에서 결과 공개를 준비하고 있어. 공개되면 그때 보호자와 공유할 수 있어.',
    ],
  ][$reason];
@endphp

<x-layouts.app title="공유할 수 없어">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-lg mx-auto px-4 py-16">
      <div class="rounded-3xl bg-white p-10 text-center shadow-lg ring-1 ring-black/5">
        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-signal-yellow/20 text-signal-yellow">
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        </div>
        <h1 class="text-xl font-extrabold text-deepgreen">{{ $copy['title'] }}</h1>
        <p class="mt-3 text-sm leading-relaxed text-navy/60">{{ $copy['body'] }}</p>

        <a href="{{ route('my.index') }}"
           class="mt-6 inline-block rounded-xl bg-deepgreen px-6 py-3 text-sm font-bold text-cream">내 검사함으로</a>
      </div>

      {{-- 공유가 막힌 것과 상관없이 도움 연락처는 남겨 둔다. --}}
      <div class="mt-6 rounded-2xl bg-deepgreen/5 p-5 ring-1 ring-deepgreen/10">
        <p class="text-sm text-navy/70">지금 이야기하고 싶은 게 있으면 여기로 연락하면 돼.</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
          <a href="tel:109" class="rounded-xl bg-signal-red py-3 text-center font-bold text-white">자살예방 상담 109</a>
          <a href="tel:1388" class="rounded-xl bg-teal py-3 text-center font-bold text-white">청소년상담 1388</a>
        </div>
      </div>
    </div>
  </div>
</x-layouts.app>
