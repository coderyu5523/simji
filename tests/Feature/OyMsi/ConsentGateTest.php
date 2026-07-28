<?php
use App\Models\ConsentRecord;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
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

test('동의하면 attempt 가 created 상태로 생기고 동의 기록이 남는다', function () {
    $this->actingAs($this->user)
        ->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])
        ->assertRedirect();

    $attempt = TestAttempt::where('test_id', $this->test->id)->latest('id')->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('created');
    expect($attempt->assessment_version)->toBe('1.0.1');
    expect($attempt->scoring_version)->toBe('1.0.0');

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
