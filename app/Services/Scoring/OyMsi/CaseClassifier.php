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
            if ($score['band'] === 'RED') $red++;
            if ($score['band'] === 'YELLOW') $yellow++;
        }

        $counts = ['red_count' => $red, 'yellow_count' => $yellow];
        $code = 'G0';
        foreach ($rules['case_codes']['general'] as $entry) {
            if ($entry['when'] === null) { $code = $entry['code']; break; }
            [$field, $op, $value] = $entry['when'];
            $actual = $counts[$field];
            $hit = $op === '>=' ? $actual >= $value : $actual === $value;
            if ($hit) { $code = $entry['code']; break; }
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

        return $rules['case_codes']['escalation'][$highest] ?? $generalCode;
    }

    /** 'S2' → 2, 'E0' → 0 */
    private function rank(string $level): int
    {
        return (int) substr($level, 1);
    }
}
