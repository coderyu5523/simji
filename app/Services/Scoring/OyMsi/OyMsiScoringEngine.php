<?php
namespace App\Services\Scoring\OyMsi;

use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Services\Scoring\ScoringEngine;

class OyMsiScoringEngine implements ScoringEngine
{
    public function __construct(
        private ItemScorer $itemScorer = new ItemScorer(),
        private FactorScorer $factorScorer = new FactorScorer(),
        private SafetyEvaluator $safety = new SafetyEvaluator(),
        private EnvironmentEvaluator $environment = new EnvironmentEvaluator(),
        private CaseClassifier $classifier = new CaseClassifier(),
        private PriorityRanker $ranker = new PriorityRanker(),
        private StrengthExtractor $strengths = new StrengthExtractor(),
        private SolutionRecommender $solutions = new SolutionRecommender(),
    ) {}

    public function score(TestAttempt $attempt): TestResult
    {
        $attempt->loadMissing('test.items', 'test.scoringRule', 'answers');
        $test = $attempt->test;
        $rules = $test->scoringRule->rules;

        // 1. item_code 기준 원점수 맵 (역채점 전 raw — SafetyEvaluator·EnvironmentEvaluator·
        //    StrengthExtractor 는 반드시 이 raw 를 받아야 한다. FUT04~06 은 긍정 문항으로
        //    역채점되므로, scored 를 넘기면 강점 판정과 안전/환경 조건 매칭이 뒤집힌다.)
        $itemsById = $test->items->keyBy('id');
        $raw = [];
        foreach ($test->items as $item) $raw[$item->item_code] = null; // 미응답 기본값
        foreach ($attempt->answers as $ans) {
            $item = $itemsById[$ans->test_item_id] ?? null;
            if (!$item) continue;
            $raw[$item->item_code] = $ans->value === null ? null : (int) $ans->value;
        }

        // 2. 문항 점수 (역채점) — FactorScorer 에게만 넘기는 scored 값
        $reverseCodes = $test->items->where('reverse', true)->pluck('item_code')->all();
        $scored = $this->itemScorer->score($raw, $reverseCodes);

        // 3. 요인 점수 — scored 사용
        $codesByFactor = $test->items->groupBy('area')
            ->map(fn ($group) => $group->pluck('item_code')->values()->all())->all();
        $factors = $this->factorScorer->scoreAll($scored, $codesByFactor, $rules);
        $overall = $this->factorScorer->overall($factors, $rules);

        // 4. 안전·환경 — raw 사용 (역채점 전 원점수)
        $safetyLevel = $this->safety->evaluate($raw, $rules);
        $environmentLevel = $this->environment->evaluate($raw, $rules);

        // 5. 사례코드
        $general = $this->classifier->general($factors, $rules);
        $finalCode = $this->classifier->final($general['code'], $safetyLevel, $environmentLevel, $rules);

        // 6. 우선순위·강점·솔루션·재검 — 강점은 raw 사용 (역채점 전 원점수)
        $alertFactors = $this->alertFactors($safetyLevel, $environmentLevel);
        $priority = $this->ranker->rank($factors, $rules, $alertFactors);
        $strengthCodes = $this->strengths->extract($raw, $rules);
        $solutionCodes = $this->solutions->recommend($priority, $safetyLevel, $environmentLevel, $rules);
        $recheck = $this->solutions->recheckDays($finalCode, $rules);

        // 7. 요인에 rank 병합
        foreach ($priority as $row) $factors[$row['factor']]['rank'] = $row['rank'];
        foreach ($factors as $code => &$f) {
            $f['max'] = 18;
            $f['rank'] = $f['rank'] ?? null;
        }
        unset($f);

        $scoreStatus = $this->overallStatus($factors, $rules);

        return TestResult::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                // 기존 컬럼 (결과 화면 호환)
                'area_scores' => array_map(fn ($f) => $f['raw'], $factors),
                'area_signals' => array_map(
                    fn ($f) => $f['band'] === null ? null : strtolower($f['band']),
                    $factors
                ),
                'overall_signal' => strtolower($overall['band']),
                'overall_level' => $this->overallLevelText($overall['band']),
                'interpretation' => '',   // 문안은 ReportComposer 가 템플릿에서 조립한다
                'recommendations' => $solutionCodes,

                // 신규 컬럼
                'general_case_code' => $general['code'],
                'final_case_code' => $finalCode,
                'safety_level' => $safetyLevel,
                'environment_level' => $environmentLevel,
                'score_status' => $scoreStatus,
                'engine_result' => [
                    'versions' => [
                        'assessment' => $attempt->assessment_version ?: $test->assessment_version,
                        'scoring' => $attempt->scoring_version ?: $test->scoringRule->version,
                    ],
                    'score_status' => $scoreStatus,
                    'overall' => $overall,
                    'profile' => [
                        'general_case_code' => $general['code'],
                        'final_case_code' => $finalCode,
                        'red_count' => $general['red_count'],
                        'yellow_count' => $general['yellow_count'],
                    ],
                    'safety' => [
                        'suicide_level' => $safetyLevel,
                        'environment_level' => $environmentLevel,
                    ],
                    'factors' => $factors,
                    'priority' => $priority,
                    'strengths' => $strengthCodes,
                    'solutions' => $solutionCodes,
                    'recheck' => $recheck,
                ],
            ]
        );
    }

    /** 경보가 걸린 요인 — 우선순위 alert_bonus 대상 */
    private function alertFactors(string $safetyLevel, string $environmentLevel): array
    {
        $out = [];
        if ((int) substr($safetyLevel, 1) >= 2) $out[] = 'SAF';
        if ((int) substr($environmentLevel, 1) >= 2) { $out[] = 'TRM'; $out[] = 'FAM'; $out[] = 'RSK'; }
        return $out;
    }

    /**
     * overall() 은 UNSCORABLE 요인의 raw=null 을 0 으로 취급하면서도 분모(18점)는
     * 그대로 포함한다 — 결측을 저위험으로 오인시키는 함정. 여기서 그 결측을
     * COMPLETE 로 감추지 않고 INCOMPLETE 로 명시해 신호를 살린다.
     */
    private function overallStatus(array $factors, array $rules): string
    {
        $statuses = [];
        foreach ($factors as $code => $f) {
            if (!($rules['factors'][$code]['included_in_overall'] ?? false)) continue;
            $statuses[] = $f['score_status'];
        }
        if (in_array('UNSCORABLE', $statuses, true)) return 'INCOMPLETE';
        if (in_array('PARTIAL', $statuses, true)) return 'PARTIAL';
        return 'COMPLETE';
    }

    private function overallLevelText(string $band): string
    {
        return match ($band) {
            'RED' => '적극적 지원이 필요한 단계',
            'YELLOW' => '관심과 조기지원이 필요한 단계',
            default => '양호한 단계',
        };
    }
}
