<x-layouts.app :title="$test->title_easy">
  <div class="bg-cream">
    <div class="max-w-4xl mx-auto px-4 py-10">
      <a href="{{ route('catalog.room', $test->room) }}" class="text-navy/50 text-sm hover:text-teal transition">← 다른 검사 보기</a>

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

      <div class="mt-8">
        @auth
          @if(!$product || $hasVoucher)
            {{-- 무료 검사거나, 유료 검사인데 검사권 보유 → 바로 응시 --}}
            <div class="flex flex-wrap gap-3">
              <a href="{{ route('assessment.consent', $test->code) }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">검사 시작</a>
              <a href="{{ route('catalog.room', $test->room) }}" class="rounded-xl border border-teal text-teal px-6 py-3.5 font-semibold hover:bg-mint/30 transition">다른 검사 보기</a>
            </div>
            @if($product)
              <p class="mt-3 text-sm text-navy/50">응시 시 보유 검사권 1개가 차감됩니다.</p>
            @endif
          @else
            {{-- 유료 검사인데 검사권 없음 → 검사권 관리로 안내 (가격/단건구매 없음, 검사권 차감 모델) --}}
            <div class="rounded-2xl bg-mint/20 p-5 ring-1 ring-black/5">
              <p class="font-bold text-deepgreen">검사권으로 응시하는 검사예요</p>
              <p class="text-sm text-navy/60 mt-1">보유한 검사권이 없습니다. 검사권을 발급해 직접 응시하거나, 링크로 다른 사람에게 전달할 수 있어요.</p>
              <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('my.index') }}" class="rounded-xl bg-deepgreen text-cream px-6 py-3 font-bold shadow-lg hover:brightness-110 transition">검사권 관리로 이동</a>
                <a href="{{ route('catalog.room', $test->room) }}" class="rounded-xl border border-teal text-teal px-6 py-3 font-semibold hover:bg-mint/30 transition">다른 검사 보기</a>
              </div>
            </div>
          @endif
        @else
          <div class="flex flex-wrap gap-3">
            <a href="{{ route('login') }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">로그인하고 검사 시작</a>
            <a href="{{ route('catalog.room', $test->room) }}" class="rounded-xl border border-teal text-teal px-6 py-3.5 font-semibold hover:bg-mint/30 transition">다른 검사 보기</a>
          </div>
          @if($product)
            <p class="mt-3 text-sm text-navy/50">검사권으로 응시하는 검사입니다. 로그인 후 이용해 주세요.</p>
          @endif
        @endauth
      </div>
    </div>
  </div>
</x-layouts.app>
