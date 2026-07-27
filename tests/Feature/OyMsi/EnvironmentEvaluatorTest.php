<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\EnvironmentEvaluator;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->eval = new EnvironmentEvaluator();
});

function envAnswers(array $overrides = []): array
{
    $base = ['TRM06' => 0, 'FAM05' => 0, 'RSK04' => 0, 'RSK05' => 0, 'RSK06' => 0];
    return array_merge($base, $overrides);
}

test('경보 문항 전부 0 이면 E0 이다', function () {
    expect($this->eval->evaluate(envAnswers(), $this->rules))->toBe('E0');
});

test('FAM05=3 이면 E3 이다 (T12)', function () {
    expect($this->eval->evaluate(envAnswers(['FAM05' => 3]), $this->rules))->toBe('E3');
});

test('TRM06=3 · RSK06=3 도 E3 이다', function () {
    expect($this->eval->evaluate(envAnswers(['TRM06' => 3]), $this->rules))->toBe('E3');
    expect($this->eval->evaluate(envAnswers(['RSK06' => 3]), $this->rules))->toBe('E3');
});

test('RSK04>=2 · RSK05>=2 는 E2 이다', function () {
    expect($this->eval->evaluate(envAnswers(['RSK04' => 2]), $this->rules))->toBe('E2');
    expect($this->eval->evaluate(envAnswers(['RSK05' => 3]), $this->rules))->toBe('E2');
});

test('TRM06=1 은 E1 이다', function () {
    expect($this->eval->evaluate(envAnswers(['TRM06' => 1]), $this->rules))->toBe('E1');
});

test('RSK04=1 만으로는 E0 이다 (E1 조건에 RSK04 없음)', function () {
    expect($this->eval->evaluate(envAnswers(['RSK04' => 1]), $this->rules))->toBe('E0');
});

test('높은 등급이 우선한다', function () {
    expect($this->eval->evaluate(envAnswers(['TRM06' => 1, 'FAM05' => 3]), $this->rules))->toBe('E3');
});
