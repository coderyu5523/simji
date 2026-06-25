<?php
namespace App\Services;
use App\Models\TestAttempt;
use App\Models\TestResult;

class ScoringService
{
    private array $order = ['green' => 0, 'yellow' => 1, 'red' => 2];

    public function score(TestAttempt $attempt): TestResult
    {
        $attempt->loadMissing('test.items', 'test.scoringRule', 'answers');
        $rules = $attempt->test->scoringRule->rules;
        $itemsById = $attempt->test->items->keyBy('id');

        $areaScores = [];
        foreach ($attempt->answers as $ans) {
            if (!isset($itemsById[$ans->test_item_id])) continue;
            $item = $itemsById[$ans->test_item_id];
            $val = $item->reverse ? (6 - $ans->value) : $ans->value;
            $areaScores[$item->area] = ($areaScores[$item->area] ?? 0) + $val;
        }

        $areaSignals = [];
        foreach ($areaScores as $area => $sum) {
            $th = $rules['areas'][$area] ?? ['yellow' => PHP_INT_MAX, 'red' => PHP_INT_MAX];
            $areaSignals[$area] = $sum >= $th['red'] ? 'red' : ($sum >= $th['yellow'] ? 'yellow' : 'green');
        }

        $overall = 'green';
        foreach ($areaSignals as $sig) {
            if ($this->order[$sig] > $this->order[$overall]) $overall = $sig;
        }

        $levelText = ['green' => '양호한 단계', 'yellow' => '관심과 조기지원이 필요한 단계', 'red' => '적극적 지원이 필요한 단계'];

        return TestResult::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                'area_scores' => $areaScores,
                'area_signals' => $areaSignals,
                'overall_signal' => $overall,
                'overall_level' => $levelText[$overall],
                'interpretation' => $rules['interpretation'][$overall] ?? '',
                'recommendations' => $rules['recommendations'][$overall] ?? [],
            ]
        );
    }
}
