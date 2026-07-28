<?php
use App\Models\ConsentRecord;
use App\Models\Product;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

test('OY_MSI 는 consent_required 가 켜져 있다', function () {
    expect($this->test->consent_required)->toBeTrue();
});

// Task 13 부터 OY_MSI 는 동의보다 앞선 단계로 연령 게이트를 거친다. 나이가 세션에 없으면
// agree() 가 연령 게이트로 되돌리므로, 동의 로직 자체를 보는 아래 테스트들은 나이를 미리 채워둔다
// (연령 게이트의 차단 규칙은 AgeGateTest 가 검증한다).
test('동의하면 attempt 가 created 상태로 생기고 동의 기록이 남는다', function () {
    $this->actingAs($this->user)
        ->withSession(['oymsi_age:OY_MSI' => 16])
        ->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])
        ->assertRedirect();

    $attempt = TestAttempt::where('test_id', $this->test->id)->latest('id')->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('created');
    expect($attempt->assessment_version)->toBe('1.0.1');
    expect($attempt->scoring_version)->toBe('1.0.0');
    expect($attempt->age_at_test)->toBe(16);

    $consent = ConsentRecord::where('attempt_id', $attempt->id)->where('consent_type', 'sensitive')->first();
    expect($consent)->not->toBeNull();
    expect($consent->granted)->toBeTrue();
    expect($consent->actor)->toBe('youth');
    expect($consent->actor_user_id)->toBe($this->user->id);
});

test('동의 없이 만든 attempt 로는 take 에 들어갈 수 없다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertForbidden();
});

test('동의 없이 submit 을 직접 호출해도 차단된다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);
    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 0;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), ['answers' => $answers])
        ->assertForbidden();

    expect($attempt->fresh()->status)->not->toBe('submitted');
});

test('동의 체크를 빠뜨리면 attempt 가 만들어지지 않는다', function () {
    $this->actingAs($this->user)
        ->post(route('assessment.agree', 'OY_MSI'), [])
        ->assertSessionHasErrors('agree');

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
});

test('consent_required 가 꺼진 기존 검사는 영향받지 않는다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    expect($sample->consent_required)->toBeFalse();

    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]))
        ->assertOk();
});

// ── Fix round 1 추가분 ──────────────────────────────────────────────

test('동의 없이 start 를 직접 호출해도 차단되고 attempt 가 생기지 않는다 (Important 6 — 주석이 명시한 위협)', function () {
    // agree() 를 거치지 않은 깨끗한 세션으로 start() 를 바로 때린다 —
    // AssessmentController::agree() 주석이 명시한 바로 그 우회 시나리오.
    $this->actingAs($this->user)
        ->post(route('assessment.start', 'OY_MSI'))
        ->assertForbidden();

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
});

test('동의 폼을 여러 번 제출해도 attempt 와 동의기록이 하나만 생긴다 (Important 7)', function () {
    $this->actingAs($this->user)->withSession(['oymsi_age:OY_MSI' => 16]);
    $this->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1']);
    $this->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1']);
    $this->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1']);

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(1);
    expect(ConsentRecord::where('consent_type', 'sensitive')->count())->toBe(1);
});

test('제출 완료된 attempt 로 start 를 다시 호출해도 submitted 가 in_progress 로 되돌아가지 않는다 (Important 5 — 회귀)', function () {
    $this->actingAs($this->user)
        ->withSession(['oymsi_age:OY_MSI' => 16])
        ->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1']);
    $attempt = TestAttempt::where('test_id', $this->test->id)->latest('id')->first();
    $attempt->update(['status' => 'submitted', 'submitted_at' => now()]);

    $this->actingAs($this->user)
        ->post(route('assessment.start', 'OY_MSI'))
        ->assertStatus(409);

    expect($attempt->fresh()->status)->toBe('submitted');
});

function oyMsiPaidConsentTest(): Test
{
    $t = Test::create([
        'code' => 'PAC', 'room' => 'middle', 'title_easy' => '유료+동의 검사(테스트용)', 'title_pro' => 'PAC',
        'target' => '테스트', 'duration_min' => 5, 'item_count' => 1, 'areas' => [],
        'result_type' => 'signal', 'description' => 'd', 'status' => 'active',
        'consent_required' => true,
    ]);
    Product::create(['test_id' => $t->id, 'name' => 'PAC 검사권', 'price' => 9900, 'credit_qty' => 1, 'valid_days' => 365, 'status' => 'active']);
    return $t;
}

test('consent_required 검사도 유료면 검사권 없이는 checkout 으로 간다 — 자격 확인이 동의 분기보다 먼저다 (Important 4)', function () {
    $t = oyMsiPaidConsentTest();
    $this->actingAs($this->user)
        ->post(route('assessment.agree', 'PAC'), ['agree' => '1']);
    $attempt = TestAttempt::where('test_id', $t->id)->latest('id')->first();
    expect($attempt->status)->toBe('created');

    $this->actingAs($this->user)
        ->post(route('assessment.start', 'PAC'))
        ->assertRedirect(route('checkout.show', $t->activeProduct()->id));

    // 검사권 없이는 in_progress 로 넘어가지 않는다 — 동의 분기가 결제 확인을 건너뛰지 않는지 확인
    expect($attempt->fresh()->status)->toBe('created');
});

test('consent_required 검사에 검사권이 있으면 소비되고 attempt 가 이어진다 (Important 4)', function () {
    $t = oyMsiPaidConsentTest();
    $this->actingAs($this->user)
        ->post(route('assessment.agree', 'PAC'), ['agree' => '1']);
    $attempt = TestAttempt::where('test_id', $t->id)->latest('id')->first();

    $voucher = Voucher::create([
        'user_id' => $this->user->id, 'test_id' => $t->id, 'status' => 'active',
        'source' => 'purchase', 'issued_at' => now(), 'expires_at' => now()->addYear(),
    ]);

    $this->actingAs($this->user)
        ->post(route('assessment.start', 'PAC'))
        ->assertRedirect(route('assessment.take', ['PAC', $attempt->id]));

    expect($attempt->fresh()->status)->toBe('in_progress');
    expect($attempt->fresh()->voucher_id)->toBe($voucher->id);
    expect($voucher->fresh()->status)->toBe('used');
});
