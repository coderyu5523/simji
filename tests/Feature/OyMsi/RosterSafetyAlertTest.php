<?php
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

/**
 * 응시가 끝난 명부 한 줄(검사권 + attempt + 결과)을 만든다.
 * 다른 파일의 voucher 헬퍼들과 이름이 겹치면 안 된다(Pest 전역 함수).
 */
function rosterRow(
    Test $test,
    User $issuer,
    string $token,
    ?string $safetyLevel = null,
    ?string $environmentLevel = null,
    string $overallSignal = 'green',
): Voucher {
    $voucher = Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id,
        'source' => 'issued_free', 'status' => 'used',
        'issued_at' => now(), 'assigned_at' => now(),
        'access_token' => $token,
    ]);

    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.$token,
        'test_id' => $test->id, 'voucher_id' => $voucher->id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 16, 'nickname' => '민수',
    ]);

    TestResult::create([
        'attempt_id' => $attempt->id,
        'area_scores' => [], 'area_signals' => [], 'recommendations' => [],
        'overall_level' => 'low', 'overall_signal' => $overallSignal,
        'interpretation' => '테스트',
        'safety_level' => $safetyLevel,
        'environment_level' => $environmentLevel,
    ]);

    $voucher->update(['used_attempt_id' => $attempt->id, 'used_at' => now()]);

    return $voucher;
}

// ── 안전등급 배지 ──────────────────────────────────────────────────────

test('S3 응시는 명부에 즉시 확인 배지가 뜬다', function () {
    rosterRow($this->test, $this->staff, 'tok-s3', safetyLevel: 'S3');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertSee('안전 확인 — 즉시');
});

test('S2 와 S1 응시는 당일 확인 배지가 뜬다', function () {
    rosterRow($this->test, $this->staff, 'tok-s2', safetyLevel: 'S2');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertSee('안전 확인 — 당일');

    rosterRow($this->test, $this->staff, 'tok-s1', safetyLevel: 'S1');

    $res = $this->actingAs($this->staff)->get(route('my.index'));
    expect(substr_count($res->getContent(), '안전 확인 — 당일'))->toBe(2);
});

// assertDontSee 가 공허하지 않도록, 같은 화면에 배지가 실제로 렌더되는 줄을 함께 둔다.
test('S0 응시에는 안전 배지가 붙지 않는다', function () {
    rosterRow($this->test, $this->staff, 'tok-s0', safetyLevel: 'S0');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertDontSee('안전 확인');

    // 같은 화면에서 배지가 렌더될 수 있음을 증명
    rosterRow($this->test, $this->staff, 'tok-s3b', safetyLevel: 'S3');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertSee('안전 확인 — 즉시');
});

test('안전등급이 없는 다른 검사에는 배지가 붙지 않는다', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $plain = Test::where('code', 'KMSIA-SAMPLE')->firstOrFail();

    rosterRow($plain, $this->staff, 'tok-plain', safetyLevel: null);

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertDontSee('안전 확인');
});

// ── 핵심: 종합이 초록이어도 안전은 빨강일 수 있다 ─────────────────────────

test('종합이 초록이어도 안전등급이 S3 면 두 배지가 함께 뜬다', function () {
    rosterRow($this->test, $this->staff, 'tok-both', safetyLevel: 'S3', overallSignal: 'green');

    $res = $this->actingAs($this->staff)->get(route('my.index'));
    $res->assertOk();
    $res->assertSee('초록');            // 종합 신호등 (SAF 는 종합에서 제외된다)
    $res->assertSee('안전 확인 — 즉시'); // 안전등급은 별도로 드러난다
});

// ── 환경위험 ──────────────────────────────────────────────────────────

test('환경등급 E3 는 환경 배지가 뜨고 E1 은 뜨지 않는다', function () {
    rosterRow($this->test, $this->staff, 'tok-e3', environmentLevel: 'E3');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertSee('환경 위험 확인');

    $onlyE1 = rosterRow($this->test, $this->staff, 'tok-e1', environmentLevel: 'E1');

    // E1 줄만 남기면 환경 배지가 사라져야 한다
    $onlyE1->attempt->result->update(['environment_level' => 'E1']);
    Voucher::where('access_token', 'tok-e3')->delete();

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertDontSee('환경 위험 확인');
});

// ── 상단 요약 배너 ─────────────────────────────────────────────────────

test('경보가 없으면 요약 배너가 뜨지 않는다', function () {
    rosterRow($this->test, $this->staff, 'tok-none', safetyLevel: 'S0', environmentLevel: 'E0');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertDontSee('안전 확인이 필요한 응시');
});

test('경보가 있으면 요약 배너에 건수가 뜬다', function () {
    rosterRow($this->test, $this->staff, 'tok-b1', safetyLevel: 'S3');
    rosterRow($this->test, $this->staff, 'tok-b2', safetyLevel: 'S1');
    rosterRow($this->test, $this->staff, 'tok-b3', safetyLevel: 'S0');

    $res = $this->actingAs($this->staff)->get(route('my.index'));
    $res->assertOk();
    $res->assertSee('안전 확인이 필요한 응시 2건');
});

// ── fail closed ───────────────────────────────────────────────────────

test('알 수 없는 안전등급 값은 낮추지 않고 즉시로 취급한다', function () {
    rosterRow($this->test, $this->staff, 'tok-weird', safetyLevel: 'S9');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertSee('안전 확인 — 즉시');
});
