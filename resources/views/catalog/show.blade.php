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

      <div class="mt-8 flex flex-wrap gap-3">
        @if($product && !$hasVoucher)
          @auth
            <a href="{{ route('checkout.show', $product->id) }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">{{ number_format($product->price) }}원 구매하고 응시</a>
          @else
            <a href="{{ route('login') }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">{{ number_format($product->price) }}원 로그인하고 구매</a>
          @endauth
        @else
          @auth
            <a href="{{ route('assessment.consent', $test->code) }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">검사 시작</a>
          @else
            <a href="{{ route('login') }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">로그인하고 검사 시작</a>
          @endauth
        @endif
        <a href="{{ route('catalog.room', $test->room) }}" class="rounded-xl border border-teal text-teal px-6 py-3.5 font-semibold hover:bg-mint/30 transition">다른 검사 보기</a>
      </div>
    </div>
  </div>
</x-layouts.app>
