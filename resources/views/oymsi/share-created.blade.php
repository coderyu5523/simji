{{--
  공유 링크를 만든 직후 화면 (청소년 본인만 본다).
  링크는 로그인 없이 열리므로, 누구에게 보내는지와 언제 닫히는지를 분명히 알린다.
--}}
<x-layouts.app title="공유 링크가 만들어졌습니다">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">

      <h1 class="text-2xl font-extrabold text-deepgreen">공유 링크가 만들어졌습니다</h1>
      <p class="mt-3 text-sm text-navy/70">
        이 링크를 보호자에게 보내면 결과를 볼 수 있습니다.
        링크를 아는 사람은 로그인 없이 열 수 있으니 보호자에게만 보내 주세요.
      </p>

      <div class="mt-6 rounded-2xl bg-white p-4 ring-1 ring-black/5">
        <p class="text-xs text-navy/50">공유 링크</p>
        <p id="share-url" class="mt-1 break-all text-sm font-semibold text-navy">{{ $url }}</p>
        <button type="button" data-copy="{{ $url }}"
                class="mt-3 w-full rounded-xl bg-deepgreen text-cream py-3 font-bold">링크 복사하기</button>
        <p id="copy-done" class="mt-2 hidden text-center text-xs text-teal">복사했습니다</p>
      </div>

      <p class="mt-4 text-sm text-navy/60">
        {{ $expiresAt->format('Y년 n월 j일') }}까지 열려 있습니다. 그 뒤에는 링크가 저절로 닫힙니다.
      </p>

      <form method="POST" action="{{ route('oymsi.share.revoke', $attempt->id) }}" class="mt-8">
        @csrf
        <button class="w-full rounded-xl bg-white ring-1 ring-signal-red/40 text-signal-red py-3 font-bold">
          지금 공유 취소하기
        </button>
      </form>

      <a href="{{ route('result.show', $attempt->id) }}"
         class="mt-4 block text-center text-sm text-navy/50 underline">내 결과로 돌아가기</a>

    </div>
  </div>

  <script>
    document.querySelector('[data-copy]')?.addEventListener('click', function () {
      var url = this.dataset.copy;
      var done = function () { document.getElementById('copy-done').classList.remove('hidden'); };
      if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done); return; }
      var sel = window.getSelection();
      var range = document.createRange();
      range.selectNodeContents(document.getElementById('share-url'));
      sel.removeAllRanges();
      sel.addRange(range);
    });
  </script>
</x-layouts.app>
