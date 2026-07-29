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

test('활성화하면 중고등학생 방에 검사 카드가 뜨고 상세로 이어진다', function () {
    $this->test->update(['status' => 'active']);

    $res = $this->get(route('catalog.room', 'middle'));
    $res->assertOk();
    $res->assertSee($this->test->title_easy);          // 마음상태검사
    $res->assertSee(route('catalog.show', 'OY_MSI'));  // 카드가 상세로 연결된다
});

test('draft 인 동안에는 방 목록에 뜨지 않는다', function () {
    expect($this->test->status)->toBe('draft');

    $this->get(route('catalog.room', 'middle'))
        ->assertOk()
        ->assertDontSee(route('catalog.show', 'OY_MSI'));
});
