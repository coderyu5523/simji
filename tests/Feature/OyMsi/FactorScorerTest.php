<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\FactorScorer;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->scorer = new FactorScorer();
});

/** DEP 6문항만 담은 최소 입력 */
function depScores(array $values): array
{
    $out = [];
    foreach ($values as $i => $v) $out[sprintf('DEP%02d', $i + 1)] = $v;
    return $out;
}
function depMap(): array
{
    return ['DEP' => ['DEP01', 'DEP02', 'DEP03', 'DEP04', 'DEP05', 'DEP06']];
}

test('전부 0이면 GREEN 이고 위험지수 0 이다 (T01)', function () {
    $r = $this->scorer->scoreAll(depScores([0, 0, 0, 0, 0, 0]), depMap(), $this->rules);
    expect($r['DEP']['raw'])->toBe(0.0);
    expect($r['DEP']['risk_index'])->toBe(0.0);
    expect($r['DEP']['band'])->toBe('GREEN');
    expect($r['DEP']['score_status'])->toBe('COMPLETE');
});

test('경계값 5 GREEN / 6 YELLOW / 10 YELLOW / 11 RED (T02~T05)', function () {
    $band = fn (array $v) => $this->scorer->scoreAll(depScores($v), depMap(), $this->rules)['DEP']['band'];
    expect($band([3, 2, 0, 0, 0, 0]))->toBe('GREEN');   // 5
    expect($band([3, 3, 0, 0, 0, 0]))->toBe('YELLOW');  // 6
    expect($band([3, 3, 3, 1, 0, 0]))->toBe('YELLOW');  // 10
    expect($band([3, 3, 3, 2, 0, 0]))->toBe('RED');     // 11
});

test('위험지수는 raw/18*100 을 소수 1자리로 반올림한다', function () {
    $r = $this->scorer->scoreAll(depScores([3, 3, 3, 3, 1, 0]), depMap(), $this->rules); // 13
    expect($r['DEP']['risk_index'])->toBe(72.2);
});

test('5문항만 응답하면 PARTIAL 로 6/5 환산한다 (T16)', function () {
    $r = $this->scorer->scoreAll(depScores([2, 2, 2, 2, 2, null]), depMap(), $this->rules); // 10 → 12.0
    expect($r['DEP']['score_status'])->toBe('PARTIAL');
    expect($r['DEP']['answered_count'])->toBe(5);
    expect($r['DEP']['raw'])->toBe(12.0);
    expect($r['DEP']['band'])->toBe('RED');
});

test('4문항 이하면 UNSCORABLE 이고 점수를 내지 않는다 (T17)', function () {
    $r = $this->scorer->scoreAll(depScores([2, 2, 2, 2, null, null]), depMap(), $this->rules);
    expect($r['DEP']['score_status'])->toBe('UNSCORABLE');
    expect($r['DEP']['raw'])->toBeNull();
    expect($r['DEP']['risk_index'])->toBeNull();
    expect($r['DEP']['band'])->toBeNull();
});

test('전체 지수는 SAF 를 빼고 9요인 162점 만점으로 계산한다', function () {
    $scored = [];
    $factors = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT'];
    foreach ($factors as $f) {
        foreach (range(1, 6) as $i) $scored[sprintf('%s%02d', $f, $i)] = 1; // 요인당 6점
    }
    foreach (range(1, 6) as $i) $scored[sprintf('SAF%02d', $i)] = 3; // SAF 는 총점 제외

    $map = [];
    foreach ([...$factors, 'SAF'] as $f) {
        $map[$f] = array_map(fn ($i) => sprintf('%s%02d', $f, $i), range(1, 6));
    }

    $overall = $this->scorer->overall(
        $this->scorer->scoreAll($scored, $map, $this->rules),
        $this->rules
    );

    expect($overall['raw'])->toBe(54.0);
    expect($overall['max'])->toBe(162);
    expect($overall['risk_index'])->toBe(33.3);
    expect($overall['band'])->toBe('YELLOW');
});
