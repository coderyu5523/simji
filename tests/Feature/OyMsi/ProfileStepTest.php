<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->user = User::factory()->create();
});

/** 연령 게이트 + 동의를 통과한 상태를 만든다 */
function passGateAndConsent($testCase, User $user, int $age = 16): TestAttempt
{
    $testCase->actingAs($user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => now()->subYears($age)->subDay()->format('Y-m-d')]);
    $testCase->actingAs($user)->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1']);

    return TestAttempt::latest('id')->firstOrFail();
}

test('기본정보를 저장하면 attempt 에 닉네임·성별·나이가 남는다', function () {
    $attempt = passGateAndConsent($this, $this->user, 16);

    $this->actingAs($this->user)
        ->post(route('oymsi.profile.submit', 'OY_MSI'), ['nickname' => '민수', 'gender' => 'male'])
        ->assertRedirect(route('assessment.start', 'OY_MSI'));

    $attempt->refresh();
    expect($attempt->nickname)->toBe('민수');
    expect($attempt->gender)->toBe('male');
    expect($attempt->age_at_test)->toBe(16);
});

test('닉네임은 필수, 성별은 응답하지 않음을 고를 수 있다', function () {
    passGateAndConsent($this, $this->user);

    $this->actingAs($this->user)
        ->post(route('oymsi.profile.submit', 'OY_MSI'), ['gender' => 'no_answer'])
        ->assertSessionHasErrors('nickname');

    $this->actingAs($this->user)
        ->post(route('oymsi.profile.submit', 'OY_MSI'), ['nickname' => '별명', 'gender' => 'no_answer'])
        ->assertRedirect();

    expect(TestAttempt::latest('id')->first()->gender)->toBe('no_answer');
});

test('기본정보 없이 start 를 호출하면 기본정보 화면으로 보낸다', function () {
    passGateAndConsent($this, $this->user);

    $this->actingAs($this->user)
        ->post(route('assessment.start', 'OY_MSI'))
        ->assertRedirect(route('oymsi.profile.form', 'OY_MSI'));
});

test('학년·학교명은 받지 않는다 (학교 밖 청소년 대상)', function () {
    passGateAndConsent($this, $this->user);

    $html = $this->actingAs($this->user)->get(route('oymsi.profile.form', 'OY_MSI'))->getContent();
    expect($html)->not->toContain('name="grade"');
    expect($html)->not->toContain('name="school"');
    expect($html)->not->toContain('학교명');
});

// ── 우회 시도 (실제 HTTP) ──────────────────────────────────────────────
// start() 만 막아서는 안 된다 — take()/submit() 을 직접 호출해도 기본정보 없이는 응시할 수 없어야 한다.

test('기본정보 없이 take 를 직접 호출하면 기본정보 화면으로 보낸다 (start 우회 차단)', function () {
    $attempt = passGateAndConsent($this, $this->user, 16);
    expect($attempt->nickname)->toBeNull();

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertRedirect(route('oymsi.profile.form', 'OY_MSI'));
});

test('기본정보 없이 submit 을 직접 호출하면 차단되고 채점되지 않는다 (start·take 우회 차단)', function () {
    $attempt = passGateAndConsent($this, $this->user, 16);
    $this->test->load('items');
    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 0;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), ['answers' => $answers])
        ->assertForbidden();

    expect($attempt->fresh()->status)->not->toBe('submitted');
});

test('링크 경로는 담당자 명부 이름과 청소년 닉네임을 분리 저장한다', function () {
    $voucher = Voucher::create([
        'user_id' => $this->user->id, 'test_id' => $this->test->id,
        'source' => 'link', 'status' => 'active', 'issued_at' => now(),
        'access_token' => 'tokprofile', 'recipient_name' => '김OO(명부)',
    ]);

    $this->post(route('link.age.submit', 'tokprofile'),
                ['birthdate' => now()->subYears(16)->subDay()->format('Y-m-d')]);
    $this->post(route('link.start', 'tokprofile'), ['nickname' => '별명이', 'gender' => 'female', 'agree' => '1'])
         ->assertRedirect();

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->firstOrFail();
    expect($attempt->nickname)->toBe('별명이');
    expect($attempt->age_at_test)->toBe(16);
    expect($voucher->fresh()->recipient_name)->toBe('김OO(명부)'); // 담당자 입력값 보존
});
