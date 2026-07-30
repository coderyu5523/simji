<?php
use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->staff = User::factory()->create();
});

/** 발급자 소유의 제출 응시 한 건. SAF 응답을 실제로 넣는다(부재 단언을 공허하지 않게). */
function issuedAttempt(Test $test, User $issuer, string $name, array $answers = ['DEP01' => 2, 'SAF06' => 3]): Voucher
{
    $voucher = Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id,
        'source' => 'issued_free', 'status' => 'used',
        'issued_at' => now(), 'assigned_at' => now(),
        'access_token' => 'tok-'.uniqid(), 'recipient_name' => $name,
    ]);

    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.uniqid(),
        'test_id' => $test->id, 'voucher_id' => $voucher->id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 17, 'gender' => 'male', 'nickname' => '별명',
    ]);

    $itemsByCode = $test->items->keyBy('item_code');
    foreach ($answers as $code => $value) {
        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'test_item_id' => $itemsByCode[$code]->id, 'value' => $value,
        ]);
    }

    TestResult::create([
        'attempt_id' => $attempt->id,
        'area_scores' => ['DEP' => 12.0], 'area_signals' => ['DEP' => 'red'],
        'recommendations' => [], 'overall_level' => 'high', 'overall_signal' => 'red',
        'interpretation' => '', 'safety_level' => 'S3', 'environment_level' => 'E0',
        'score_status' => 'COMPLETE', 'engine_result' => ['factors' => ['SAF' => ['raw' => 6.0, 'band' => 'RED']]],
    ]);

    $voucher->update(['used_attempt_id' => $attempt->id, 'used_at' => now()]);

    return $voucher;
}

function rosterCsv($response): string
{
    return $response->streamedContent();
}

test('비로그인은 기관용 추출을 받을 수 없다', function () {
    $this->get(route('my.exports.institution', $this->test))
        ->assertRedirect(route('login'));
});

test('담당자는 자기 발급분을 받는다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');

    $body = rosterCsv($this->actingAs($this->staff)
        ->get(route('my.exports.institution', $this->test))->assertOk());

    expect($body)->toStartWith("\xEF\xBB\xBF");
    expect($body)->toContain('홍길동');
});

test('남의 발급분은 들어가지 않는다', function () {
    $other = User::factory()->create();
    issuedAttempt($this->test, $this->staff, '내응시자');
    issuedAttempt($this->test, $other, '남의응시자');

    $body = rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    expect($body)->toContain('내응시자')->not->toContain('남의응시자');
});

test('기관용에는 SAF 문항 열이 없다', function () {
    // SAF06 = 3 응답이 실제로 존재하는 상태에서 확인한다.
    issuedAttempt($this->test, $this->staff, '홍길동', ['SAF06' => 3]);

    $body = rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    expect($body)->not->toContain('SAF06')->not->toContain('SAF01');
    expect($body)->toContain('DEP01');   // 다른 문항은 남아 있다
});

test('기관용에도 안전등급은 즉시로 표기된다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');

    $body = rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    expect($body)->toContain('안전확인')->toContain('즉시');
});

test('명부에 내려받기 버튼이 있다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertSee(route('my.exports.institution', $this->test), escape: false);
});

test('추출이 감사 로그에 남는다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');
    \Illuminate\Support\Facades\Log::spy();

    rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) => str_contains($message, '응답 추출')
            && ($context['profile'] ?? null) === 'institution'
            && ($context['actor_id'] ?? null) === $this->staff->id);
});
