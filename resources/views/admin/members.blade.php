<x-layouts.admin :title="'회원 · 심지 관리자'" :heading="'회원 목록'">

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-black/5 overflow-hidden">
        <div class="px-5 py-3 border-b border-black/5 text-sm text-navy/50">총 {{ number_format($members->count()) }}명</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-black/[0.02] text-navy/50 text-left">
                        <th class="px-5 py-3 font-medium">이름</th>
                        <th class="px-5 py-3 font-medium">유형</th>
                        <th class="px-5 py-3 font-medium">기관명</th>
                        <th class="px-5 py-3 font-medium">이메일</th>
                        <th class="px-5 py-3 font-medium">전화</th>
                        <th class="px-5 py-3 font-medium">가입일</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $m)
                        <tr class="border-t border-black/5">
                            <td class="px-5 py-3 font-semibold text-navy/80">{{ $m->name }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $m->user_type === 'institution' ? 'bg-teal/15 text-teal' : 'bg-mint/40 text-deepgreen' }}">{{ $m->user_type === 'institution' ? '기관' : '개인' }}</span>
                            </td>
                            <td class="px-5 py-3 text-navy/60">{{ $m->organization ?? '-' }}</td>
                            <td class="px-5 py-3 text-navy/60">{{ $m->email }}</td>
                            <td class="px-5 py-3 text-navy/60">{{ $m->phone ?? '-' }}</td>
                            <td class="px-5 py-3 text-navy/40">{{ $m->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-navy/40">회원이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-layouts.admin>
