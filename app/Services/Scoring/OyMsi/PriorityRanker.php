<?php
namespace App\Services\Scoring\OyMsi;

class PriorityRanker
{
    /**
     * 007 §9.5 — severity_weight + risk_index + alert_bonus + tie_break
     *
     * @param  list<string>  $alertFactors  HIGH/CRITICAL 경보가 걸린 요인 코드
     * @return list<array{factor:string, band:string, risk_index:float, score:float, rank:int}>
     */
    public function rank(array $factorScores, array $rules, array $alertFactors = []): array
    {
        $weights = $rules['priority']['severity_weight'];
        $bonus = $rules['priority']['alert_bonus'];
        $limit = $rules['priority']['limit'];
        $alerts = array_flip($alertFactors);

        $rows = [];
        foreach ($factorScores as $factor => $score) {
            if (!($rules['factors'][$factor]['included_in_overall'] ?? false)) continue;
            if ($score['score_status'] === 'UNSCORABLE') continue;

            $rows[] = [
                'factor' => $factor,
                'band' => $score['band'],
                'risk_index' => $score['risk_index'],
                'score' => $weights[$score['band']]
                    + $score['risk_index']
                    + (isset($alerts[$factor]) ? $bonus : 0)
                    + ($rules['factors'][$factor]['tie_break'] / 100),
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        $top = array_slice($rows, 0, $limit);
        foreach ($top as $i => &$row) $row['rank'] = $i + 1;

        return $top;
    }
}
