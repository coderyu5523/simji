<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\StrengthExtractor;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->extractor = new StrengthExtractor();
});

test('FUT04 원점수 2 이상이면 TRY_NEW 를 준다', function () {
    // 주의: 강점은 역채점 전 원점수(raw) 기준이다
    expect($this->extractor->extract(['FUT04' => 2], $this->rules))->toContain('TRY_NEW');
});

test('FUT05·FUT06 도 각각 SMALL_GOAL·RECOVERY_HOPE 를 준다', function () {
    $s = $this->extractor->extract(['FUT05' => 3, 'FUT06' => 2], $this->rules);
    expect($s)->toContain('SMALL_GOAL');
    expect($s)->toContain('RECOVERY_HOPE');
});

test('조건을 못 채워도 HONEST_RESPONSE 로 최소 1개는 나온다', function () {
    $s = $this->extractor->extract(['FUT04' => 0, 'FUT05' => 0, 'FUT06' => 0], $this->rules);
    expect($s)->toHaveCount(1);
    expect($s)->toBe(['HONEST_RESPONSE']);
});

test('조건을 채우면 HONEST_RESPONSE 는 붙지 않는다', function () {
    $s = $this->extractor->extract(['FUT04' => 3], $this->rules);
    expect($s)->toBe(['TRY_NEW']);
});
