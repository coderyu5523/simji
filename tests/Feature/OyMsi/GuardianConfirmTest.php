<?php
use App\Models\ConsentRecord;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OyMsi\ConsentGate;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->staff = User::factory()->create();
});

/**
 * 명부용 링크 검사권.
 * AgeGateTest 의 ageGateVoucher / LinkConsentGateTest 의 makeOyMsiVoucher 와
 * 이름이 겹치면 안 된다(Pest 전역 함수).
 */
function guardianConfirmVoucher(Test $test, User $issuer, string $token, bool $confirmed = false): Voucher
{
    return Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id,
        'source' => 'issued_free', 'status' => 'active',
        'issued_at' => now(), 'assigned_at' => now(),
        'access_token' => $token,
        'guardian_consent_confirmed_at' => $confirmed ? now() : null,
        'guardian_consent_confirmed_by' => $confirmed ? $issuer->id : null,
    ]);
}

// ── 확인 기록 ──────────────────────────────────────────────────────────

test('발급자가 미응시 링크에 확인하면 시각과 담당자가 함께 기록된다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-confirm');

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), ['confirm' => '1'])
        ->assertRedirect();

    $voucher->refresh();
    expect($voucher->guardian_consent_confirmed_at)->not->toBeNull();
    expect($voucher->guardian_consent_confirmed_by)->toBe($this->staff->id);
});

test('확인 체크 없이 제출하면 거부되고 컬럼이 비어 있다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-nocheck');

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), [])
        ->assertSessionHasErrors('confirm');

    $voucher->refresh();
    expect($voucher->guardian_consent_confirmed_at)->toBeNull();
    expect($voucher->guardian_consent_confirmed_by)->toBeNull();
});

test('이미 확인된 것을 다시 확인해도 최초 확인 시각이 바뀌지 않는다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-again');
    $voucher->update(['guardian_consent_confirmed_at' => now()->subDays(3)]);
    $first = $voucher->fresh()->guardian_consent_confirmed_at;

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), ['confirm' => '1'])
        ->assertRedirect();

    expect($voucher->fresh()->guardian_consent_confirmed_at->timestamp)->toBe($first->timestamp);
});

// ── 인가 ──────────────────────────────────────────────────────────────

test('남의 검사권은 확인할 수 없다', function () {
    $other = User::factory()->create();
    $voucher = guardianConfirmVoucher($this->test, $other, 'tok-other');

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), ['confirm' => '1'])
        ->assertForbidden();

    expect($voucher->fresh()->guardian_consent_confirmed_at)->toBeNull();
});

test('연령 확인이 필요 없는 검사의 검사권은 확인할 수 없다', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $plain = Test::where('code', 'KMSIA-SAMPLE')->firstOrFail();
    expect($plain->requiresAgeVerification())->toBeFalse();

    $voucher = guardianConfirmVoucher($plain, $this->staff, 'tok-plain');

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), ['confirm' => '1'])
        ->assertForbidden();

    expect($voucher->fresh()->guardian_consent_confirmed_at)->toBeNull();
});

test('링크로 발급되지 않은 보유 검사권은 확인할 수 없다', function () {
    $voucher = Voucher::create([
        'user_id' => $this->staff->id, 'test_id' => $this->test->id,
        'source' => 'purchase', 'status' => 'active', 'issued_at' => now(),
        'access_token' => null,
    ]);

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), ['confirm' => '1'])
        ->assertForbidden();

    expect($voucher->fresh()->guardian_consent_confirmed_at)->toBeNull();
});

// ── 응시 시작 후 잠금 ──────────────────────────────────────────────────

test('응시가 시작된 뒤에는 확인할 수 없다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-started');
    TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-started',
        'test_id' => $this->test->id, 'voucher_id' => $voucher->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => 13,
        'nickname' => '테스트',
    ]);

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), ['confirm' => '1'])
        ->assertForbidden();

    expect($voucher->fresh()->guardian_consent_confirmed_at)->toBeNull();
});

test('응시가 시작된 뒤에는 해제할 수 없다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-lock', confirmed: true);
    TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-lock',
        'test_id' => $this->test->id, 'voucher_id' => $voucher->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => 13,
        'nickname' => '테스트',
    ]);

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.release', $voucher->id))
        ->assertForbidden();

    expect($voucher->fresh()->guardian_consent_confirmed_at)->not->toBeNull();
});

// ── 해제 ──────────────────────────────────────────────────────────────

test('해제하면 시각과 담당자가 함께 비워진다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-release', confirmed: true);

    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.release', $voucher->id))
        ->assertRedirect();

    $voucher->refresh();
    expect($voucher->guardian_consent_confirmed_at)->toBeNull();
    expect($voucher->guardian_consent_confirmed_by)->toBeNull();
});

// ── 명부 화면 ──────────────────────────────────────────────────────────

test('미확인 링크에는 확인 폼이, 확인된 링크에는 배지와 해제가 뜬다', function () {
    guardianConfirmVoucher($this->test, $this->staff, 'tok-view-no');

    $res = $this->actingAs($this->staff)->get(route('my.index'));
    $res->assertOk();
    $res->assertSee('보호자 동의 확인');
    $res->assertSee('법정대리인에게 동의를 받았으며');
    $res->assertDontSee('보호자 동의 확인됨');

    guardianConfirmVoucher($this->test, $this->staff, 'tok-view-yes', confirmed: true);

    $res2 = $this->actingAs($this->staff)->get(route('my.index'));
    $res2->assertSee('보호자 동의 확인됨');
    $res2->assertSee('확인 해제');
});

test('확인 후 응시가 시작된 링크에는 배지만 남고 해제가 사라진다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-view-started', confirmed: true);
    TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-view',
        'test_id' => $this->test->id, 'voucher_id' => $voucher->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => 13,
        'nickname' => '테스트',
    ]);

    $res = $this->actingAs($this->staff)->get(route('my.index'));
    $res->assertOk();
    $res->assertSee('보호자 동의 확인됨');
    $res->assertDontSee('확인 해제');
    $res->assertDontSee('법정대리인에게 동의를 받았으며');
});

// assertDontSee 는 대상이 실제로 렌더될 수 있는 조건에서 해야 공허하지 않다.
// 그래서 같은 테스트 안에서 ① 없음을 단언하고 ② 확인된 링크를 추가해 그 문자열이
// 이 화면에서 실제로 렌더된다는 것을 증명한다. ②가 없으면 ①은 아무것도 보장하지 않는다.
test('확인 없이 응시가 시작된 링크에는 보호자 동의 표시가 뜨지 않는다', function () {
    $plain = guardianConfirmVoucher($this->test, $this->staff, 'tok-view-plain');
    TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-plain',
        'test_id' => $this->test->id, 'voucher_id' => $plain->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => 16,
        'nickname' => '테스트',
    ]);

    // ① 확인 없이 응시가 시작된 링크뿐 → 보호자 동의 관련 표시가 전혀 없다
    $res = $this->actingAs($this->staff)->get(route('my.index'));
    $res->assertOk();
    $res->assertDontSee('보호자 동의');
    $res->assertDontSee('법정대리인');

    // ② 확인된 링크를 하나 추가하면 같은 화면에서 그 문자열이 실제로 렌더된다
    //    (= ①의 assertDontSee 가 공허하지 않았음을 증명)
    guardianConfirmVoucher($this->test, $this->staff, 'tok-view-badge', confirmed: true);

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertSee('보호자 동의 확인됨');

    expect($plain->fresh()->guardian_consent_confirmed_at)->toBeNull();
});

// ── 진짜 성공 기준: 만 13세가 실제로 검사를 마친다 ───────────────────────

test('담당자가 확인한 링크로 만 13세가 응시를 완주하고 담당자 동의가 기록된다', function () {
    $voucher = guardianConfirmVoucher($this->test, $this->staff, 'tok-e2e');

    // ① 담당자가 명부에서 확인
    $this->actingAs($this->staff)
        ->post(route('my.voucher.guardian.confirm', $voucher->id), ['confirm' => '1'])
        ->assertRedirect();

    // ② 만 13세가 링크로 응시
    $birthdate = now()->subYears(13)->subDays(1)->format('Y-m-d');
    $this->post(route('link.age.submit', 'tok-e2e'), birthdateFields($birthdate))
        ->assertRedirect(route('link.landing', 'tok-e2e'));

    $this->post(route('link.start', 'tok-e2e'), ['nickname' => '민수', 'gender' => 'male', 'agree' => '1'])
        ->assertRedirect();

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->latest('id')->firstOrFail();
    expect($attempt->age_at_test)->toBe(13);

    $this->get(route('link.take', ['tok-e2e', $attempt->id]))->assertOk();

    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 0;
    $this->post(route('link.submit', ['tok-e2e', $attempt->id]), ['answers' => $answers])
        ->assertRedirect(route('result.show', $attempt->id));

    // ③ 담당자 동의가 그 담당자 이름으로 기록됐다
    expect($attempt->fresh()->status)->toBe('submitted');

    $guardian = ConsentRecord::where('attempt_id', $attempt->id)
        ->where('consent_type', ConsentGate::GUARDIAN_OFFLINE)->first();
    expect($guardian)->not->toBeNull();
    expect($guardian->actor)->toBe('staff');
    expect($guardian->actor_user_id)->toBe($this->staff->id);
    expect($guardian->meta['voucher_id'])->toBe($voucher->id);
});

// ── 회귀: 확인이 없으면 여전히 막힌다 ───────────────────────────────────

test('확인하지 않은 링크로는 만 13세가 여전히 차단된다', function () {
    guardianConfirmVoucher($this->test, $this->staff, 'tok-still-blocked');

    $birthdate = now()->subYears(13)->subDays(1)->format('Y-m-d');
    $this->post(route('link.age.submit', 'tok-still-blocked'), birthdateFields($birthdate))
        ->assertOk()
        ->assertSee('담당자');

    expect(session('oymsi_age_token:tok-still-blocked'))->toBeNull();
});

test('개인 경로의 만 13세 차단은 이번 변경으로 뚫리지 않는다', function () {
    $birthdate = now()->subYears(13)->subDays(1)->format('Y-m-d');

    $this->actingAs($this->staff)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields($birthdate))
        ->assertOk()
        ->assertSee('기관을 통해');

    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});
