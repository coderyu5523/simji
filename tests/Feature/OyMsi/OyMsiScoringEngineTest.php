<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ScoringService;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
});

/**
 * item_code => raw 매핑으로 응시·응답을 만들고 채점한다.
 * $overrides 에 지정한 문항은 입력값을 "그대로" raw 로 저장한다(문항 표시 그대로 고른 원점수).
 * 지정하지 않은 문항은 $default 로 "채점 후" 값을 맞춘다 — FUT04~06 처럼 역채점(긍정 문항)
 * 대상은 raw 를 뒤집어 저장해야 실제 scored 값이 $default 가 된다. 그렇지 않으면 raw=0 을
 * 그대로 넣었을 때 역채점 후 scored=3(최고 위험)이 되어 "전부 0 → 건강한 기준선" 이라는
 * 의도와 반대로 뒤집힌다. null 을 주면 응답거부로 저장한다.
 */
function scoreWith(array $overrides, ?int $default = 0): App\Models\TestResult
{
    $test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'guest_token' => 'g', 'status' => 'in_progress',
        'started_at' => now(), 'assessment_version' => $test->assessment_version,
        'scoring_version' => $test->scoringRule->version,
    ]);

    foreach ($test->items as $item) {
        if (array_key_exists($item->item_code, $overrides)) {
            $raw = $overrides[$item->item_code];
        } elseif ($default === null) {
            $raw = null;
        } else {
            $raw = $item->reverse ? (3 - $default) : $default;
        }
        $attempt->answers()->create([
            'test_item_id' => $item->id,
            'value' => $raw,
            'missing_code' => $raw === null ? 'PREFER_NOT' : null,
        ]);
    }

    return app(ScoringService::class)->score($attempt);
}

test('전부 0 이면 G0 · S0 · E0 · GREEN 이다 (T01)', function () {
    $r = scoreWith([]);
    expect($r->general_case_code)->toBe('G0');
    expect($r->final_case_code)->toBe('G0');
    expect($r->safety_level)->toBe('S0');
    expect($r->environment_level)->toBe('E0');
    expect($r->score_status)->toBe('COMPLETE');
    expect($r->engine_result['overall']['band'])->toBe('GREEN');
});

test('역채점 문항이 0 이면 요인 점수를 올린다', function () {
    // FUT04~06 raw 0 → scored 3 씩 → FUT raw 9 → YELLOW
    $r = scoreWith(['FUT04' => 0, 'FUT05' => 0, 'FUT06' => 0]);
    expect($r->engine_result['factors']['FUT']['raw'])->toBe(9.0);
    expect($r->engine_result['factors']['FUT']['band'])->toBe('YELLOW');
});

test('DEP 6문항 전부 3 이면 RED · R1 이다 (T13)', function () {
    $r = scoreWith(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    expect($r->engine_result['factors']['DEP']['band'])->toBe('RED');
    expect($r->general_case_code)->toBe('R1');
    expect($r->final_case_code)->toBe('R1');
});

test('SAF04=2 면 S3 이고 최종 C3 로 격상된다 (T09)', function () {
    $r = scoreWith(['SAF04' => 2]);
    expect($r->safety_level)->toBe('S3');
    expect($r->general_case_code)->toBe('G0');
    expect($r->final_case_code)->toBe('C3'); // 일반코드가 G0 여도 안전이 우선
});

test('FAM05=3 이면 E3 → C3 이다 (T12)', function () {
    $r = scoreWith(['FAM05' => 3]);
    expect($r->environment_level)->toBe('E3');
    expect($r->final_case_code)->toBe('C3');
});

test('일반코드와 최종코드를 둘 다 저장한다 (기관 통계 왜곡 방지)', function () {
    // TRM06=1 → E1 → C1. 하지만 일반 프로파일은 G0 라는 정보가 남아야 한다.
    $r = scoreWith(['TRM06' => 1]);
    expect($r->environment_level)->toBe('E1');
    expect($r->final_case_code)->toBe('C1');
    expect($r->general_case_code)->toBe('G0');
});

test('SAF 는 전체 위험지수에 포함되지 않는다', function () {
    $allSafMax = ['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3, 'SAF04' => 3, 'SAF05' => 3, 'SAF06' => 3];
    expect(scoreWith($allSafMax)->engine_result['overall']['raw'])->toBe(0.0);
});

test('응답거부가 있으면 PARTIAL 이 되고 SAF 무응답은 최소 S1 이다 (T11)', function () {
    $r = scoreWith(['SAF02' => null]);
    expect($r->safety_level)->toBe('S1');
    expect($r->final_case_code)->toBe('C1');
});

test('기존 컬럼도 함께 채운다 (결과 화면 호환)', function () {
    $r = scoreWith(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    expect($r->area_scores['DEP'])->toBe(18.0);
    expect($r->area_signals['DEP'])->toBe('red');
    expect($r->overall_signal)->toBeIn(['green', 'yellow', 'red']);
    expect($r->overall_level)->not->toBeEmpty();
});

test('버전을 결과에 기록한다', function () {
    $r = scoreWith([]);
    expect($r->engine_result['versions']['assessment'])->toBe('1.0.1');
    expect($r->engine_result['versions']['scoring'])->toBe('1.0.0');
});

test('강점과 솔루션과 재검일이 들어 있다', function () {
    $r = scoreWith(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    expect($r->engine_result['strengths'])->not->toBeEmpty();
    expect($r->engine_result['solutions'])->toContain('SOL_DEP_ACTIVATION');
    expect($r->engine_result['recheck']['days'])->toBe(14);
});

test('강점은 원점수(raw) 기준이다 — 역채점 문항을 scored 로 잘못 넘기면 강점이 잘못 잡힌다', function () {
    // FUT04~06 raw 0 (부정 응답) → 만약 StrengthExtractor 가 scored(3) 를 받으면
    // TRY_NEW/SMALL_GOAL/RECOVERY_HOPE 가 (잘못) 강점으로 잡힌다.
    $r = scoreWith(['FUT04' => 0, 'FUT05' => 0, 'FUT06' => 0]);
    expect($r->engine_result['strengths'])
        ->not->toContain('TRY_NEW')
        ->not->toContain('SMALL_GOAL')
        ->not->toContain('RECOVERY_HOPE');
});

test('요인 하나가 UNSCORABLE 이면 전체 score_status 는 INCOMPLETE 다 — deflated risk index 를 숨기지 않는다', function () {
    // DEP 는 6문항 중 4문항 무응답 → 2문항만 응답 → UNSCORABLE (FactorScorer 규칙)
    $r = scoreWith(['DEP01' => null, 'DEP02' => null, 'DEP03' => null, 'DEP04' => null]);
    expect($r->engine_result['factors']['DEP']['score_status'])->toBe('UNSCORABLE');
    expect($r->score_status)->toBe('INCOMPLETE');
    expect($r->engine_result['score_status'])->toBe('INCOMPLETE');
});
