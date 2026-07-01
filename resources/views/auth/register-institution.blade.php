<x-guest-layout>
    <div class="flex items-center gap-2 mb-6">
        <a href="{{ route('register') }}" class="text-navy/40 hover:text-teal transition" aria-label="뒤로">←</a>
        <h1 class="text-xl font-extrabold text-deepgreen">기관 회원가입</h1>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="user_type" value="institution">

        {{-- 기관명 --}}
        <div>
            <label for="organization" class="block text-sm font-semibold text-deepgreen mb-1.5">기관명 <span class="text-signal-red">*</span></label>
            <input id="organization" name="organization" type="text" value="{{ old('organization') }}" required autofocus placeholder="○○초등학교 / ○○복지관"
                   class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            @error('organization') <p class="text-signal-red text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 담당자명 --}}
        <div>
            <label for="name" class="block text-sm font-semibold text-deepgreen mb-1.5">담당자명 <span class="text-signal-red">*</span></label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" placeholder="홍길동"
                   class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            @error('name') <p class="text-signal-red text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 이메일 --}}
        <div>
            <label for="email" class="block text-sm font-semibold text-deepgreen mb-1.5">이메일 <span class="text-signal-red">*</span></label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" placeholder="name@example.com"
                   class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            @error('email') <p class="text-signal-red text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 전화번호 --}}
        <div>
            <label for="phone" class="block text-sm font-semibold text-deepgreen mb-1.5">전화번호 <span class="text-signal-red">*</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" placeholder="02-123-4567"
                   class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            @error('phone') <p class="text-signal-red text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 비밀번호 --}}
        <div>
            <label for="password" class="block text-sm font-semibold text-deepgreen mb-1.5">비밀번호 <span class="text-signal-red">*</span></label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
            @error('password') <p class="text-signal-red text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- 비밀번호 확인 --}}
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-deepgreen mb-1.5">비밀번호 확인 <span class="text-signal-red">*</span></label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-navy/80 focus:outline-none focus:ring-2 focus:ring-teal/40">
        </div>

        {{-- 동의 --}}
        <div class="pt-2 space-y-2">
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="terms" value="1" required class="h-4 w-4 accent-[#1F4D3F]">
                <span class="text-sm text-navy/70"><b class="text-deepgreen">[필수]</b> 이용약관 및 개인정보 수집·이용에 동의합니다</span>
            </label>
            @error('terms') <p class="text-signal-red text-xs">{{ $message }}</p> @enderror
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="marketing" value="1" {{ old('marketing') ? 'checked' : '' }} class="h-4 w-4 accent-[#1F4D3F]">
                <span class="text-sm text-navy/70"><span class="text-navy/40">[선택]</span> 마케팅 정보 수신에 동의합니다</span>
            </label>
        </div>

        <button type="submit" class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">가입하기</button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        이미 회원이신가요?
        <a href="{{ route('login') }}" class="font-semibold text-teal hover:underline">로그인</a>
    </p>
</x-guest-layout>
