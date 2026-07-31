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
    $this->user = User::factory()->create();
});

function birthdateForAge(int $age): string
{
    return now()->subYears($age)->subDays(1)->format('Y-m-d');
}

/** 링크용 검사권. LinkConsentGateTest 의 makeOyMsiVoucher 와 이름이 겹치면 안 된다(Pest 전역). */
function ageGateVoucher(Test $test, User $issuer, string $token, bool $guardianConfirmed = false): Voucher
{
    return Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id,
        'source' => 'issued_free', 'status' => 'active', 'issued_at' => now(),
        'access_token' => $token,
        'guardian_consent_confirmed_at' => $guardianConfirmed ? now() : null,
        'guardian_consent_confirmed_by' => $guardianConfirmed ? $issuer->id : null,
    ]);
}

// ── 브리프 차단 규칙 표 ────────────────────────────────────────────────

test('만 14~18세는 개인 경로로 통과한다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateForAge(16)))
        ->assertRedirect(route('assessment.consent', 'OY_MSI'));

    expect(session('oymsi_age:OY_MSI'))->toBe(16);
});

test('만 13세는 개인 경로에서 차단되고 기관 안내를 본다', function () {
    $res = $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateForAge(13)));

    $res->assertOk();
    $res->assertSee('기관을 통해');
    $res->assertSee('1388');
    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});

test('만 12세는 대상 연령이 아니라 차단된다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateForAge(12)))
        ->assertOk()
        ->assertSee('대상');

    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});

test('만 19세는 대상 연령이 아니라 차단된다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateForAge(19)))
        ->assertOk()
        ->assertSee('대상');

    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});

test('연령 확인 없이 동의 화면에 들어가면 연령 게이트로 보낸다', function () {
    $this->actingAs($this->user)
        ->get(route('assessment.consent', 'OY_MSI'))
        ->assertRedirect(route('oymsi.age.form', 'OY_MSI'));
});

test('링크 경로 · 만 13세 · 담당자 확인 있으면 통과하고 동의가 기록된다', function () {
    $voucher = ageGateVoucher($this->test, $this->user, 'tok13ok', guardianConfirmed: true);

    $this->post(route('link.age.submit', $voucher->access_token), birthdateFields(birthdateForAge(13)))
        ->assertRedirect(route('link.landing', $voucher->access_token));

    expect(session('oymsi_age_token:tok13ok'))->toBe(13);

    // (b) 통과시킨 이상 guardian_offline 동의가 실제로 기록되어야 한다 —
    // 기록이 없으면 ConsentGate 가 영원히 403 을 던지는 데드락이 된다.
    $this->post(route('link.start', 'tok13ok'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1'])
        ->assertRedirect();

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->latest('id')->firstOrFail();
    expect($attempt->age_at_test)->toBe(13);

    $guardian = ConsentRecord::where('attempt_id', $attempt->id)
        ->where('consent_type', ConsentGate::GUARDIAN_OFFLINE)->first();
    expect($guardian)->not->toBeNull();
    expect($guardian->granted)->toBeTrue();
    expect($guardian->actor)->toBe('staff');
    expect($guardian->actor_user_id)->toBe($this->user->id);

    // 기록됐으니 실제로 검사 화면까지 통과해야 한다(데드락 아님)
    $this->get(route('link.take', ['tok13ok', $attempt->id]))->assertOk();
});

test('링크 start 를 여러 번 제출해도 attempt 와 동의기록이 하나씩만 생긴다', function () {
    $voucher = ageGateVoucher($this->test, $this->user, 'tok-dup', guardianConfirmed: true);

    $this->post(route('link.age.submit', 'tok-dup'), birthdateFields(birthdateForAge(13)));
    $this->post(route('link.start', 'tok-dup'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1']);
    $this->post(route('link.start', 'tok-dup'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1']);
    $this->post(route('link.start', 'tok-dup'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1']);

    expect(TestAttempt::where('voucher_id', $voucher->id)->count())->toBe(1);

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->firstOrFail();
    expect(ConsentRecord::where('attempt_id', $attempt->id)
        ->where('consent_type', ConsentGate::SENSITIVE)->count())->toBe(1);
    expect(ConsentRecord::where('attempt_id', $attempt->id)
        ->where('consent_type', ConsentGate::GUARDIAN_OFFLINE)->count())->toBe(1);
    expect(ConsentRecord::count())->toBe(2);
});

test('링크 경로 · 만 13세 · 담당자 확인 없으면 차단된다', function () {
    $voucher = ageGateVoucher($this->test, $this->user, 'tok13no');

    $this->post(route('link.age.submit', $voucher->access_token), birthdateFields(birthdateForAge(13)))
        ->assertOk()
        ->assertSee('담당자');

    expect(session('oymsi_age_token:tok13no'))->toBeNull();
});

test('생년월일은 어디에도 저장되지 않는다 (만 나이만)', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields('2010-03-15'));

    expect(session()->all())->not->toHaveKey('birthdate');
    expect(session('oymsi_age:OY_MSI'))->toBeInt();
});

test('미래 날짜·잘못된 형식은 거부한다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(now()->addDay()->format('Y-m-d')))
        ->assertSessionHasErrors('birthdate');

    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'),
            ['birth_year' => '아무말', 'birth_month' => '3', 'birth_day' => '15'])
        ->assertSessionHasErrors('birthdate');

    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});

// 년·월·일을 따로 받으면서 새로 생긴 실패 경로. 칸마다 따로 검증하면 각 값은 멀쩡한데
// 조합이 존재하지 않는 날짜가 그대로 통과한다.
test('존재하지 않는 날짜 조합은 거부한다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'),
            ['birth_year' => '2011', 'birth_month' => '2', 'birth_day' => '30'])
        ->assertSessionHasErrors('birthdate');

    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});

test('빈 칸이 있으면 거부한다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'),
            ['birth_year' => '2010', 'birth_month' => '', 'birth_day' => '15'])
        ->assertSessionHasErrors('birthdate');

    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});

// 한 자리로 적어도(3월 → "3") 받아야 한다. 앞자리 0 을 강요하면 입력이 다시 불편해진다.
test('월·일을 한 자리로 적어도 통과한다', function () {
    $target = now()->subYears(16)->subDays(1);

    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), [
            'birth_year' => (string) $target->year,
            'birth_month' => (string) $target->month,
            'birth_day' => (string) $target->day,
        ])
        ->assertRedirect(route('assessment.consent', 'OY_MSI'));

    expect(session('oymsi_age:OY_MSI'))->toBe(16);
});

// ── (a) fail closed: 나이를 모르는 attempt 는 통과가 아니라 차단 ────────────

// Task 14 닉네임 가드가 age fail-closed 검사(ConsentGate:46-48) 뒤에 있다. nickname 을 안 채우면
// age 가드를 지워도 닉네임 가드가 대신 막아 이 테스트가 계속 초록불이 된다(final-review C1) —
// nickname 을 채워 age 가드 자체에 도달하게 한다.
test('(a) 나이를 모르는 attempt 는 동의가 있어도 take 에서 차단된다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => null,
        'nickname' => '테스트',
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth', $this->user->id);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertForbidden();
});

// submit() 의 닉네임 가드(AssessmentController:174, abort_if 403)가 age fail-closed 검사를 지워도
// 대신 403 을 던져 이 테스트를 가려버린다(final-review C1) — nickname 을 채워야 age 가드 자체가
// 살아있는지 검증된다.
test('(a) 나이를 모르는 attempt 는 submit 도 차단된다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => null,
        'nickname' => '테스트',
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth', $this->user->id);

    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 0;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), ['answers' => $answers])
        ->assertForbidden();

    expect($attempt->fresh()->status)->not->toBe('submitted');
});

// LinkController::take() 에는 현재 닉네임 가드가 없어 이 케이스 자체는 그것으로 가려지지
// 않지만, 형제 fixture(위 두 건)와의 일관성 + 향후 링크 경로에도 같은 가드가 붙을 가능성을 위해
// 동일하게 nickname 을 채워둔다(final-review C1 스캔 결과).
test('(a) 링크 경로도 나이를 모르는 attempt 는 차단된다', function () {
    $voucher = ageGateVoucher($this->test, $this->user, 'tok-noage');
    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-noage',
        'test_id' => $this->test->id, 'voucher_id' => $voucher->id,
        'status' => 'in_progress', 'started_at' => now(), 'age_at_test' => null,
        'nickname' => '테스트',
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth');

    $this->withSession(['guest_token' => 'g-noage'])
        ->get(route('link.take', ['tok-noage', $attempt->id]))
        ->assertForbidden();
});

// ── 인계 ①: 나이 없이 굳은 created attempt 를 재사용해도 나이가 갱신된다 ────

test('(①) 나이 null 로 굳은 created attempt 도 나이 확정 후 다시 동의하면 갱신되어 통과한다', function () {
    // 연령 게이트 이전에 만들어진(=age_at_test 가 null 로 굳은) 동의 완료 attempt
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'created', 'assessment_version' => $this->test->assessment_version,
        'age_at_test' => null,
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth', $this->user->id);

    $this->actingAs($this->user)->withSession(['oymsi_attempt:OY_MSI' => $attempt->id]);

    // (a) 때문에 지금은 막혀 있다
    $this->get(route('assessment.take', ['OY_MSI', $attempt->id]))->assertForbidden();

    // 나이를 확정하고 동의를 다시 확인한다 → 재사용 분기에서도 나이가 채워져야 한다
    $this->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateForAge(16)))
        ->assertRedirect(route('assessment.consent', 'OY_MSI'));
    $this->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])
        ->assertRedirect(route('oymsi.profile.form', 'OY_MSI'));

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(1); // 재사용
    expect($attempt->fresh()->age_at_test)->toBe(16);

    // Task 14: 응시 전 기본정보(닉네임·성별) 단계를 거쳐야 한다.
    $this->post(route('oymsi.profile.submit', 'OY_MSI'), ['nickname' => '민수', 'gender' => 'male'])
        ->assertRedirect(route('assessment.take', ['OY_MSI', $attempt->id]));

    $this->get(route('assessment.take', ['OY_MSI', $attempt->id]))->assertOk();
});

// ── 우회 시도 (실제 HTTP) ──────────────────────────────────────────────

test('나이 없이 agree 를 직접 POST 하면 연령 게이트로 돌려보내고 attempt 가 생기지 않는다', function () {
    $this->actingAs($this->user)
        ->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])
        ->assertRedirect(route('oymsi.age.form', 'OY_MSI'));

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
    expect(ConsentRecord::count())->toBe(0);
});

test('만 13세가 차단당한 뒤 agree 를 직접 POST 해도 세션에 나이가 없어 막힌다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateForAge(13)))
        ->assertOk();

    $this->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])
        ->assertRedirect(route('oymsi.age.form', 'OY_MSI'));

    $this->post(route('assessment.start', 'OY_MSI'))->assertForbidden();

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
});

test('링크: 나이 확인 없이 landing 에 들어가면 연령 게이트로 보낸다', function () {
    ageGateVoucher($this->test, $this->user, 'tok-landing');

    $this->get(route('link.landing', 'tok-landing'))
        ->assertRedirect(route('link.age.form', 'tok-landing'));
});

test('링크: 나이 확인 없이 start 를 직접 POST 하면 attempt 가 생기지 않는다 (fail closed)', function () {
    $voucher = ageGateVoucher($this->test, $this->user, 'tok-nostart');

    $this->post(route('link.start', 'tok-nostart'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1'])
        ->assertRedirect(route('link.age.form', 'tok-nostart'));

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
    expect($voucher->fresh()->status)->toBe('active');
});

test('링크: 세션에 만 13세를 심어도 담당자 확인이 없으면 start 에서 다시 막힌다', function () {
    $voucher = ageGateVoucher($this->test, $this->user, 'tok-forge');

    // 게이트를 우회해 세션만 조작한 상황(또는 확인이 사후 철회된 상황)
    $this->withSession(['oymsi_age_token:tok-forge' => 13])
        ->post(route('link.start', 'tok-forge'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1'])
        ->assertForbidden();

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
    expect($voucher->fresh()->status)->toBe('active');
});

test('링크: 동의 체크 없이 start 하면 거부되고 attempt 가 생기지 않는다', function () {
    ageGateVoucher($this->test, $this->user, 'tok-noagree');

    $this->withSession(['oymsi_age_token:tok-noagree' => 16])
        ->post(route('link.start', 'tok-noagree'), ['nickname' => '홍길동', 'gender' => 'male'])
        ->assertSessionHasErrors('agree');

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
});

// ── 정상 전 구간 (연령 → 동의 → 검사) ──────────────────────────────────

test('개인 경로 만 16세는 연령 → 동의 → 시작 → 검사까지 이어진다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateForAge(16)))
        ->assertRedirect(route('assessment.consent', 'OY_MSI'));

    $this->get(route('assessment.consent', 'OY_MSI'))->assertOk();
    $this->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])->assertRedirect();

    $attempt = TestAttempt::where('test_id', $this->test->id)->latest('id')->firstOrFail();
    expect($attempt->age_at_test)->toBe(16);

    // Task 14: 응시 전 기본정보(닉네임·성별) 단계를 거쳐야 한다.
    $this->post(route('oymsi.profile.submit', 'OY_MSI'), ['nickname' => '민수', 'gender' => 'male'])
        ->assertRedirect(route('assessment.take', ['OY_MSI', $attempt->id]));
    $this->get(route('assessment.take', ['OY_MSI', $attempt->id]))->assertOk();
});

test('링크 경로 만 16세는 연령 → 동의 → 검사까지 이어지고 sensitive 동의가 기록된다', function () {
    $voucher = ageGateVoucher($this->test, $this->user, 'tok16');

    $this->post(route('link.age.submit', 'tok16'), birthdateFields(birthdateForAge(16)))
        ->assertRedirect(route('link.landing', 'tok16'));
    $this->get(route('link.landing', 'tok16'))->assertOk();

    $this->post(route('link.start', 'tok16'), ['nickname' => '홍길동', 'gender' => 'male', 'agree' => '1'])
        ->assertRedirect();

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->latest('id')->firstOrFail();
    expect($attempt->age_at_test)->toBe(16);
    expect($attempt->assessment_version)->toBe('1.0.1');

    $sensitive = ConsentRecord::where('attempt_id', $attempt->id)
        ->where('consent_type', ConsentGate::SENSITIVE)->first();
    expect($sensitive)->not->toBeNull();
    expect($sensitive->actor)->toBe('youth');

    // 만 14세 이상이므로 guardian_offline 은 기록되지 않는다
    expect(ConsentRecord::where('attempt_id', $attempt->id)
        ->where('consent_type', ConsentGate::GUARDIAN_OFFLINE)->count())->toBe(0);

    $this->get(route('link.take', ['tok16', $attempt->id]))->assertOk();
});

// ── 회귀: 다른 검사·옛 플로우는 연령 게이트에 걸리지 않는다 ─────────────────

test('guardian_consent_below_age 가 없는 기존 검사는 연령 게이트가 걸리지 않는다', function () {
    (new Database\Seeders\SampleTestSeeder())->run();

    $this->actingAs($this->user)
        ->get(route('assessment.consent', 'KMSIA-SAMPLE'))
        ->assertOk();
});

test('옛 requires_guardian_consent 플로우는 연령 게이트와 별개로 그대로 동작한다', function () {
    $old = Test::create([
        'code' => 'ELEM-AGE', 'room' => 'elem', 'title_easy' => '초등 샘플', 'title_pro' => 'GC',
        'target' => '부모용', 'duration_min' => 7, 'item_count' => 1, 'areas' => ['불안'],
        'result_type' => 'signal', 'description' => 'd', 'status' => 'active',
        'requires_guardian_consent' => true,
    ]);
    expect($old->guardian_consent_below_age)->toBeNull();

    $this->actingAs($this->user)->get(route('assessment.consent', 'ELEM-AGE'))->assertOk();
    $this->post(route('assessment.agree', 'ELEM-AGE'), ['agree' => '1', 'guardian_agree' => '1'])
        ->assertRedirect(route('assessment.take', [
            'ELEM-AGE',
            TestAttempt::where('test_id', $old->id)->latest('id')->firstOrFail()->id,
        ]));
});

// 달력 피커로 되돌아가면 연도 스크롤 불편이 그대로 돌아온다 — 폼 모양 자체를 고정한다.
test('연령 입력은 달력 피커가 아니라 숫자 세 칸이다', function () {
    $html = $this->actingAs($this->user)
        ->get(route('oymsi.age.form', 'OY_MSI'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('type="date"');
    foreach (['birth_year', 'birth_month', 'birth_day'] as $field) {
        expect($html)->toContain('name="'.$field.'"');
    }
    expect($html)->toContain('inputmode="numeric"');
});
