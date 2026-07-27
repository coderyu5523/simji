<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\CaseClassifier;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->classifier = new CaseClassifier();
});

/**
 * 9개 일반요인 밴드를 지정해 factorScores 형태로 만든다.
 * @param array<string,string> $bands 예: ['DEP'=>'RED']  나머지는 GREEN
 */
function factorsWithBands(array $bands): array
{
    $all = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT', 'SAF'];
    $out = [];
    foreach ($all as $f) {
        $band = $bands[$f] ?? 'GREEN';
        $raw = match ($band) { 'RED' => 12.0, 'YELLOW' => 8.0, default => 2.0 };
        $out[$f] = [
            'raw' => $raw, 'answered_count' => 6,
            'risk_index' => round($raw / 18 * 100, 1),
            'band' => $band, 'score_status' => 'COMPLETE',
        ];
    }
    return $out;
}

test('빨강 0 노랑 0 이면 G0 이다', function () {
    expect($this->classifier->general(factorsWithBands([]), $this->rules)['code'])->toBe('G0');
});

test('노랑 1개면 Y1, 3개면 Y2 이다 (T15)', function () {
    expect($this->classifier->general(factorsWithBands(['DEP' => 'YELLOW']), $this->rules)['code'])->toBe('Y1');
    expect($this->classifier->general(
        factorsWithBands(['DEP' => 'YELLOW', 'ANX' => 'YELLOW', 'IMP' => 'YELLOW']), $this->rules
    )['code'])->toBe('Y2');
});

test('빨강 1개면 R1, 2개면 R2 이다 (T13·T14)', function () {
    expect($this->classifier->general(factorsWithBands(['DEP' => 'RED']), $this->rules)['code'])->toBe('R1');
    expect($this->classifier->general(
        factorsWithBands(['DEP' => 'RED', 'ANX' => 'RED']), $this->rules
    )['code'])->toBe('R2');
});

test('SAF 밴드는 일반 사례코드 계산에서 제외한다', function () {
    $r = $this->classifier->general(factorsWithBands(['SAF' => 'RED']), $this->rules);
    expect($r['code'])->toBe('G0');
    expect($r['red_count'])->toBe(0);
});

test('S/E 가 0 이면 일반코드를 그대로 쓴다', function () {
    expect($this->classifier->final('R1', 'S0', 'E0', $this->rules))->toBe('R1');
});

test('max(S,E) 만큼 C 코드로 격상한다', function () {
    expect($this->classifier->final('G0', 'S1', 'E0', $this->rules))->toBe('C1');
    expect($this->classifier->final('G0', 'S0', 'E2', $this->rules))->toBe('C2');
    expect($this->classifier->final('R2', 'S2', 'E3', $this->rules))->toBe('C3');
});

test('FAM05=3 시나리오는 E3 → C3 이다 (T12)', function () {
    expect($this->classifier->final('G0', 'S0', 'E3', $this->rules))->toBe('C3');
});

test('SAF03=1 시나리오는 S1 → C1 이다 (T08)', function () {
    expect($this->classifier->final('G0', 'S1', 'E0', $this->rules))->toBe('C1');
});

test('UNSCORABLE 요인은 red/yellow 카운트에서 제외한다', function () {
    $factors = factorsWithBands(['DEP' => 'RED']);
    $factors['DEP'] = [
        'raw' => null, 'answered_count' => 2, 'risk_index' => null,
        'band' => null, 'score_status' => 'UNSCORABLE',
    ];

    $r = $this->classifier->general($factors, $this->rules);
    expect($r['red_count'])->toBe(0);
    expect($r['yellow_count'])->toBe(0);
    expect($r['code'])->toBe('G0');
});

test('case_codes.general 에 포괄 규칙(when=>null)이 없으면 예외를 던진다', function () {
    $rules = $this->rules;
    array_pop($rules['case_codes']['general']); // G0 catch-all 제거

    expect(fn () => $this->classifier->general(factorsWithBands([]), $rules))
        ->toThrow(InvalidArgumentException::class);
});

test('case_codes.escalation 에 해당 등급 매핑이 없으면 예외를 던진다', function () {
    $rules = $this->rules;
    unset($rules['case_codes']['escalation'][1]);

    expect(fn () => $this->classifier->final('G0', 'S1', 'E0', $rules))
        ->toThrow(InvalidArgumentException::class);
});
