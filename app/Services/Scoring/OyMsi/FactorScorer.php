<?php
namespace App\Services\Scoring\OyMsi;

class FactorScorer
{
    private const ITEMS_PER_FACTOR = 6;
    private const MAX_PER_FACTOR = 18;

    /**
     * @param  array<string, int|null>     $scoredByItemCode
     * @param  array<string, list<string>> $itemCodesByFactor
     */
    public function scoreAll(array $scoredByItemCode, array $itemCodesByFactor, array $rules): array
    {
        $out = [];
        foreach ($itemCodesByFactor as $factor => $codes) {
            $values = [];
            foreach ($codes as $code) {
                $v = $scoredByItemCode[$code] ?? null;
                if ($v !== null) $values[] = $v;
            }
            $count = count($values);

            if ($count === self::ITEMS_PER_FACTOR) {
                $raw = (float) array_sum($values);
                $status = 'COMPLETE';
            } elseif ($count === self::ITEMS_PER_FACTOR - 1) {
                // 007 §5.3 — 5문항 응답 시 6/5 환산
                $raw = round(array_sum($values) * self::ITEMS_PER_FACTOR / ($count), 1);
                $status = 'PARTIAL';
            } else {
                $raw = null;
                $status = 'UNSCORABLE';
            }

            $out[$factor] = [
                'raw' => $raw,
                'answered_count' => $count,
                'risk_index' => $raw === null ? null : round($raw / self::MAX_PER_FACTOR * 100, 1),
                'band' => $raw === null ? null : $this->pickBand($raw, $rules['bands']),
                'score_status' => $status,
            ];
        }
        return $out;
    }

    public function overall(array $factorScores, array $rules): array
    {
        $included = array_keys(array_filter(
            $rules['factors'],
            fn ($f) => $f['included_in_overall']
        ));

        $raw = 0.0;
        foreach ($included as $factor) {
            $raw += $factorScores[$factor]['raw'] ?? 0.0;
        }
        $max = count($included) * self::MAX_PER_FACTOR;
        $index = round($raw / $max * 100, 1);

        return [
            'raw' => round($raw, 1),
            'max' => $max,
            'risk_index' => $index,
            'band' => $this->pickBand($index, $rules['overall_bands']),
        ];
    }

    /** RED → YELLOW → GREEN 순으로 min 을 만족하는 첫 밴드 */
    private function pickBand(float $value, array $bands): string
    {
        foreach (['RED', 'YELLOW', 'GREEN'] as $name) {
            if ($value >= $bands[$name]['min']) return $name;
        }
        return 'GREEN';
    }
}
