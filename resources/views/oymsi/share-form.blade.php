{{--
  보호자와 공유할지 고르는 화면 (청소년 본인만 본다).

  spec §5.3 — 자살안전 S2 이상 또는 환경위험 E2 이상이면 "공유"가 아니라 "연결"이
  먼저다. 위기 상태의 청소년에게 보호자 공유를 1순위로 들이밀지 않는다.
  환경위험 발동 문항에는 가정 내 폭력·학대가 포함되므로 이 분기에 함께 넣는다.
  공유를 막지는 않는다 — 같은 축에 학교폭력·온라인 성착취처럼 가정 밖 출처도 있어서
  일괄 차단하면 도와줄 수 있는 보호자까지 끊긴다. 눈에 덜 띄는 2차 선택으로 남긴다.
--}}
<x-layouts.app title="보호자와 공유">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">

      @if($needsContactFirst)
        <h1 class="text-2xl font-extrabold text-deepgreen">지금은 먼저 이야기할 사람이 필요해 보입니다</h1>
        <p class="mt-3 text-sm text-navy/70">
          결과를 보호자와 나누는 것보다, 지금 상담자와 이야기하는 것이 먼저입니다.
          누구에게 알릴지는 이야기하면서 함께 정해도 됩니다.
        </p>
        <div class="mt-6 grid gap-2">
          <a href="tel:109" class="rounded-xl bg-signal-red text-white py-4 text-center font-bold">109 자살예방 상담</a>
          <a href="tel:1388" class="rounded-xl bg-teal text-white py-4 text-center font-bold">1388 청소년 상담</a>
        </div>

        <form method="POST" action="{{ route('oymsi.share.create', $attempt->id) }}" class="mt-8">
          @csrf
          <button class="text-sm text-navy/45 underline">그래도 보호자와 공유하기</button>
        </form>
        <a href="{{ route('result.show', $attempt->id) }}"
           class="mt-3 block text-sm text-navy/45 underline">결과로 돌아가기</a>
      @else
        <h1 class="text-2xl font-extrabold text-deepgreen">보호자와 공유할까요?</h1>
        <p class="mt-3 text-sm text-navy/70">
          공유하면 보호자가 결과 요약과 도와줄 방법을 볼 수 있습니다.
          어떻게 답했는지 문항별 내용은 보이지 않습니다. 언제든 공유를 취소할 수 있습니다.
        </p>
        <form method="POST" action="{{ route('oymsi.share.create', $attempt->id) }}" class="mt-8">
          @csrf
          <button class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold">공유 링크 만들기</button>
        </form>
        <a href="{{ route('result.show', $attempt->id) }}"
           class="mt-4 block text-center text-sm text-navy/50 underline">지금은 하지 않기</a>
      @endif

      @if($existing)
        {{-- 이미 살아 있는 링크가 있으면 새로 만들지 않고 그것을 다시 보여준다. --}}
        <div class="mt-10 rounded-2xl bg-white p-5 ring-1 ring-black/5">
          <p class="text-sm font-semibold text-navy">이미 만들어 둔 공유 링크가 있습니다</p>
          <p class="mt-1 text-xs text-navy/50">
            {{ $existing->expires_at?->format('Y년 n월 j일') }}까지 열려 있습니다.
          </p>
          <form method="POST" action="{{ route('oymsi.share.revoke', $attempt->id) }}" class="mt-3">
            @csrf
            <button class="text-sm text-signal-red underline">공유 취소하기</button>
          </form>
        </div>
      @endif

    </div>
  </div>
</x-layouts.app>
