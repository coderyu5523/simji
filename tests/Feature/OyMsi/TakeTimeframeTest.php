<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\OyMsi\ConsentGate;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

test('문항 화면에 기간 안내 문구를 따로 붙이지 않는다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
        'age_at_test' => 16, 'nickname' => '민수',
    ]);
    app(ConsentGate::class)->record($attempt, ConsentGate::SENSITIVE, 'youth', $this->user->id);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertOk()
        ->assertDontSee('기준으로 답해');
});

// 안내 문구를 뺄 수 있는 근거. 이 단언이 깨지면 응답 기준 기간이 화면에서 사라진 것이므로
// 안내 문구를 되살리거나 문항 텍스트를 고쳐야 한다.
test('12개월 기준 문항은 문항 텍스트 자체에 기간이 적혀 있다', function () {
    $twelveMonth = $this->test->items->where('timeframe_code', 'PAST_12_MONTHS');

    expect($twelveMonth->pluck('item_code')->all())->toBe(['SAF05', 'SAF06']);

    foreach ($twelveMonth as $item) {
        expect($item->text)->toContain('최근 12개월 동안');
    }
});

// 안내(intro) 단계를 없애면서 이 문구는 동의 화면으로 옮겼다.
// 동의 화면은 연령 게이트 뒤에 있으므로(fail closed) 나이를 먼저 통과시킨다.
test('2주 기준은 동의 화면이 알린다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), [
            'birthdate' => now()->subYears(16)->subDay()->format('Y-m-d'),
        ]);

    $this->actingAs($this->user)
        ->get(route('assessment.consent', 'OY_MSI'))
        ->assertOk()
        ->assertSee('최근 2주');
});
