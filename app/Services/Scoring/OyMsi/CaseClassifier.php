<?php
namespace App\Services\Scoring\OyMsi;

class CaseClassifier
{
    /** @return array{code:string, red_count:int, yellow_count:int} */
    public function general(array $factorScores, array $rules): array
    {
        $red = 0; $yellow = 0;
        foreach ($factorScores as $factor => $score) {
            if (!($rules['factors'][$factor]['included_in_overall'] ?? false)) continue;
            if ($score['score_status'] === 'UNSCORABLE') continue;
            if ($score['band'] === 'RED') $red++;
            if ($score['band'] === 'YELLOW') $yellow++;
        }

        $counts = ['red_count' => $red, 'yellow_count' => $yellow];
        $code = null;
        foreach ($rules['case_codes']['general'] as $entry) {
            if ($entry['when'] === null) { $code = $entry['code']; break; }
            [$field, $op, $value] = $entry['when'];
            $actual = $counts[$field];
            $hit = $op === '>=' ? $actual >= $value : $actual === $value;
            if ($hit) { $code = $entry['code']; break; }
        }

        if ($code === null) {
            throw new \InvalidArgumentException(
                'scoring_rules.case_codes.general 에 일치하는 항목이 없습니다 — 마지막 항목의 when 이 null(포괄 규칙)인지 확인하십시오.'
            );
        }

        return ['code' => $code] + $counts;
    }

    public function final(
        string $generalCode,
        string $safetyLevel,
        string $environmentLevel,
        array $rules
    ): string {
        $highest = max($this->rank($safetyLevel), $this->rank($environmentLevel));
        if ($highest === 0) return $generalCode;

        if (!array_key_exists($highest, $rules['case_codes']['escalation'])) {
            throw new \InvalidArgumentException(
                "scoring_rules.case_codes.escalation 에 등급 {$highest} 매핑이 없습니다 — 안전·환경 경보가 사례코드로 반영되지 않습니다."
            );
        }

        return $rules['case_codes']['escalation'][$highest];
    }

    /** 'S2' → 2, 'E0' → 0 */
    private function rank(string $level): int
    {
        return (int) substr($level, 1);
    }
}
