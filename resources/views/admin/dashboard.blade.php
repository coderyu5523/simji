<x-layouts.admin :title="'대시보드 · 심지 관리자'" :heading="'대시보드'">

    {{-- 요약 카드 --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['t' => '총 회원',        'v' => number_format($stats['members']).'명'],
            ['t' => '검사 수',        'v' => number_format($stats['tests']).'종'],
            ['t' => '주문 수',        'v' => number_format($stats['orders']).'건'],
            ['t' => '매출(결제완료)', 'v' => number_format($stats['revenue']).'원'],
        ] as $c)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-navy/50">{{ $c['t'] }}</p>
                <p class="text-2xl font-extrabold text-deepgreen mt-1">{{ $c['v'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-5 mt-6">
        {{-- 최근 가입 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-deepgreen">최근 가입</h2>
                <a href="{{ route('admin.members') }}" class="text-xs text-teal hover:underline">전체 보기 →</a>
            </div>
            @forelse($recentMembers as $m)
                <div class="flex items-center justify-between py-2 border-t border-black/5 first:border-0 text-sm">
                    <div>
                        <span class="font-semibold text-navy/80">{{ $m->name }}</span>
                        <span class="ml-2 rounded-full px-2 py-0.5 text-xs {{ $m->user_type === 'institution' ? 'bg-teal/15 text-teal' : 'bg-mint/40 text-deepgreen' }}">{{ $m->user_type === 'institution' ? '기관' : '개인' }}</span>
                    </div>
                    <span class="text-navy/40 text-xs">{{ $m->created_at?->format('Y-m-d') }}</span>
                </div>
            @empty
                <p class="text-sm text-navy/40 py-4 text-center">아직 가입한 회원이 없습니다.</p>
            @endforelse
        </div>

        {{-- 최근 주문 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-deepgreen">최근 주문</h2>
                <a href="{{ route('admin.orders') }}" class="text-xs text-teal hover:underline">전체 보기 →</a>
            </div>
            @forelse($recentOrders as $o)
                <div class="flex items-center justify-between py-2 border-t border-black/5 first:border-0 text-sm">
                    <div>
                        <span class="font-mono text-xs text-navy/60">{{ $o->order_no }}</span>
                        <span class="ml-2 text-navy/50">{{ $o->user?->name ?? '-' }}</span>
                    </div>
                    <span class="font-semibold text-deepgreen">{{ number_format($o->total_amount) }}원</span>
                </div>
            @empty
                <p class="text-sm text-navy/40 py-4 text-center">아직 주문이 없습니다.</p>
            @endforelse
        </div>
    </div>

</x-layouts.admin>
