<?php
use App\Models\Test;
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

test('catalog index shows three rooms and concern tags', function () {
    $this->get('/tests')->assertOk()->assertSee('대학생')->assertSee('직장인·성인')->assertSee('실버')->assertSee('번아웃');
});
test('room page lists tests of that room', function () {
    $this->get('/tests/room/worker')->assertOk()->assertSee('직장인 마음상태 검사(샘플)');
});
test('room page with unknown code 404', function () {
    $this->get('/tests/room/nope')->assertNotFound();
});
test('test detail shows meta and start button', function () {
    $this->get('/tests/KMSIA-SAMPLE')->assertOk()->assertSee('검사 시작')->assertSee('스트레스')->assertSee('약 5분');
});
