<x-layouts.app :title="'결제 · '.$product->name">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-12">
      <h1 class="text-2xl font-extrabold text-deepgreen">검사권 구매</h1>
      <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-bold text-deepgreen">{{ $product->name }}</p>
            <p class="text-sm text-navy/50 mt-1">검사권 {{ $product->credit_qty }}장 · 유효기간 {{ $product->valid_days }}일</p>
          </div>
          <p class="text-xl font-extrabold text-deepgreen">{{ number_format($product->price) }}원</p>
        </div>
      </div>

      @if(!$order)
        <form method="POST" action="{{ route('checkout.start', $product->id) }}" class="mt-6">
          @csrf
          <button class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">결제하기</button>
        </form>
      @else
        {{-- 모의 결제 단계: 실 InicisGateway에선 이 자리에 결제창 스크립트가 들어간다 --}}
        <form method="POST" action="{{ route('payment.return') }}" class="mt-6 space-y-3">
          @csrf
          <input type="hidden" name="order_no" value="{{ $order->order_no }}">
          <input type="hidden" name="amount" value="{{ $order->total_amount }}">
          <p class="text-sm text-navy/60">아래 버튼으로 결제를 진행합니다. (테스트 결제)</p>
          <div class="flex gap-3">
            <button name="result" value="success" class="flex-1 rounded-xl bg-deepgreen text-cream py-3.5 font-bold">결제 완료</button>
            <button name="result" value="fail" class="rounded-xl border border-navy/20 text-navy/60 px-5 py-3.5">취소</button>
          </div>
        </form>
      @endif
    </div>
  </div>
</x-layouts.app>
