<?php
use App\Models\{Test, TestAttempt, TestResult};
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

function makeAttempt(): array {
    $test = Test::where('code','KMSIA-SAMPLE')->with('items')->first();
    $attempt = TestAttempt::create(['test_id'=>$test->id,'guest_token'=>'g-1','status'=>'in_progress','started_at'=>now()]);
    return [$test, $attempt];
}

test('take page renders all items', function () {
    [$test, $attempt] = makeAttempt();
    $this->withSession(['guest_token'=>'g-1'])
        ->get("/assessment/KMSIA-SAMPLE/take/{$attempt->id}")
        ->assertOk()->assertSee('요즘 사소한 일에도');
});

test('submit stores answers, scores, and redirects to result', function () {
    [$test, $attempt] = makeAttempt();
    $answers = [];
    foreach ($test->items as $item) $answers[$item->id] = 3;
    $res = $this->withSession(['guest_token'=>'g-1'])
        ->post("/assessment/KMSIA-SAMPLE/take/{$attempt->id}", ['answers' => $answers]);
    $res->assertRedirect(route('result.show', $attempt->id));
    expect(TestResult::where('attempt_id',$attempt->id)->exists())->toBeTrue();
    expect($attempt->fresh()->status)->toBe('submitted');
});

test('cannot access others attempt', function () {
    [$test, $attempt] = makeAttempt();
    $this->withSession(['guest_token'=>'other'])
        ->get("/assessment/KMSIA-SAMPLE/take/{$attempt->id}")->assertForbidden();
});
