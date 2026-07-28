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

    /** options 가 있으면 0..count-1, 없으면 레거시 5점(1..5) */
    private function range($item): array
    {
        $options = $item->options;
        if (is_array($options) && count($options) > 0) {
            return [0, count($options) - 1];
        }
        return [1, 5];
    }
}
