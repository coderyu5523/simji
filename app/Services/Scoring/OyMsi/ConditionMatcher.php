<?php
namespace App\Services\Scoring\OyMsi;

use InvalidArgumentException;

class ConditionMatcher
{
    /**
     * @param  list<array{item:string, op:string, value:int}>  $conditions
     * @param  array<string, int|null>                          $rawByItemCode
     */
    public function anyMatches(array $conditions, array $rawByItemCode): bool
    {
        foreach ($conditions as $c) {
            if ($this->matches($c, $rawByItemCode)) return true;
        }
        return false;
    }

    /** @param array{item:string, op:string, value:int} $condition */
    public function matches(array $condition, array $rawByItemCode): bool
    {
        $raw = $rawByItemCode[$condition['item']] ?? null;
        if ($raw === null) return false; // 무응답은 조건 불성립 (별도 규칙으로 처리)

        return match ($condition['op']) {
            '='  => $raw === $condition['value'],
            '>=' => $raw >= $condition['value'],
            '<=' => $raw <= $condition['value'],
            default => throw new InvalidArgumentException("알 수 없는 연산자: {$condition['op']}"),
        };
    }
}
