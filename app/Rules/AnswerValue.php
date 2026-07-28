<?php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;

class AnswerValue implements ValidationRule
{
    public const PREFER_NOT = 'PREFER_NOT';

    /** @param Collection<int, \App\Models\TestItem> $itemsById */
    public function __construct(private Collection $itemsById) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $itemId = (int) str_replace('answers.', '', $attribute);
        $item = $this->itemsById[$itemId] ?? null;
        if (!$item) { $fail('존재하지 않는 문항입니다.'); return; }

        if ($value === self::PREFER_NOT) return; // 응답거부 허용

        if (!is_numeric($value) || (int) $value != $value) {
            $fail('응답값이 올바르지 않습니다.'); return;
        }
        $value = (int) $value;

        [$min, $max] = $this->range($item);
        if ($value < $min || $value > $max) {
            $fail("응답값은 {$min}~{$max} 범위여야 합니다.");
        }
    }

    /**
     * options 가 있으면 0..count-1(4점 척도 등), 없으면 레거시 5점(1..5).
     * type 이 'likert4' 인데 options 가 비어있는 경우(설정 누락)나 알 수 없는
     * type 은 조용히 1~5 로 통과시키지 않고 예외를 던진다 — 데이터 오류를 숨기지 않기 위함.
     */
    private function range($item): array
    {
        $options = $item->options;
        if (is_array($options) && count($options) > 0) {
            return [0, count($options) - 1];
        }

        $type = $item->type;
        if ($type === 'likert5' || $type === null) {
            return [1, 5];
        }

        throw new \RuntimeException(
            "문항 {$item->item_code}(id={$item->id})의 type '{$type}' 에 대한 응답 범위를 결정할 수 없습니다. ".
            "options 가 없거나 비어 있습니다."
        );
    }
}
