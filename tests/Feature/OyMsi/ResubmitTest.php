<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OyMsi\ConsentGate;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

/** 제출까지 끝낸 개인 응시를 만든다. */
function submittedAttempt(): TestAttempt
{
    $attempt = TestAttempt::create([
        'test_id' => test()->test->id, 'user_id' => test()->user->id,
        'status' => 'in_progress', 'started_at' => now(),
        'age_at_test' => 16, 'nickname' => '민수',
        'assessment_version' => test()->test->assessment_version,
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth', test()->user->id);

    $answers = [];
    foreach (test()->test->items as $item) $answers[$item->id] = 1;

    test()->actingAs(test()->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), ['answers' => $answers])
        ->assertRedirect(route('result.show', $attempt->id));

    return $attempt->fresh();
}

test('이미 제출한 검사에 다시 제출하면 결과로 보내고 재채점하지 않는다', function () {
    $attempt = submittedAttempt();

    $before = TestResult::where('attempt_id', $attempt->id)->firstOrFail();
    $submittedAt = $attempt->submitted_at;

    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 3;   // 다른 값으로 재제출

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), ['answers' => $answers])
        ->assertRedirect(route('result.show', $attempt->id));

    $after = TestResult::where('attempt_id', $attempt->id)->firstOrFail();
    expect(TestResult::where('attempt_id', $attempt->id)->count())->toBe(1);
    expect($after->overall_signal)->toBe($before->overall_signal);
    expect($after->updated_at->eq($before->updated_at))->toBeTrue();   // 재채점 안 함
    expect($attempt->fresh()->submitted_at->eq($submittedAt))->toBeTrue();
});

// 서버는 이중 제출을 결과로 흘려보내지만(위 테스트), 애초에 요청이 두 번 가지 않게 한다.
test('문항 화면에 이중 제출 방지가 붙어 있다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
        'age_at_test' => 16, 'nickname' => '민수',
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth', $this->user->id);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertOk()
        ->assertSee('data-submit-once', escape: false);
});

// 레벨 2 는 모달로 검사를 멈추지 않고 하단 고정 배너로 알린다(2026-07-29 방침).
test('문항 화면에 안전 배너와 모달이 둘 다 준비돼 있다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
        'age_at_test' => 16, 'nickname' => '민수',
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth', $this->user->id);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertOk()
        ->assertSee('id="safety-banner"', escape: false)
        ->assertSee('id="safety-modal"', escape: false);
});

test('이미 제출한 검사의 문항 화면을 열면 결과로 보낸다', function () {
    $attempt = submittedAttempt();

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertRedirect(route('result.show', $attempt->id));
});

test('이미 제출한 검사에 검사 시작을 눌러도 결과로 보낸다', function () {
    $attempt = submittedAttempt();
    $this->withSession(['oymsi_attempt:OY_MSI' => $attempt->id]);

    $this->actingAs($this->user)
        ->post(route('assessment.start', 'OY_MSI'))
        ->assertRedirect(route('result.show', $attempt->id));

    expect($attempt->fresh()->status)->toBe('submitted');
});

test('링크 경로도 재제출하면 결과로 보내고 재채점하지 않는다', function () {
    $voucher = Voucher::create([
        'user_id' => $this->user->id, 'test_id' => $this->test->id,
        'source' => 'issued_free', 'status' => 'active', 'issued_at' => now(),
        'access_token' => 'tok-resubmit',
    ]);

    $this->post(route('link.age.submit', 'tok-resubmit'),
        birthdateFields(now()->subYears(16)->subDays(1)->format('Y-m-d'))
    )->assertRedirect(route('link.landing', 'tok-resubmit'));

    $this->post(route('link.start', 'tok-resubmit'), [
        'nickname' => '민수', 'gender' => 'male', 'agree' => '1',
    ])->assertRedirect();

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->latest('id')->firstOrFail();

    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 1;
    $this->post(route('link.submit', ['tok-resubmit', $attempt->id]), ['answers' => $answers])
        ->assertRedirect(route('result.show', $attempt->id));

    // 재제출
    $this->post(route('link.submit', ['tok-resubmit', $attempt->id]), ['answers' => $answers])
        ->assertRedirect(route('result.show', $attempt->id));

    expect(TestResult::where('attempt_id', $attempt->id)->count())->toBe(1);
});
