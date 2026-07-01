<x-layouts.admin :title="'주문·결제 · 심지 관리자'" :heading="'주문·결제'">

    @php
        $statusMap = [
            'pending'  => ['대기',   'bg-signal-yellow/20 text-amber-700'],
            'paid'     => ['결제완료','bg-signal-green/20 text-green-700'],
            'canceled' => ['취소',   'bg-black/5 text-navy/50'],
        ];
    @endphp

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-black/5 overflow-hidden">
        <div class="px-5 py-3 border-b border-black/5 text-sm text-navy/50">총 {{ number_format($orders->count()) }}건</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-black/[0.02] text-navy/50 text-left">
                        <th class="px-5 py-3 font-medium">주문번호</th>
                        <th class="px-5 py-3 font-medium">회원</th>
                        <th class="px-5 py-3 font-medium">금액</th>
                        <th class="px-5 py-3 font-medium">상태</th>
                        <th class="px-5 py-3 font-medium">일시</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                        @php [$label, $cls] = $statusMap[$o->status] ?? [$o->status, 'bg-black/5 text-navy/50']; @endphp
                        <tr class="border-t border-black/5">
                            <td class="px-5 py-3 font-mono text-xs text-navy/70">{{ $o->order_no }}</td>
                            <td class="px-5 py-3 text-navy/70">{{ $o->user?->name ?? '-' }}</td>
                            <td class="px-5 py-3 font-semibold text-deepgreen">{{ number_format($o->total_amount) }}원</td>
                            <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cls }}">{{ $label }}</span></td>
                            <td class="px-5 py-3 text-navy/40">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-navy/40">주문이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-layouts.admin>
