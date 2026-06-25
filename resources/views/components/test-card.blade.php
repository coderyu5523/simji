@props(['test'])
<a href="{{ route('catalog.show', $test->code) }}" class="block rounded-2xl bg-white shadow-sm p-5 hover:shadow-md transition">
  <img src="{{ asset($test->thumbnail ?: 'images/tests/placeholder.png') }}" alt="{{ $test->title_easy }}" class="h-28 w-full object-cover rounded-xl mb-3 bg-cream">
  <h3 class="font-semibold text-deepgreen">{{ $test->title_easy }}</h3>
  <p class="text-xs text-navy/60 mt-1">{{ $test->target }} · 약 {{ $test->duration_min }}분 · {{ $test->item_count }}문항</p>
</a>
