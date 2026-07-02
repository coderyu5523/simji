<x-layouts.app :title="'내 검사함'">
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-4xl mx-auto px-4 py-12">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-3">내 검사함</span>
      <h1 class="text-2xl md:text-3xl font-extrabold">검사권 발급하고, 결과를 관리하세요</h1>
      <p class="mt-2 text-cream/80">검사권을 링크로 전달하면, 받은 분이 로그인 없이 응시할 수 있어요.</p>
    </div>
  </section>

  @if(session('status'))
    <div class="max-w-4xl mx-auto px-4"><div class="mt-4 rounded-xl bg-mint/40 text-deepgreen px-4 py-3 text-sm font-semibold">{{ session('status') }}</div></div>
  @endif
  @error('issue')
    <div class="max-w-4xl mx-auto px-4"><div class="mt-4 rounded-xl bg-signal-red/10 text-signal-red px-4 py-3 text-sm font-semibold">{{ $message }}</div></div>
  @enderror

  {{-- 탭 --}}
  <div class="max-w-4xl mx-auto px-4 pt-6">
    <div class="flex gap-1 border-b border-black/10">
      <button type="button" data-tab="manage" class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-deepgreen text-deepgreen">검사권 관리</button>
      <button type="button" data-tab="history" class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-navy/40">내가 응시한 검사</button>
    </div>
  </div>

  {{-- ===== 탭 1: 검사권 관리 ===== --}}
  <div data-panel="manage" class="bg-cream">
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

      {{-- 발급 폼 --}}
      <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h2 class="font-bold text-deepgreen mb-4">검사권 발급</h2>
        <form method="POST" action="{{ route('my.issue') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
          @csrf
          <div class="flex-1">
            <label class="block text-xs font-semibold text-navy/60 mb-1.5">검사 선택</label>
            <select name="test_id" required class="w-full rounded-xl border border-black/10 px-4 py-3 text-sm focus:border-teal focus:ring-teal">
              <option value="">검사를 선택하세요</option>
              @foreach($issuableTests as $t)
                @php $ok = !$t->is_paid || $t->available_credits > 0; @endphp
                <option value="{{ $t->id }}" @unless($ok) disabled @endunless>
                  {{ $t->title_easy }} — {{ $t->is_paid ? '유료 (보유 '.$t->available_credits.'개)' : '무료' }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="w-full sm:w-28">
            <label class="block text-xs font-semibold text-navy/60 mb-1.5">수량</label>
            <input type="number" name="qty" value="1" min="1" max="100" required class="w-full rounded-xl border border-black/10 px-4 py-3 text-sm focus:border-teal focus:ring-teal">
          </div>
          <button class="rounded-xl bg-deepgreen text-cream px-6 py-3 font-bold hover:brightness-110 transition whitespace-nowrap">발급</button>
        </form>
        <p class="mt-3 text-xs text-navy/40">유료 검사는 보유 검사권에서 차감됩니다. 무료 검사는 바로 발급됩니다.</p>
      </div>

      {{-- 대량 발급 (엑셀) — 화면만, 실제 기능은 정식 단계에 구현 --}}
      <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex items-center gap-2 mb-1">
          <h2 class="font-bold text-deepgreen">대량 발급 <span class="text-navy/40 font-normal text-sm">(엑셀 업로드)</span></h2>
          <span class="rounded-full bg-signal-yellow/20 text-signal-yellow text-xs font-bold px-2.5 py-0.5">준비 중</span>
        </div>
        <p class="text-sm text-navy/60">기관에서 여러 명에게 한 번에 발급할 때 사용합니다. 엑셀에 <b>이름·전화번호</b>만 넣어 업로드하면 명단 수만큼 검사권이 만들어지고, 각자에게 문자로 링크를 보낼 수 있어요. <span class="text-navy/40">(문자 발송은 추후 제공)</span></p>

        <div class="mt-4 flex flex-wrap gap-3 items-end">
          <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-semibold text-navy/60 mb-1.5">검사 선택</label>
            <select disabled class="w-full rounded-xl border border-black/10 bg-black/5 px-4 py-3 text-sm text-navy/40">
              <option>검사를 선택하세요</option>
            </select>
          </div>
          <button type="button" onclick="alert('준비 중인 기능입니다.')" class="rounded-xl border border-teal text-teal px-4 py-3 text-sm font-semibold hover:bg-mint/20 transition">엑셀 양식 다운로드</button>
          <button type="button" onclick="alert('준비 중인 기능입니다.')" class="rounded-xl bg-black/5 text-navy/50 px-5 py-3 text-sm font-bold hover:bg-black/10 transition">엑셀 파일 업로드</button>
        </div>

        {{-- 업로드 결과 미리보기 (예시 화면) --}}
        <div class="mt-5">
          <p class="text-xs text-navy/40 mb-2">업로드하면 이렇게 명단이 검사권으로 만들어집니다 <span class="italic">(예시 화면)</span></p>
          <div class="overflow-hidden rounded-2xl ring-1 ring-black/5">
            <table class="w-full text-sm">
              <thead class="bg-cream text-navy/50 text-xs">
                <tr><th class="text-left px-4 py-2 font-semibold">이름</th><th class="text-left px-4 py-2 font-semibold">전화번호</th><th class="text-left px-4 py-2 font-semibold">상태</th></tr>
              </thead>
              <tbody class="divide-y divide-black/5 bg-white text-navy/60">
                @foreach([['김○○','010-1234-****'],['이○○','010-5678-****'],['박○○','010-9012-****']] as [$nm,$ph])
                  <tr>
                    <td class="px-4 py-2.5">{{ $nm }}</td>
                    <td class="px-4 py-2.5">{{ $ph }}</td>
                    <td class="px-4 py-2.5"><span class="rounded-full bg-signal-yellow/20 text-signal-yellow text-xs font-semibold px-2 py-0.5">발급 대기</span></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- 발급 명부 --}}
      <div>
        <h2 class="font-bold text-deepgreen mb-4">발급한 검사권 <span class="text-sm text-navy/40 font-normal">({{ $issued->count() }})</span></h2>
        @if($issued->isEmpty())
          <p class="text-sm text-navy/50 rounded-2xl bg-white p-6 ring-1 ring-black/5">아직 발급한 검사권이 없습니다. 위에서 검사권을 발급해 보세요.</p>
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
                        응시자: {{ $v->recipient_name }}@if($v->recipient_age) ({{ $v->recipient_age }})@endif · {{ optional($v->attempt->submitted_at)->format('Y.m.d') }}
                      @else
                        <span class="text-signal-yellow font-semibold">미응시</span> · 발급 {{ optional($v->assigned_at)->format('Y.m.d') }}
                      @endif
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    @if($done && $v->attempt->result)
                      <x-signal-badge :signal="$v->attempt->result->overall_signal"/>
                    @endif
                    @if($done && $v->attempt->result)
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
