<footer class="bg-navy text-cream/70">
  <div class="max-w-6xl mx-auto px-4 py-12 grid md:grid-cols-4 gap-8">
    <div class="md:col-span-2">
      <p class="text-lg font-extrabold text-cream">simji <span class="text-mint">심지</span></p>
      <p class="mt-2 text-sm text-cream/60 max-w-sm">마음을 검사하고, 삶을 코칭하다. 대학생부터 실버 세대까지, 검사 → 결과 → 강의·코칭으로 잇는 마음건강 플랫폼.</p>
    </div>
    <div>
      <p class="text-sm font-bold text-cream mb-3">바로가기</p>
      <ul class="space-y-2 text-sm">
        <li><a href="{{ route('catalog.index') }}" class="hover:text-mint transition">심리검사</a></li>
        <li><a href="{{ route('coaching') }}" class="hover:text-mint transition">강의·코칭</a></li>
        <li><a href="{{ route('institution') }}" class="hover:text-mint transition">기관·단체</a></li>
        <li><a href="{{ route('report.sample') }}" class="hover:text-mint transition">리포트 샘플</a></li>
      </ul>
    </div>
    <div>
      <p class="text-sm font-bold text-cream mb-3">고객센터</p>
      <ul class="space-y-2 text-sm">
        <li><a href="{{ route('support') }}" class="hover:text-mint transition">고객센터</a></li>
        <li><a href="{{ route('about') }}" class="hover:text-mint transition">심지 소개</a></li>
        <li class="text-cream/40">simji.org</li>
      </ul>
    </div>
  </div>
  <div class="border-t border-cream/10">
    <div class="max-w-6xl mx-auto px-4 py-5 text-xs text-cream/40 flex flex-col sm:flex-row justify-between gap-2">
      <span>© {{ date('Y') }} simji 심지. All rights reserved.</span>
      <span>마음알지 브랜드 · 심리검사·코칭 플랫폼</span>
    </div>
  </div>
</footer>
