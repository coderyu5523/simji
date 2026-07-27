<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\SolutionRecommender;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->rec = new SolutionRecommender();
});

function top(array $factors): array
{
    $out = [];
    foreach ($factors as $i => $f) {
        $out[] = ['factor' => $f, 'band' => 'RED', 'risk_index' => 70.0, 'score' => 270.0, 'rank' => $i + 1];
    }
    return $out;
}

test('상위 요인에 대응하는 솔루션을 순서대로 준다', function () {
    expect($this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S0', 'E0', $this->rules))
        ->toBe(['SOL_DEP_ACTIVATION', 'SOL_ANX_BREATHING', 'SOL_ISO_CONNECT']);
});

test('S2 이상이면 안전 솔루션이 첫 번째로 고정된다', function () {
    $sols = $this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S2', 'E0', $this->rules);
    expect($sols[0])->toBe('SOL_SAF_PLAN');
    expect($sols)->toHaveCount(3);
});

test('E2 이상이어도 안전 솔루션이 앞에 붙는다', function () {
    expect($this->rec->recommend(top(['DEP']), 'S0', 'E3', $this->rules)[0])->toBe('SOL_SAF_PLAN');
});

test('dedupe_group 이 같으면 하나만 남긴다', function () {
    // DEP(생활회복) 와 LIF(생활회복) 는 같은 그룹
    $sols = $this->rec->recommend(top(['DEP', 'LIF', 'ANX']), 'S0', 'E0', $this->rules);
    expect($sols)->toBe(['SOL_DEP_ACTIVATION', 'SOL_ANX_BREATHING']);
});

test('최대 3개를 넘지 않는다', function () {
    expect($this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S3', 'E3', $this->rules))
        ->toHaveCount(3);
});

test('dedupe 는 3개 cap 적용 전에 이루어진다 (cap-before-dedup 회귀 방지)', function () {
    // 후보 4개: SOL_SAF_PLAN, SOL_DEP_ACTIVATION, SOL_LIF_7DAY(DEP 와 같은 '생활회복' 그룹), SOL_ANX_BREATHING
    // dedupe 를 cap 전에 하면 3개 모두 서로 다른 그룹이라 3개가 남는다.
    // cap 을 먼저 적용하면(회귀) 앞 3개([SAF, DEP, LIF])만 자르고 남은 걸 dedupe 해서 2개만 남는다.
    $sols = $this->rec->recommend(top(['DEP', 'LIF', 'ANX']), 'S2', 'E0', $this->rules);
    expect($sols)->toBe(['SOL_SAF_PLAN', 'SOL_DEP_ACTIVATION', 'SOL_ANX_BREATHING']);
});

test('S1 만으로는 안전 솔루션이 고정되지 않는다', function () {
    $sols = $this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S1', 'E0', $this->rules);
    expect($sols)->not->toContain('SOL_SAF_PLAN');
    expect($sols[0])->toBe('SOL_DEP_ACTIVATION');
});

test('E1 만으로는 안전 솔루션이 고정되지 않는다', function () {
    $sols = $this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S0', 'E1', $this->rules);
    expect($sols)->not->toContain('SOL_SAF_PLAN');
    expect($sols[0])->toBe('SOL_DEP_ACTIVATION');
});

test('재검 시점은 사례코드로 정한다', function () {
    expect($this->rec->recheckDays('C3', $this->rules)['days'])->toBe(14);
    expect($this->rec->recheckDays('R1', $this->rules)['days'])->toBe(14);
    expect($this->rec->recheckDays('Y1', $this->rules)['days'])->toBe(28);
    expect($this->rec->recheckDays('G0', $this->rules)['days'])->toBe(90);
});

test('사례코드가 recheck 규칙 어디에도 없으면 예외를 던진다', function () {
    expect(fn () => $this->rec->recheckDays('ZZ_UNKNOWN', $this->rules))
        ->toThrow(InvalidArgumentException::class);
});
