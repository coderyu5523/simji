<?php
namespace App\Services\Scoring\OyMsi;

use InvalidArgumentException;

class SolutionRecommender
{
    private const LIMIT = 3;
    private const SAFETY_SOLUTION = 'SOL_SAF_PLAN';

    /**
     * 007 §10.2 — 안전 우선 고정 → 상위 요인 순 → dedupe_group 중복 제거 → 3개 이하
     * @param  list<array{factor:string,...}>  $topFactors
     * @return list<string>
     */
    public function recommend(
        array $topFactors,
        string $safetyLevel,
        string $environmentLevel,
        array $rules
    ): array {
        $catalog = $rules['solutions'];
        $byFactor = [];
        foreach ($catalog as $code => $sol) $byFactor[$sol['factor']] = $code;

        $candidates = [];
        if ($this->needsSafetyFirst($safetyLevel, $environmentLevel)) {
            $candidates[] = self::SAFETY_SOLUTION;
        }
        foreach ($topFactors as $row) {
            if (isset($byFactor[$row['factor']])) $candidates[] = $byFactor[$row['factor']];
        }

        $picked = [];
        $usedGroups = [];
        foreach ($candidates as $code) {
            if (count($picked) >= self::LIMIT) break;
            if (in_array($code, $picked, true)) continue;
            $group = $catalog[$code]['dedupe_group'];
            if (isset($usedGroups[$group])) continue;
            $usedGroups[$group] = true;
            $picked[] = $code;
        }

        return $picked;
    }

    /**
     * 규칙에 일치하는 사례코드가 없으면 예외를 던진다 — 재검 시점을 조용히
     * 기본값(90일)으로 낮추는 것은 촌각을 다투는 사례를 놓치는 실패다.
     *
     * @return array{days:int, reason:string}
     */
    public function recheckDays(string $finalCaseCode, array $rules): array
    {
        foreach ($rules['recheck'] as $entry) {
            if (in_array($finalCaseCode, $entry['when_case_in'], true)) {
                return ['days' => $entry['days'], 'reason' => $entry['reason']];
            }
        }

        throw new InvalidArgumentException(
            "scoring_rules.recheck 에 사례코드 {$finalCaseCode} 에 일치하는 항목이 없습니다 — 재검 시점을 정할 수 없습니다."
        );
    }

    private function needsSafetyFirst(string $safetyLevel, string $environmentLevel): bool
    {
        return max((int) substr($safetyLevel, 1), (int) substr($environmentLevel, 1)) >= 2;
    }
}
