<x-layouts.app :title="'내 검사함'">
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-4xl mx-auto px-4 py-12">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-3">내 검사함</span>
      <h1 class="text-2xl md:text-3xl font-extrabold">발급한 검사를 관리하고, 결과를 확인하세요</h1>
      <p class="mt-2 text-cream/80">검사권 발급은 <b>심리검사</b>에서 검사를 고른 뒤 진행합니다. 발급한 링크와 결과는 여기서 관리해요.</p>
    </div>
  </section>

  @if(session('status'))
    <div class="max-w-4xl mx-auto px-4"><div class="mt-4 rounded-xl bg-mint/40 text-deepgreen px-4 py-3 text-sm font-semibold">{{ session('status') }}</div></div>
  @endif

  {{-- 탭 --}}
  <div class="max-w-4xl mx-auto px-4 pt-6">
    <div class="flex gap-1 border-b border-black/10">
      <button type="button" data-tab="manage" class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-deepgreen text-deepgreen">발급·결과 관리</button>
      <button type="button" data-tab="history" class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-navy/40">내가 응시한 검사</button>
    </div>
  </div>

  {{-- ===== 탭 1: 발급·결과 관리 ===== --}}
  <div data-panel="manage" class="bg-cream">
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

      {{-- 발급 안내 (발급은 심리검사에서) --}}
      <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 class="font-bold text-deepgreen">검사권 발급은 심리검사에서</h2>
          <p class="text-sm text-navy/60 mt-1">발급할 검사를 고르면 그 자리에서 수량을 정해 발급할 수 있어요. 보유 미발급 검사권 <b class="text-deepgreen">{{ $ownedCount }}</b>개.</p>
        </div>
        <a href="{{ route('catalog.index') }}" class="rounded-xl bg-deepgreen text-cream px-6 py-3 font-bold hover:brightness-110 transition whitespace-nowrap">심리검사로 이동</a>
      </div>

      {{-- 발급 명부 --}}
      <div>
        <h2 class="font-bold text-deepgreen mb-4">발급한 검사권 <span class="text-sm text-navy/40 font-normal">({{ $issued->count() }})</span></h2>
        @if($issued->isEmpty())
          <p class="text-sm text-navy/50 rounded-2xl bg-white p-6 ring-1 ring-black/5">아직 발급한 검사권이 없습니다. 심리검사에서 검사를 골라 발급해 보세요.</p>
        @else
          <div class="space-y-3">
            @foreach($issued as $v)
              @php $done = $v->status === 'used' && $v->attempt; @endphp
              <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="font-bold text-deepgreen">{{ $v->test->title_easy }}</p>
                    <p class="text-xs text-navy/50 mt-0.5">
                      @if($done)
                        응시자: {{ $v->recipient_name ?? $v->attempt?->nickname ?? '이름 없음' }}@if($v->recipient_age) ({{ $v->recipient_age }})@endif · {{ optional($v->attempt->submitted_at)->format('Y.m.d') }}
                      @else
                        <span class="text-signal-yellow font-semibold">미응시</span> · 발급 {{ optional($v->assigned_at)->format('Y.m.d') }}
                      @endif
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    @if($done && $v->attempt->result)
                      <x-signal-badge :signal="$v->attempt->result->overall_signal"/>
                      <a href="{{ route('result.show', $v->attempt->id) }}" class="text-sm font-semibold text-teal hover:text-deepgreen transition">결과 보기 →</a>
                    @endif
                  </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                  {{-- 응시 링크 복사 --}}
                  <input type="text" readonly value="{{ route('link.landing', $v->access_token) }}"
                         class="flex-1 min-w-0 rounded-lg border border-black/10 bg-cream/50 px-3 py-2 text-xs text-navy/60" onclick="this.select()">
                  <button type="button" onclick="copyLink(this)" data-link="{{ route('link.landing', $v->access_token) }}"
                          class="rounded-lg bg-teal/10 text-teal px-3 py-2 text-xs font-bold hover:bg-teal/20 transition whitespace-nowrap">링크 복사</button>

                  {{-- 결과 열람 승인/대기 토글 --}}
                  @if($done)
                    <form method="POST" action="{{ route('my.voucher.visibility', $v->id) }}">
                      @csrf
                      <button class="rounded-lg px-3 py-2 text-xs font-bold transition whitespace-nowrap {{ $v->result_visible ? 'bg-signal-green/15 text-signal-green' : 'bg-black/5 text-navy/50' }}">
                        {{ $v->result_visible ? '열람 승인됨' : '열람 대기' }}
                      </button>
                    </form>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- ===== 탭 2: 내가 응시한 검사 ===== --}}
  <div data-panel="history" class="bg-cream hidden">
    <div class="max-w-3xl mx-auto px-4 py-8">
      @if($attempts->isEmpty())
        <div class="rounded-3xl bg-white p-12 text-center shadow-sm ring-1 ring-black/5">
          <p class="text-navy/60">아직 직접 응시한 검사가 없어요.<br>마음방에서 검사를 시작해 보세요.</p>
          <a href="{{ route('catalog.index') }}" class="inline-block mt-5 rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">심리검사 보러가기</a>
        </div>
      @else
        <ul class="space-y-3">
          @foreach($attempts as $a)
            <li class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5 flex items-center justify-between hover:shadow-md transition">
              <div>
                <p class="font-bold text-deepgreen">{{ $a->test->title_easy }}</p>
                <p class="text-xs text-navy/40 mt-0.5">{{ $a->submitted_at?->format('Y. m. d') }}</p>
              </div>
              <div class="flex items-center gap-4">
                @if($a->result) <x-signal-badge :signal="$a->result->overall_signal"/> @endif
                <a href="{{ route('result.show', $a->id) }}" class="text-sm font-semibold text-teal hover:text-deepgreen transition">결과 보기 →</a>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>

  <script>
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => {
          const on = b.dataset.tab === tab;
          b.classList.toggle('border-deepgreen', on);
          b.classList.toggle('text-deepgreen', on);
          b.classList.toggle('border-transparent', !on);
          b.classList.toggle('text-navy/40', !on);
        });
        document.querySelectorAll('[data-panel]').forEach(p => {
          p.classList.toggle('hidden', p.dataset.panel !== tab);
        });
      });
    });
    function copyLink(el) {
      navigator.clipboard.writeText(el.dataset.link).then(() => {
        const t = el.textContent; el.textContent = '복사됨!';
        setTimeout(() => el.textContent = t, 1500);
      });
    }
  </script>
</x-layouts.app>
