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

    $this->attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
        'nickname' => '민수', 'gender' => 'male', 'age_at_test' => 16,
    ]);
    app(App\Services\OyMsi\ConsentGate::class)
        ->record($this->attempt, App\Services\OyMsi\ConsentGate::SENSITIVE, 'youth', $this->user->id);
});

test('4점 척도 보기 문구가 그대로 나온다', function () {
    $res = $this->actingAs($this->user)->get(route('assessment.take', ['OY_MSI', $this->attempt->id]));
    $res->assertOk();
    $res->assertSee('전혀 그렇지 않다');
    $res->assertSee('거의 항상 그렇다');
});

test('안전문항은 전용 척도 문구를 쓴다', function () {
    $res = $this->actingAs($this->user)->get(route('assessment.take', ['OY_MSI', $this->attempt->id]));
    $res->assertSee('자주 있었거나 지금도 그렇다');  // SAF_THOUGHT_4PT
    $res->assertSee('4회 이상 또는 최근 1개월 안에 있었다'); // SAF_BEHAVIOR_4PT
});

test('응답값이 0부터 시작한다', function () {
    $first = $this->test->items->firstWhere('item_code', 'DEP01');
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('name="answers['.$first->id.']" value="0"', false);
});

test('12개월 기준 문항에 기간 안내가 붙는다', function () {
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('최근 12개월');
});

test('응답거부 선택지가 제공된다', function () {
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('응답하기 어려움');
});

test('안전문항에 data-safety 속성이 붙는다', function () {
    $saf = $this->test->items->firstWhere('item_code', 'SAF04');
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('data-item-code="SAF04"', false);
    expect($saf->area)->toBe('SAF');
});

test('기존 5점 검사 화면은 그대로 동작한다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]))
        ->assertOk();
});
