<x-layouts.app :title="'강의·코칭 · 검사 결과 이후의 변화'">
  {{-- HERO --}}
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-5xl mx-auto px-4 py-16">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-4">강의·코칭 프로그램</span>
      <h1 class="text-2xl md:text-4xl font-extrabold leading-snug">검사 결과 이후,<br>변화가 시작됩니다</h1>
      <p class="mt-4 text-cream/80 max-w-2xl">심지는 검사와 결과에서 끝나지 않습니다. "그래서 이제 무엇을 해야 하나요?"에 답하는 연령별 강의·코칭으로 이어집니다.</p>
      <div class="mt-6 flex flex-wrap gap-2">
        @foreach($types as $type)
          <span class="rounded-full bg-cream/10 text-cream/90 px-4 py-1.5 text-sm">{{ $type }}</span>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 연령별 프로그램 --}}
  <section class="bg-cream">
    <div class="max-w-5xl mx-auto px-4 py-16">
      <h2 class="text-2xl font-extrabold text-deepgreen text-center mb-10">연령별 강의·코칭 프로그램</h2>
      <div class="grid md:grid-cols-2 gap-5">
        @foreach($programs as $p)
          <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex items-center justify-between">
              <span class="rounded-full bg-mint/40 text-deepgreen text-xs font-semibold px-3 py-1">{{ $p['room'] }}</span>
              <span class="text-xs text-navy/50">{{ $p['type'] }}</span>
            </div>
            <h3 class="font-bold text-deepgreen text-lg mt-3">{{ $p['name'] }}</h3>
            <p class="text-sm text-navy/60 mt-1.5">{{ $p['desc'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 기관 CTA --}}
  <section class="bg-white">
    <div class="max-w-5xl mx-auto px-4 py-14">
      <div class="rounded-3xl bg-navy text-cream p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-xl md:text-2xl font-extrabold">기관 맞춤형 특강·코칭이 필요하신가요?</h3>
          <p class="mt-2 text-cream/70">학교·기업·복지관 단위 프로그램을 설계해 드립니다.</p>
        </div>
        <a href="{{ route('institution') }}" class="shrink-0 rounded-xl bg-mint text-deepgreen px-7 py-3.5 font-bold hover:brightness-105 transition">기관 도입 문의</a>
      </div>
    </div>
  </section>
</x-layouts.app>
