<?php
use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Export\ResponseExporter;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->exporter = new ResponseExporter();
});

/**
 * 응답이 실제로 들어 있는 제출 응시를 만든다.
 * $answers 는 item_code => value. 넘기지 않은 문항은 미응답으로 남는다.
 */
function exportAttempt(Test $test, array $answers, ?User $issuer = null, ?string $recipientName = null): TestAttempt
{
    $voucher = null;
    if ($issuer) {
        $voucher = Voucher::create([
            'user_id' => $issuer->id, 'test_id' => $test->id,
            'source' => 'issued_free', 'status' => 'used',
            'issued_at' => now(), 'assigned_at' => now(),
            'access_token' => 'tok-'.uniqid(),
            'recipient_name' => $recipientName,
        ]);
    }

    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.uniqid(),
        'test_id' => $test->id, 'voucher_id' => $voucher?->id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 16, 'gender' => 'male', 'nickname' => '민수',
        'assessment_version' => '1.0.1', 'scoring_version' => '1.0.0',
    ]);

    $itemsByCode = $test->items->keyBy('item_code');
    foreach ($answers as $code => $value) {
        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'test_item_id' => $itemsByCode[$code]->id,
            'value' => $value,
        ]);
    }

    TestResult::create([
        'attempt_id' => $attempt->id,
        'area_scores' => ['DEP' => 12.0], 'area_signals' => ['DEP' => 'red'],
        'recommendations' => [], 'overall_level' => 'high', 'overall_signal' => 'red',
        'interpretation' => '',
        'safety_level' => 'S3', 'environment_level' => 'E0',
        'general_case_code' => 'R1', 'final_case_code' => 'C3', 'score_status' => 'COMPLETE',
        'engine_result' => ['factors' => [
            'DEP' => ['raw' => 12.0, 'band' => 'RED'],
            'SAF' => ['raw' => 6.0, 'band' => 'RED'],
        ]],
    ]);

    if ($voucher) $voucher->update(['used_attempt_id' => $attempt->id, 'used_at' => now()]);

    return $attempt->fresh();
}

test('연구용 헤더에 SAF 문항이 있다', function () {
    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);

    expect($headers)->toContain('SAF06')->toContain('DEP01');
});

test('기관용 헤더에 SAF 문항이 없다', function () {
    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('SAF06')
        ->not->toContain('SAF01')
        ->toContain('DEP01');   // 다른 문항은 남아 있어야 제외가 과하지 않음을 보인다
});

test('기관용은 SAF 응답이 실제로 있어도 값이 나오지 않는다', function () {
    // 부재 단언이 공허하지 않으려면 SAF 응답이 실제로 존재해야 한다.
    $attempt = exportAttempt($this->test, ['DEP01' => 2, 'SAF06' => 3]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    expect($byColumn)->not->toHaveKey('SAF06');
    expect($byColumn['DEP01'])->toBe(2);
});

test('연구용은 SAF 응답 값을 그대로 내보낸다', function () {
    $attempt = exportAttempt($this->test, ['SAF06' => 3]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['SAF06'])->toBe(3);
});

test('미응답은 빈칸이고 0 이 아니다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 0]);   // DEP02 는 미응답

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['DEP01'])->toBe(0);      // 실제 0점 응답
    expect($byColumn['DEP02'])->toBeNull();   // 미응답 — 0 이면 안 된다
});

test('역채점 문항도 역채점 전 원점수로 나온다', function () {
    // FUT04 는 reverse=true 인 긍정 문항. 저장된 값 그대로여야 한다.
    $attempt = exportAttempt($this->test, ['FUT04' => 3]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($this->test->items->firstWhere('item_code', 'FUT04')->reverse)->toBeTrue();
    expect($byColumn['FUT04'])->toBe(3);   // 역채점된 0 이 아니다
});

test('연구용에 이름이 없다', function () {
    $issuer = User::factory()->create();
    $attempt = exportAttempt($this->test, ['DEP01' => 1], $issuer, recipientName: '홍길동');

    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);

    expect($row)->not->toContain('홍길동')->not->toContain('민수');
});

test('기관용에 응시자 이름이 있다', function () {
    $issuer = User::factory()->create();
    $attempt = exportAttempt($this->test, ['DEP01' => 1], $issuer, recipientName: '홍길동');

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['응시자'])->toBe('홍길동');
});

test('연구용 영역 점수는 SAF 를 포함한다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    // engine_result.factors 에서 읽는다 — area_scores 컬럼은 SAF 가 빠져 있어 불완전하다
    expect($byColumn['SAF_raw'])->toBe(6.0);
    expect($byColumn['DEP_raw'])->toBe(12.0);
});

test('기관용 영역 점수에는 SAF 가 없다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('SAF_raw')->toContain('DEP_raw');
});

test('기관용 안전등급은 즉시·당일로 표기된다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);   // safety_level = S3

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['안전확인'])->toBe('즉시');
});

test('safety_items 키가 없어도 included_in_overall=false 요인 문항은 계속 제외된다', function () {
    // fail-closed: safety_items 리터럴이 낡거나 통째로 사라져도, 종합 점수에서 빠지는
    // 요인(factors.SAF.included_in_overall === false)에 속한 문항이라는 것만으로도
    // 제외돼야 한다. 두 조건 중 하나만 살아있어도 SAF 는 계속 막힌다.
    $rule = $this->test->scoringRule;
    $rules = $rule->rules;
    unset($rules['safety_items']);
    $rule->update(['rules' => $rules]);

    $headers = $this->exporter->headers($this->test->fresh(), ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('SAF06')->not->toContain('SAF01');
});

test('factors.SAF 요인 정의가 사라져도 safety_items 목록이 문항을 계속 제외한다', function () {
    // 반대 방향의 독립성: 요인 정의 자체가 개정으로 사라지거나 included_in_overall 이
    // 없어져도, safety_items 리터럴 목록이 살아있으면 그것만으로 SAF 문항이 막혀야 한다.
    $rule = $this->test->scoringRule;
    $rules = $rule->rules;
    unset($rules['factors']['SAF']);
    $rule->update(['rules' => $rules]);

    $headers = $this->exporter->headers($this->test->fresh(), ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('SAF06')->not->toContain('SAF01')
        ->toContain('DEP01');   // 다른 문항은 과하게 제외되지 않는다
});

test('safety_items 를 지워도 영역 제외(included_in_overall)는 그대로 작동한다', function () {
    // 문항 제외(safety_items ∪ included_in_overall=false)와 영역 제외(included_in_overall)는
    // 서로 다른 키를 읽는 별개 메커니즘이다 — 한쪽을 지워도 다른 쪽 필터가
    // SAF_raw/SAF_band 를 계속 숨겨야 한다.
    $rule = $this->test->scoringRule;
    $rules = $rule->rules;
    unset($rules['safety_items']);
    $rule->update(['rules' => $rules]);

    $headers = $this->exporter->headers($this->test->fresh(), ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('SAF06')            // included_in_overall=false 로 여전히 제외
        ->not->toContain('SAF_raw')                       // 영역도 여전히 숨는다
        ->not->toContain('SAF_band');
});

test('=cmd 처럼 수식으로 해석될 수 있는 응시자 이름은 앞에 작은따옴표가 붙는다', function () {
    // 링크 응시자가 자유 입력하는 nickname/recipient_name 은 살균되지 않은 채 그대로
    // 저장된다. 이 값이 = / + / - / @ 로 시작하면 엑셀이 수식으로 해석해 실행할 수 있다
    // (CSV 수식 인젝션). 앞에 '(작은따옴표)를 붙여 문자열로 강제해야 한다.
    $issuer = User::factory()->create();
    $attempt = exportAttempt($this->test, ['DEP01' => 1], $issuer, recipientName: '=cmd|calc');

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['응시자'])->toBe("'=cmd|calc");
});

test('숫자 값(문항 원점수·연령)은 수식 인젝션 방지 처리를 절대 타지 않는다', function () {
    // is_string() 으로 좁혀야 한다 — 문항 원점수·연령이 문자열로 바뀌면 분석이 깨진다.
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['DEP01'])->toBe(1)->toBeInt();
    expect($byColumn['age_at_test'])->toBe(16)->toBeInt();
});

test('연구용에 응답 거부 문항 목록(refused_items)이 세미콜론으로 이어져 나온다', function () {
    // "안 봄"(미응답)과 "보고 거부함"(missing_code='PREFER_NOT')은 결측 메커니즘이
    // 다르다. 003 문서는 안전문항 응답 거부 자체를 노랑 판정 조건에 넣으므로 표준화
    // 분석에서 구분이 필요하다.
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);
    $itemsByCode = $this->test->items->keyBy('item_code');
    $attempt->answers()->create([
        'test_item_id' => $itemsByCode['SAF03']->id,
        'value' => null, 'missing_code' => \App\Rules\AnswerValue::PREFER_NOT,
    ]);
    $attempt->answers()->create([
        'test_item_id' => $itemsByCode['SAF06']->id,
        'value' => null, 'missing_code' => \App\Rules\AnswerValue::PREFER_NOT,
    ]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt->fresh(), $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect(array_slice($headers, array_search('score_status', $headers), 2))
        ->toBe(['score_status', 'refused_items']); // 채점 결과 블록 끝, score_status 다음
    expect($byColumn['refused_items'])->toBe('SAF03;SAF06');
});

test('응답 거부가 없는 응시는 refused_items 가 빈칸이다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['refused_items'])->toBe('');
});

test('기관용에는 refused_items 컬럼이 없다', function () {
    // 어느 SAF 문항을 거부했는지가 드러나면 SAF 원점수 차단(수정 1)이 다시 새어 나간다.
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);
    $itemsByCode = $this->test->items->keyBy('item_code');
    $attempt->answers()->create([
        'test_item_id' => $itemsByCode['SAF03']->id,
        'value' => null, 'missing_code' => \App\Rules\AnswerValue::PREFER_NOT,
    ]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt->fresh(), $this->test, ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('refused_items');
    expect(count($headers))->toBe(count($row));
});

test('파일명에 검사 코드와 용도가 들어간다', function () {
    $name = $this->exporter->filename($this->test, ResponseExporter::PROFILE_RESEARCH);

    expect($name)->toContain('OY_MSI')->toEndWith('.csv');
});
