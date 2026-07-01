<x-layouts.admin :title="'검사 · 심지 관리자'" :heading="'검사 목록'">

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-black/5 overflow-hidden">
        <div class="px-5 py-3 border-b border-black/5 text-sm text-navy/50">총 {{ number_format($tests->count()) }}종</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-black/[0.02] text-navy/50 text-left">
                        <th class="px-5 py-3 font-medium">코드</th>
                        <th class="px-5 py-3 font-medium">검사명</th>
                        <th class="px-5 py-3 font-medium">연령방</th>
                        <th class="px-5 py-3 font-medium">문항</th>
                        <th class="px-5 py-3 font-medium">응시</th>
                        <th class="px-5 py-3 font-medium">상태</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tests as $t)
                        <tr class="border-t border-black/5">
                            <td class="px-5 py-3 font-mono text-xs text-navy/60">{{ $t->code }}</td>
                            <td class="px-5 py-3 font-semibold text-navy/80">{{ $t->title_easy }}</td>
                            <td class="px-5 py-3 text-navy/60">{{ $t->room }}</td>
                            <td class="px-5 py-3 text-navy/60">{{ $t->item_count }}문항</td>
                            <td class="px-5 py-3 text-navy/70 font-semibold">{{ number_format($attemptCounts[$t->id] ?? 0) }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $t->status === 'active' ? 'bg-signal-green/20 text-green-700' : 'bg-black/5 text-navy/50' }}">{{ $t->status === 'active' ? '활성' : $t->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-navy/40">검사가 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-layouts.admin>
