<x-layouts.app :title="$test->title_easy">
  <div class="bg-cream">
    <div class="max-w-4xl mx-auto px-4 py-10">
      <a href="{{ route('catalog.room', $test->room) }}" class="text-navy/50 text-sm hover:text-teal transition">← 다른 검사 보기</a>

      @error('issue')
        <div class="mt-4 rounded-xl bg-signal-red/10 text-signal-red px-4 py-3 text-sm font-semibold">{{ $message }}</div>
      @enderror

      <div class="mt-4 grid md:grid-cols-2 gap-8 items-start">
        <img src="{{ asset($test->thumbnail ?: 'images/tests/placeholder.png') }}" class="h-56 md:h-64 w-full object-cover rounded-3xl shadow-lg ring-1 ring-black/5" alt="{{ $test->title_easy }}">
        <div>
          <span class="inline-block rounded-full bg-teal/10 text-teal px-3 py-1 text-xs font-semibold mb-3">심리검사</span>
          <h1 class="text-2xl md:text-3xl font-extrabold text-deepgreen">{{ $test->title_easy }}</h1>
          <p class="text-sm text-navy/40 mt-1">{{ $test->title_pro }}</p>
          <p class="mt-4 text-navy/70 leading-relaxed">{{ $test->description }}</p>
        </div>
      </div>

      <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([['대상',$test->target],['소요시간','약 '.$test->duration_min.'분'],['문항수',$test->item_count.'문항'],['결과형태','신호등 리포트']] as [$k,$v])
          <div class="rounded-2xl bg-white p-4 shadow-sm">
            <dt class="text-xs text-navy/50">{{ $k }}</dt>
            <dd class="font-bold text-deepgreen mt-1">{{ $v }}</dd>
          </div>
        @endforeach
      </div>

      <div class="mt-5 rounded-2xl bg-white p-5 shadow-sm">
        <p class="text-xs text-navy/50 mb-2">평가영역</p>
        <div class="flex flex-wrap gap-2">
          @foreach($test->areas as $area)
            <span class="rounded-full bg-mint/40 text-deepgreen px-4 py-1.5 text-sm font-medium">{{ $area }}</span>
          @endforeach
        </div>
      </div>

      {{-- 두 가지 이용 방법을 한눈에: 직접 응시 | 여러 명에게 발급 --}}
      <div class="mt-8 grid md:grid-cols-2 gap-4">

        {{-- ① 직접 응시 --}}
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 flex flex-col">
          <h2 class="font-bold text-deepgreen">직접 응시하기</h2>
          <p class="text-sm text-navy/60 mt-2 flex-1">내가 바로 검사를 받고 신호등 결과를 확인해요.</p>
          <div class="mt-4">
            @auth
              @if(!$product || $hasVoucher)
                <a href="{{ route('assessment.consent', $test->code) }}" class="block text-center rounded-xl bg-deepgreen text-cream px-6 py-3.5 font-bold shadow-lg hover:brightness-110 transition">검사 시작</a>
                @if($product)<p class="mt-2 text-xs text-navy/40">보유 검사권 1개가 차감됩니다.</p>@endif
              @else
                <div class="rounded-xl bg-black/5 text-navy/50 px-4 py-3.5 text-sm text-center">보유한 검사권이 없어요.<br>오른쪽에서 발급 후 이용해 주세요.</div>
              @endif
            @else
              <a href="{{ route('login') }}" class="block text-center rounded-xl bg-deepgreen text-cream px-6 py-3.5 font-bold shadow-lg hover:brightness-110 transition">로그인하고 검사 시작</a>
            @endauth
          </div>
        </div>

        {{-- ② 여러 명에게 발급 --}}
        <div class="rounded-3xl bg-mint/20 p-6 shadow-sm ring-1 ring-black/5 flex flex-col">
          <h2 class="font-bold text-deepgreen">여러 명에게 발급하기</h2>
          <p class="text-sm text-navy/60 mt-2 flex-1">응시 링크를 만들어 전달해요. 받은 분은 로그인 없이 응시하고, 결과는 <a href="{{ route('my.index') }}" class="text-teal font-semibold">내 검사함</a>에서 관리.</p>
          <div class="mt-4">
            @auth
              <form method="POST" action="{{ route('my.issue') }}" class="flex flex-wrap gap-2 items-end">
                @csrf
                <input type="hidden" name="test_id" value="{{ $test->id }}">
                <div class="w-24">
                  <label class="block text-xs font-semibold text-navy/60 mb-1.5">수량</label>
                  <input type="number" name="qty" value="1" min="1" max="100" required class="w-full rounded-xl border border-black/10 px-3 py-3 text-sm bg-white focus:border-teal focus:ring-teal">
                </div>
                <button class="flex-1 rounded-xl bg-teal text-white px-5 py-3 font-bold shadow-lg hover:brightness-110 transition whitespace-nowrap">검사권 발급</button>
              </form>
              <button type="button" onclick="alert('준비 중인 기능입니다.')" class="mt-2 w-full rounded-xl border border-teal/60 text-teal px-4 py-2.5 text-sm font-semibold hover:bg-mint/30 transition">여러 명 한 번에 (엑셀) <span class="text-xs align-top">준비중</span></button>
              @if($product)<p class="mt-2 text-xs text-navy/40">유료 검사는 보유 검사권에서 차감됩니다.</p>@endif
            @else
              <a href="{{ route('login') }}" class="block text-center rounded-xl border border-teal text-teal px-6 py-3.5 font-bold hover:bg-mint/30 transition">로그인하고 발급하기</a>
            @endauth
          </div>
        </div>
      </div>

      <div class="mt-5">
        <a href="{{ route('catalog.room', $test->room) }}" class="text-sm text-navy/50 hover:text-teal transition">← 이 방의 다른 검사 보기</a>
      </div>
    </div>
  </div>
</x-layouts.app>
