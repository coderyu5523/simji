<?php
use App\Models\Test;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
});

test('최상위 키가 모두 존재한다', function () {
    expect(array_keys($this->rules))->toContain(
        'factors', 'bands', 'overall_bands', 'safety', 'environment',
        'case_codes', 'priority', 'strengths', 'solutions', 'recheck'
    );
});

test('요인 10개 · SAF 만 총점 제외', function () {
    expect($this->rules['factors'])->toHaveCount(10);
    $excluded = collect($this->rules['factors'])
        ->reject(fn ($f) => $f['included_in_overall'])->keys()->all();
    expect($excluded)->toBe(['SAF']);
});

test('밴드 경계가 5/10 이다', function () {
    expect($this->rules['bands']['GREEN']['max'])->toBe(5);
    expect($this->rules['bands']['YELLOW']['min'])->toBe(6);
    expect($this->rules['bands']['YELLOW']['max'])->toBe(10);
    expect($this->rules['bands']['RED']['min'])->toBe(11);
});

test('S3 조건이 003 기준이다 (SAF04>=1, SAF01=3, SAF02=3, SAF05>=2 포함)', function () {
    $s3 = $this->rules['safety']['S3'];
    expect($s3)->toContain(['item' => 'SAF04', 'op' => '>=', 'value' => 1]);
    expect($s3)->toContain(['item' => 'SAF01', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF02', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF05', 'op' => '>=', 'value' => 2]);
    expect($s3)->toContain(['item' => 'SAF03', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF06', 'op' => '>=', 'value' => 1]);
});

test('S2 에는 007 잔여분만 남는다', function () {
    $s2Items = collect($this->rules['safety']['S2'])->pluck('item')->unique()->sort()->values()->all();
    expect($s2Items)->toBe(['SAF01', 'SAF02', 'SAF03']);
});

test('tie_break 우선순위가 DEP 9 … FUT 1 이다', function () {
    $tb = collect($this->rules['factors'])->map(fn ($f) => $f['tie_break']);
    expect($tb['DEP'])->toBe(9);
    expect($tb['TRM'])->toBe(8);
    expect($tb['FAM'])->toBe(7);
    expect($tb['RSK'])->toBe(6);
    expect($tb['IMP'])->toBe(5);
    expect($tb['ISO'])->toBe(4);
    expect($tb['LIF'])->toBe(3);
    expect($tb['ANX'])->toBe(2);
    expect($tb['FUT'])->toBe(1);
});

test('솔루션 10종에 dedupe_group 이 있다', function () {
    expect($this->rules['solutions'])->toHaveCount(10);
    foreach ($this->rules['solutions'] as $code => $sol) {
        expect($sol)->toHaveKeys(['factor', 'title', 'dedupe_group']);
    }
});
