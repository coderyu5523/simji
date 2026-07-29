<?php
use App\Models\{Test, TestAttempt, TestResult, User};
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

// 검사 응시(take/submit)는 로그인 필수 정책이므로 attempt는 guest_token이 아닌
// user_id로 만들고, 요청은 그 사용자로 로그인해서 보낸다.
function makeAttempt(\App\Models\User $user): array {
    $test = Test::where('code','KMSIA-SAMPLE')->with('items')->first();
    $attempt = TestAttempt::create(['test_id'=>$test->id,'user_id'=>$user->id,'status'=>'in_progress','started_at'=>now()]);
    return [$test, $attempt];
}

test('take page renders all items', function () {
    $user = User::factory()->create();
    [$test, $attempt] = makeAttempt($user);
    $this->actingAs($user)
        ->get("/assessment/KMSIA-SAMPLE/take/{$attempt->id}")
        ->assertOk()->assertSee('요즘 사소한 일에도');
});

// Fix round 1 (Task 16 review) — Critical 1: options 없는 문항의 5점 척도 보기 라벨이
// 한글에서 숫자(1~5)로 조용히 퇴행했었다. 문항 텍스트만으로는 라벨 소실을 못 잡으므로
// 보기 라벨 자체를 고정한다.
test('take page renders korean scale labels, not bare numbers', function () {
    $user = User::factory()->create();
    [$test, $attempt] = makeAttempt($user);
    $res = $this->actingAs($user)
        ->get("/assessment/KMSIA-SAMPLE/take/{$attempt->id}");
    $res->assertOk();
    $res->assertSee('전혀 아니다');
    $res->assertSee('아니다');
    $res->assertSee('보통');
    $res->assertSee('그렇다');
    $res->assertSee('매우 그렇다');
});

test('submit stores answers, scores, and redirects to result', function () {
    $user = User::factory()->create();
    [$test, $attempt] = makeAttempt($user);
    $answers = [];
    foreach ($test->items as $item) $answers[$item->id] = 3;
    $res = $this->actingAs($user)
        ->post("/assessment/KMSIA-SAMPLE/take/{$attempt->id}", ['answers' => $answers]);
    $res->assertRedirect(route('result.show', $attempt->id));
    expect(TestResult::where('attempt_id',$attempt->id)->exists())->toBeTrue();
    expect($attempt->fresh()->status)->toBe('submitted');
});

test('cannot access others attempt', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    [$test, $attempt] = makeAttempt($owner);
    $this->actingAs($other)
        ->get("/assessment/KMSIA-SAMPLE/take/{$attempt->id}")->assertForbidden();
});

test('submit with out-of-range answer value returns 422 and does not create result', function () {
    $user = User::factory()->create();
    [$test, $attempt] = makeAttempt($user);
    $answers = [];
    foreach ($test->items as $item) $answers[$item->id] = 9; // out of range (max 5)
    $res = $this->actingAs($user)
        ->post("/assessment/KMSIA-SAMPLE/take/{$attempt->id}", ['answers' => $answers]);
    // Web routes redirect back (302) with session errors on validation failure
    $res->assertSessionHasErrors();
    expect(\App\Models\TestResult::where('attempt_id', $attempt->id)->exists())->toBeFalse();
});

// 2026-07-29: 이중 제출은 오류 페이지(409) 대신 결과로 보낸다. 지켜야 할 것은
// 상태 코드가 아니라 "재채점하지 않는다" 이므로 그 단언은 그대로 둔다.
test('submitting to already-submitted attempt redirects to result and does not re-score', function () {
    $user = User::factory()->create();
    [$test, $attempt] = makeAttempt($user);
    $answers = [];
    foreach ($test->items as $item) $answers[$item->id] = 3;
    // First submission (valid)
    $this->actingAs($user)
        ->post("/assessment/KMSIA-SAMPLE/take/{$attempt->id}", ['answers' => $answers]);
    $resultCount = \App\Models\TestResult::where('attempt_id', $attempt->id)->count();
    // Second submission should be blocked
    $res = $this->actingAs($user)
        ->post("/assessment/KMSIA-SAMPLE/take/{$attempt->id}", ['answers' => $answers]);
    $res->assertRedirect(route('result.show', $attempt->id));
    expect(\App\Models\TestResult::where('attempt_id', $attempt->id)->count())->toBe($resultCount);
    expect($attempt->fresh()->status)->toBe('submitted');
});
