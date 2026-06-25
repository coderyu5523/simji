<x-layouts.app :title="'심리검사 · simji 심지'">
  <div class="max-w-6xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold text-deepgreen">당신에게 맞는 마음검사를 선택하세요</h1>
    <h2 class="mt-10 font-semibold">연령방으로 찾기</h2>
    <div class="grid md:grid-cols-3 gap-6 mt-4">
      @foreach($rooms as $room)
        <a href="{{ route('catalog.room', $room['code']) }}" class="rounded-2xl bg-white shadow-sm p-6 hover:shadow-md">
          <img src="{{ asset('images/rooms/'.$room['code'].'.png') }}" class="h-32 w-full object-cover rounded-xl mb-4 bg-cream" alt="{{ $room['name'] }}">
          <h3 class="font-bold text-deepgreen">{{ $room['name'] }}</h3>
          <p class="text-sm text-navy/70 mt-1">{{ $room['desc'] }}</p>
        </a>
      @endforeach
    </div>
    <h2 class="mt-12 font-semibold">고민으로 찾기</h2>
    <div class="flex flex-wrap gap-2 mt-4">
      @foreach(collect($rooms)->flatMap(fn($r) => $r['tags'])->unique() as $tag)
        <span class="rounded-full bg-mint/50 text-deepgreen px-4 py-1 text-sm">{{ $tag }}</span>
      @endforeach
    </div>
  </div>
</x-layouts.app>
