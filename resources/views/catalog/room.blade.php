<x-layouts.app :title="$room['name'].' · 심리검사'">
  <div class="bg-teal text-cream">
    <div class="max-w-6xl mx-auto px-4 py-12">
      <h1 class="text-2xl font-bold">{{ $room['name'] }} 방</h1>
      <p class="mt-2 text-cream/80">{{ $room['desc'] }}</p>
    </div>
  </div>
  <div class="max-w-6xl mx-auto px-4 py-10">
    @if($tests->isEmpty())
      <p class="text-navy/60">준비 중인 검사입니다. 곧 만나보실 수 있어요.</p>
    @else
      <div class="grid md:grid-cols-3 gap-6">
        @foreach($tests as $test) <x-test-card :test="$test"/> @endforeach
      </div>
    @endif
  </div>
</x-layouts.app>
