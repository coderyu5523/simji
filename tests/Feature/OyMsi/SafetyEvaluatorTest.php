<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\SafetyEvaluator;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->eval = new SafetyEvaluator();
});

/** SAF 6문항 전부 0 인 기본 응답에 덮어쓰기 */
function saf(array $overrides = []): array
{
    $base = ['SAF01' => 0, 'SAF02' => 0, 'SAF03' => 0, 'SAF04' => 0, 'SAF05' => 0, 'SAF06' => 0];
    return array_merge($base, $overrides);
}

test('SAF 전부 0 이면 S0 이다', function () {
    expect($this->eval->evaluate(saf(), $this->rules))->toBe('S0');
});

test('SAF03=1 이면 S1 이다 (T08)', function () {
    expect($this->eval->evaluate(saf(['SAF03' => 1]), $this->rules))->toBe('S1');
});

test('SAF01=2 이면 S2 이다', function () {
    expect($this->eval->evaluate(saf(['SAF01' => 2]), $this->rules))->toBe('S2');
});

test('SAF04=2 이면 S3 이다 (T09)', function () {
    expect($this->eval->evaluate(saf(['SAF04' => 2]), $this->rules))->toBe('S3');
});

test('SAF06=1 이면 S3 이다 (T10)', function () {
    expect($this->eval->evaluate(saf(['SAF06' => 1]), $this->rules))->toBe('S3');
});

test('003 기준 — SAF04=1 은 S2 가 아니라 S3 이다', function () {
    expect($this->eval->evaluate(saf(['SAF04' => 1]), $this->rules))->toBe('S3');
});

test('003 기준 — SAF01=3 · SAF02=3 · SAF05=2 도 S3 이다', function () {
    expect($this->eval->evaluate(saf(['SAF01' => 3]), $this->rules))->toBe('S3');
    expect($this->eval->evaluate(saf(['SAF02' => 3]), $this->rules))->toBe('S3');
    expect($this->eval->evaluate(saf(['SAF05' => 2]), $this->rules))->toBe('S3');
});

test('SAF 문항 무응답이면 최소 S1 이다 (T11)', function () {
    expect($this->eval->evaluate(saf(['SAF02' => null]), $this->rules))->toBe('S1');
});

test('무응답이 있어도 더 높은 등급이 나오면 그 등급을 쓴다', function () {
    expect($this->eval->evaluate(saf(['SAF02' => null, 'SAF04' => 3]), $this->rules))->toBe('S3');
});

test('높은 등급이 낮은 등급보다 우선한다', function () {
    // SAF03=3(S3) 와 SAF01=1(S1) 이 동시에 있으면 S3
    expect($this->eval->evaluate(saf(['SAF03' => 3, 'SAF01' => 1]), $this->rules))->toBe('S3');
});

test('safety_missing_min_level 규칙이 없으면 무응답 처리 시 예외를 던진다', function () {
    $rules = $this->rules;
    unset($rules['safety_missing_min_level']);

    $this->eval->evaluate(saf(['SAF02' => null]), $rules);
})->throws(InvalidArgumentException::class);
