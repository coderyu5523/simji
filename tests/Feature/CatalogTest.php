<?php
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

test('catalog index shows all five rooms', function () {
    $this->get('/tests')->assertOk()
        ->assertSee('초등학생')->assertSee('중고등학생')
        ->assertSee('대학생')->assertSee('직장인·성인')->assertSee('실버');
});
test('elem room shows coming-soon cards with guardian badge', function () {
    $this->get('/tests/room/elem')->assertOk()
        ->assertSee('마음안전선별검사')
        ->assertSee('준비중')
        ->assertSee('보호자 동의 필요');
});
test('worker room shows active sample then coming-soon cards', function () {
    $this->get('/tests/room/worker')->assertOk()
        ->assertSee('직장인 마음상태 검사(샘플)')
        ->assertSee('번아웃검사')
        ->assertSee('준비중');
});
test('room page with unknown code 404', function () {
    $this->get('/tests/room/nope')->assertNotFound();
});
test('test detail shows meta and start button', function () {
    $this->get('/tests/KMSIA-SAMPLE')->assertOk()->assertSee('검사 시작')->assertSee('스트레스')->assertSee('약 5분');
});
