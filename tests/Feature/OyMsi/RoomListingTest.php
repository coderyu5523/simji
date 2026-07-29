<?php
use App\Models\Test;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
});

test('중고등학생 방에 준비중 자리표시자가 실물 검사와 중복되지 않는다', function () {
    // 실물이 생겼으므로 Rooms.php 의 '청소년 마음상태검사' 플레이스홀더는 없어야 한다.
    $this->get(route('catalog.room', 'middle'))
        ->assertOk()
        ->assertDontSee('청소년 마음상태검사');
});

test('시더가 만든 상태 그대로 중고등학생 방에 검사 카드가 뜨고 상세로 이어진다', function () {
    $res = $this->get(route('catalog.room', 'middle'));
    $res->assertOk();
    $res->assertSee($this->test->title_easy);          // 마음상태검사
    $res->assertSee(route('catalog.show', 'OY_MSI'));  // 카드가 상세로 연결된다
});

// 공개 여부는 tests.status 로만 제어된다. 지금은 시더가 active 로 만들지만,
// 다시 닫아야 할 때 이 가드가 살아 있는지는 계속 검증한다.
test('status 를 draft 로 되돌리면 방 목록에서 사라진다', function () {
    $this->test->update(['status' => 'draft']);

    $this->get(route('catalog.room', 'middle'))
        ->assertOk()
        ->assertDontSee(route('catalog.show', 'OY_MSI'));
});
