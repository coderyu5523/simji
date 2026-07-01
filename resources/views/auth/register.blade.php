<x-guest-layout>
    <h1 class="text-xl font-extrabold text-deepgreen text-center mb-6">회원가입</h1>

    {{-- 카카오 3초 회원가입 --}}
    <a href="{{ route('kakao.redirect') }}" class="flex items-center justify-center gap-2 rounded-xl bg-[#FEE500] py-3.5 font-bold text-[#3C1E1E] hover:brightness-95 transition">
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="M12 3C6.5 3 2 6.6 2 11c0 2.8 1.9 5.3 4.7 6.7-.2.7-.7 2.6-.8 3 0 .3.2.3.4.2.2-.1 2.5-1.7 3.5-2.4.7.1 1.4.2 2.2.2 5.5 0 10-3.6 10-8S17.5 3 12 3z"/></svg>
        카카오로 3초 회원가입
    </a>

    <div class="my-5 flex items-center gap-3 text-xs text-navy/30">
        <span class="h-px flex-1 bg-black/10"></span>또는<span class="h-px flex-1 bg-black/10"></span>
    </div>

    {{-- 개인 / 기관 선택 --}}
    <div class="space-y-3">
        <a href="{{ route('register.personal') }}" class="group flex items-center gap-4 rounded-2xl border border-black/10 p-4 hover:border-teal hover:bg-mint/10 transition">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-mint/40 text-deepgreen">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM3 22a9 9 0 0 1 18 0"/></svg>
            </span>
            <div>
                <p class="font-bold text-deepgreen">개인 회원가입</p>
                <p class="text-xs text-navy/50">이메일로 직접 가입합니다</p>
            </div>
            <span class="ml-auto text-navy/30 group-hover:translate-x-1 transition">→</span>
        </a>

        <a href="{{ route('register.institution') }}" class="group flex items-center gap-4 rounded-2xl border border-black/10 p-4 hover:border-teal hover:bg-mint/10 transition">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal/15 text-teal">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M6 21V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v16M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1"/></svg>
            </span>
            <div>
                <p class="font-bold text-deepgreen">기관 회원가입</p>
                <p class="text-xs text-navy/50">학교·기업·복지관 등 단체</p>
            </div>
            <span class="ml-auto text-navy/30 group-hover:translate-x-1 transition">→</span>
        </a>
    </div>

    <p class="mt-6 text-center text-sm text-gray-600">
        이미 회원이신가요?
        <a href="{{ route('login') }}" class="font-semibold text-teal hover:underline">로그인</a>
    </p>
</x-guest-layout>
