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

// data-item-code 는 검사 중 안전 안내 스크립트의 후크였고 그 스크립트를 걷어내면서 같이 뺐다.
// 안전문항에만 남는 실제 동작은 응답거부 선택지다 — 그쪽을 고정한다.
test('응답거부 선택지는 안전문항에만 붙는다', function () {
    $html = $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertOk()
        ->getContent();

    $safCount = $this->test->items->where('area', 'SAF')->count();

    expect($safCount)->toBe(6);
    expect(substr_count($html, 'value="PREFER_NOT"'))->toBe($safCount);
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

// Fix round 1 — Critical 1: options 가 없는 기존 검사의 보기 라벨이 한글에서 숫자(1~5)로
// 퇴행했었다. AssessmentTakeTest 는 문항 텍스트만 확인해서 라벨이 사라져도 통과했다.
// 재발 방지를 위해 한글 라벨을 직접 고정한다.
test('기존 5점 검사는 숫자가 아니라 한글 라벨로 렌더된다 (회귀 — Critical 1)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $res = $this->actingAs($this->user)
        ->get(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]));
    $res->assertOk();
    $res->assertSee('전혀 아니다');
    $res->assertSee('아니다');
    $res->assertSee('보통');
    $res->assertSee('그렇다');
    $res->assertSee('매우 그렇다');
});

// 검사 중 안전 안내(모달·배너)는 제거했다 — OY_MSI 는 표준화 전 데이터 수집용
// 사전검사라 응답 중 개입 장치를 두지 않기로 했다(2026-07-31). 안전 안내는 결과 화면에만
// 있고, 등급 판정은 서버 SafetyEvaluator 가 그대로 한다.
test('OY_MSI 응시 화면에 안전 모달·배너를 띄우지 않는다', function () {
    $res = $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]));

    $res->assertOk();
    $res->assertDontSee('safety-modal', false);
    $res->assertDontSee('safety-banner', false);
    $res->assertDontSee('attachSafetyAlert', false);
    $res->assertDontSee('109', false);
});

test('기존 5점 검사 화면에도 안전 모달이 없다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $res = $this->actingAs($this->user)
        ->get(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]));
    $res->assertOk();
    $res->assertDontSee('safety-modal', false);
    $res->assertDontSee('attachSafetyAlert', false);
});

// Fix round 1 — Important 5: OY_MSI 문항인데 options 시딩이 누락되면 조용히 1~5로
// 렌더하지 않고 예외를 던져야 한다 (SAF 문항이면 0-based 안전등급 계산이 조용히 틀어짐).
test('OY_MSI 문항에 options 가 없으면 조용히 1~5로 렌더하지 않고 예외를 던진다', function () {
    $item = $this->test->items->firstWhere('item_code', 'DEP01');
    $item->update(['options' => null]);

    $this->withoutExceptionHandling();

    $thrown = null;
    try {
        $this->actingAs($this->user)
            ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]));
    } catch (\Throwable $e) {
        $thrown = $e;
    }

    // Blade 뷰 안에서 던진 예외는 Illuminate\View\ViewException 으로 감싸져 올라온다.
    // 실제 원인(previous)이 우리가 던진 RuntimeException 이고 메시지가 맞는지 확인한다.
    expect($thrown)->not->toBeNull();
    $root = $thrown;
    while ($root->getPrevious() !== null) {
        $root = $root->getPrevious();
    }
    expect($root)->toBeInstanceOf(\RuntimeException::class);
    expect($root->getMessage())->toContain('DEP01');
    expect($root->getMessage())->toContain('options가 없습니다');
});
