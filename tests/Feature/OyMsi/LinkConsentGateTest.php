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

test('consent_required 검사는 링크 start 자체가 막힌다 (링크용 동의 화면이 아직 없어 fail closed)', function () {
    $voucher = makeOyMsiVoucher($this->test, $this->issuer, 'tok-start-block');

    $this->post(route('link.start', 'tok-start-block'), ['recipient_name' => '홍길동'])
        ->assertForbidden();

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
    expect($voucher->fresh()->status)->toBe('active');
});

test('동의 없이 직접 만든 링크 attempt 로는 take 에 들어갈 수 없다', function () {
    $voucher = makeOyMsiVoucher($this->test, $this->issuer, 'tok-take-block');

    // link.start() 를 거치지 않고(=동의 없이) attempt 를 직접 심어 우회를 시도한다.
    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'guest-take',
        'test_id' => $this->test->id, 'voucher_id' => $voucher->id,
        'status' => 'in_progress', 'started_at' => now(),
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
        'status' => 'in_progress', 'started_at' => now(),
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

    $this->post(route('link.start', 'tok-sample-ok'), ['recipient_name' => '김철수'])
        ->assertRedirect();

    $attempt = TestAttempt::where('test_id', $sample->id)->latest('id')->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('in_progress');
});
