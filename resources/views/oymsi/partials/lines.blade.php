{{--
  결과 문안 한 건을 종류별로 렌더한다.
  $lines : TemplateLineParser::parse() 결과 — [['kind'=>'heading'|'paragraph'|'item'|'question','text'=>...], ...]

  원문 문안에는 소제목("멈춤 4단계")·점검 질문("잠이 부족한 날 화가 더 심해지는가?")·
  실천항목·서술 문단이 한 필드에 섞여 있다. 전부 불릿으로 찍으면 소제목이 실천항목처럼
  보이므로 여기서 종류별로 나눠 그린다. 원문 텍스트는 손대지 않는다.
--}}
@php
  $blocks = [];
  foreach ($lines as $line) {
      $isListItem = in_array($line['kind'], ['item', 'question'], true);
      $lastIndex = count($blocks) - 1;
      if ($isListItem && $lastIndex >= 0 && $blocks[$lastIndex]['kind'] === 'list') {
          $blocks[$lastIndex]['lines'][] = $line;
          continue;
      }
      $blocks[] = $isListItem
          ? ['kind' => 'list', 'lines' => [$line]]
          : ['kind' => $line['kind'], 'text' => $line['text']];
  }
@endphp

@foreach($blocks as $block)
  @if($block['kind'] === 'heading')
    <p class="mt-4 first:mt-0 text-sm font-bold text-deepgreen">{{ $block['text'] }}</p>
  @elseif($block['kind'] === 'paragraph')
    <p class="mt-2 first:mt-0 text-sm text-navy/80 leading-relaxed">{{ $block['text'] }}</p>
  @else
    <ul class="mt-2 first:mt-0 space-y-1 pl-5 list-disc text-sm text-navy/75">
      @foreach($block['lines'] as $line)
        <li @class(['marker:text-teal', 'text-navy/60 italic' => $line['kind'] === 'question'])>{{ $line['text'] }}</li>
      @endforeach
    </ul>
  @endif
@endforeach
