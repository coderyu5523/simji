<x-layouts.app :title="'결제 완료'">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-16 text-center">
      <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-mint/50 text-deepgreen text-3xl">✓</div>
      <h1 class="text-2xl font-extrabold text-deepgreen mt-5">결제가 완료되었습니다</h1>
      <p class="text-navy/60 mt-2">주문번호 {{ $order->order_no }}</p>
      @php $item = $order->items->first(); @endphp
      @if($item)
        <p class="text-navy/70 mt-1">{{ $item->product_name }} · 검사권 {{ $item->credit_qty * $item->quantity }}장 발급</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
          <a href="{{ route('catalog.show', \App\Models\Test::find($item->test_id)->code) }}" class="rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">검사 시작하기</a>
          <a href="{{ route('my.index') }}" class="rounded-xl border border-teal text-teal px-6 py-3 font-semibold hover:bg-mint/30 transition">내 검사함</a>
        </div>
      @endif
    </div>
  </div>
</x-layouts.app>
