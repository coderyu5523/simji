@props(['signal'])
@php $map = ['green'=>['bg-signal-green','초록'],'yellow'=>['bg-signal-yellow','노랑'],'red'=>['bg-signal-red','빨강']]; [$bg,$txt] = $map[$signal] ?? $map['green']; @endphp
<span class="inline-flex items-center gap-1 text-xs font-semibold text-white {{ $bg }} rounded-full px-3 py-1">{{ $txt }}</span>
