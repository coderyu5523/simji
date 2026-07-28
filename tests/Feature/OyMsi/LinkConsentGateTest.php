<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

// 링크 응시(/t/{token})는 로그인 없이 열리는 두 번째 진입점이다. AssessmentController 에만
// ConsentGate 를 걸면 이 경로로 consent_required 검사를 통째로 우회할 수 있었다(Critical 1).

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->issuer = User::factory()->create();
});

function makeOyMsiVoucher(Test $test, User $issuer, string $token): Voucher
{
    return Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id,
        'source' => 'issued_free', 'status' => 'active', 'issued_at' => now(),
        'access_token' => $token,
    ]);
}

// Task 13 에서 링크 수신자용 연령·동의 화면이 생기면서 "consent_required 면 무조건 403" 이라는
// 임시 차단은 걷어냈다. 지키던 요구사항(동의 확인 없이는 attempt 도 검사권 소비도 없다)은 그대로다 —
// 이제는 연령 확인 없으면 연령 게이트로, 동의 체크 없으면 검증 오류로 막힌다.
test('consent_required 검사는 연령 확인 없이 링크 start 를 때리면 attempt 가 생기지 않는다 (fail closed)', function () {
    $voucher = makeOyMsiVoucher($this->test, $this->issuer, 'tok-start-block');

    $this->post(route('link.start', 'tok-start-block'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1'])
        ->assertRedirect(route('link.age.form', 'tok-start-block'));

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
    expect($voucher->fresh()->status)->toBe('active');
});

test('consent_required 검사는 나이를 확인해도 동의 체크 없이는 링크 start 가 막힌다', function () {
    $voucher = makeOyMsiVoucher($this->test, $this->issuer, 'tok-start-noagree');

    $this->withSession(['oymsi_age_token:tok-start-noagree' => 16])
        ->post(route('link.start', 'tok-start-noagree'), ['nickname' => '홍길동', 'gender' => 'male'])
        ->assertSessionHasErrors('agree');

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
    expect($voucher->fresh()->status)->toBe('active');
});

// 아래 두 건은 "SENSITIVE 동의가 없으면 막힌다"를 지키는 테스트다. Task 13 의 나이 가드가
// 동의 검사보다 앞에 서므로, age_at_test 를 채워 나이 사유로 403 이 나는 것을 배제해야
// 동의 사유로 막혔음을 실제로 검증할 수 있다. (안 채우면 동의 검사를 지워도 초록불이 된다.)
test('동의 없이 직접 만든 링크 attempt 로는 take 에 들어갈 수 없다', function () {
    $voucher = makeOyMsiVoucher($this->test, $this->issuer, 'tok-take-block');

    // link.start() 를 거치지 않고(=동의 없이) attempt 를 직접 심어 우회를 시도한다.
    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'guest-take',
        'test_id' => $this->test->id, 'voucher_id' => $voucher->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => 16,
    ]);

    $this->withSession(['guest_token' => 'guest-take'])
        ->get(route('link.take', ['tok-take-block', $attempt->id]))
        ->assertForbidden();
});

test('동의 없이 링크 submit 을 직접 호출해도 차단되고 검사권이 소비되지 않는다', function () {
    $voucher = makeOyMsiVoucher($this->test, $this->issuer, 'tok-submit-block');

    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'guest-submit',
        'test_id' => $this->test->id, 'voucher_id' => $voucher->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => 16,
    ]);

    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 0;

    $this->withSession(['guest_token' => 'guest-submit'])
        ->post(route('link.submit', ['tok-submit-block', $attempt->id]), ['answers' => $answers])
        ->assertForbidden();

    expect($attempt->fresh()->status)->not->toBe('submitted');
    expect($voucher->fresh()->status)->toBe('active');
});

test('consent_required 가 꺼진 검사는 링크 경로가 그대로 동작한다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $voucher = makeOyMsiVoucher($sample, $this->issuer, 'tok-sample-ok');

    $this->post(route('link.start', 'tok-sample-ok'), ['nickname' => '김철수', 'gender' => 'male'])
        ->assertRedirect();

    $attempt = TestAttempt::where('test_id', $sample->id)->latest('id')->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('in_progress');
});
