<?php
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

function submitPayload(Test $test, array $overrides = [], $default = 0): array
{
    $answers = [];
    foreach ($test->items as $item) {
        $answers[$item->id] = array_key_exists($item->item_code, $overrides)
            ? $overrides[$item->item_code] : $default;
    }
    return ['answers' => $answers];
}

test('4점 척도에서 0점 응답이 통과한다 (기존 min:1 버그)', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), submitPayload($this->test, [], 0))
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe('submitted');
    expect($attempt->answers()->count())->toBe(60);
    expect($attempt->answers()->where('value', 0)->count())->toBe(60);
});

test('4점 척도에서 4 이상은 거부한다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), submitPayload($this->test, ['DEP01' => 4]))
        ->assertSessionHasErrors();
});

test('PREFER_NOT 은 value=null · missing_code 로 저장된다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]),
               submitPayload($this->test, ['SAF03' => 'PREFER_NOT']))
        ->assertRedirect();

    $item = $this->test->items->firstWhere('item_code', 'SAF03');
    $answer = $attempt->answers()->where('test_item_id', $item->id)->first();
    expect($answer->value)->toBeNull();
    expect($answer->missing_code)->toBe('PREFER_NOT');
});

test('기존 5점 척도 검사는 1~5 를 그대로 받는다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $answers = [];
    foreach ($sample->items as $item) $answers[$item->id] = 3;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['KMSIA-SAMPLE', $attempt->id]), ['answers' => $answers])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe('submitted');
});

test('기존 5점 척도 검사에서 0 은 여전히 거부된다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $answers = [];
    foreach ($sample->items as $item) $answers[$item->id] = 0;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['KMSIA-SAMPLE', $attempt->id]), ['answers' => $answers])
        ->assertSessionHasErrors();
});

test('type=likert4 인데 options 가 비어있는 문항은 조용히 1~5로 통과되지 않고 예외를 던진다', function () {
    $item = new \App\Models\TestItem([
        'id' => 999999, 'test_id' => $this->test->id, 'no' => 1,
        'text' => '깨진 문항', 'type' => 'likert4', 'options' => null,
        'item_code' => 'BAD01', 'area' => 'DEP',
    ]);
    $rule = new \App\Rules\AnswerValue(collect([999999 => $item]));

    expect(fn () => $rule->validate('answers.999999', 2, fn ($msg) => null))
        ->toThrow(\RuntimeException::class);
});
