<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\PriorityRanker;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->ranker = new PriorityRanker();
});

/** @param array<string,float> $raws 요인별 원점수. 나머지는 0 */
function factorsWithRaw(array $raws): array
{
    $all = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT', 'SAF'];
    $out = [];
    foreach ($all as $f) {
        $raw = $raws[$f] ?? 0.0;
        $band = $raw >= 11 ? 'RED' : ($raw >= 6 ? 'YELLOW' : 'GREEN');
        $out[$f] = [
            'raw' => $raw, 'answered_count' => 6,
            'risk_index' => round($raw / 18 * 100, 1),
            'band' => $band, 'score_status' => 'COMPLETE',
        ];
    }
    return $out;
}

test('상위 3개만 돌려주고 rank 를 1부터 매긴다', function () {
    $top = $this->ranker->rank(factorsWithRaw(['DEP' => 14, 'LIF' => 12, 'FUT' => 8, 'ANX' => 7]), $this->rules);
    expect($top)->toHaveCount(3);
    expect(array_column($top, 'factor'))->toBe(['DEP', 'LIF', 'FUT']);
    expect(array_column($top, 'rank'))->toBe([1, 2, 3]);
});

test('SAF 는 순위에 들어가지 않는다', function () {
    $top = $this->ranker->rank(factorsWithRaw(['SAF' => 18, 'DEP' => 7]), $this->rules);
    expect(array_column($top, 'factor'))->not->toContain('SAF');
});

test('밴드가 위험지수보다 우선한다 (severity_weight)', function () {
    // ANX RED(11) vs DEP YELLOW(10) → RED 가 위
    $top = $this->ranker->rank(factorsWithRaw(['ANX' => 11, 'DEP' => 10]), $this->rules);
    expect($top[0]['factor'])->toBe('ANX');
});

test('점수가 같으면 tie_break 가 높은 요인이 앞선다', function () {
    // DEP(9) vs ANX(2) 동점 → DEP
    $top = $this->ranker->rank(factorsWithRaw(['DEP' => 8, 'ANX' => 8]), $this->rules);
    expect($top[0]['factor'])->toBe('DEP');
});

test('경보가 걸린 요인은 alert_bonus 로 최상단에 온다', function () {
    // FAM 은 GREEN(2) 이지만 경보가 있으면 RED 인 DEP 보다 위
    $top = $this->ranker->rank(factorsWithRaw(['DEP' => 14, 'FAM' => 2]), $this->rules, ['FAM']);
    expect($top[0]['factor'])->toBe('FAM');
});

test('UNSCORABLE 요인은 순위에서 제외한다', function () {
    $factors = factorsWithRaw(['DEP' => 14, 'LIF' => 12, 'FUT' => 8]);
    $factors['LIF'] = ['raw' => null, 'answered_count' => 3, 'risk_index' => null,
                       'band' => null, 'score_status' => 'UNSCORABLE'];
    $top = $this->ranker->rank($factors, $this->rules);
    expect(array_column($top, 'factor'))->not->toContain('LIF');
});
