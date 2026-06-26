<?php
test('coaching page renders program intro and age-group programs', function () {
    $this->get('/coaching')->assertOk()
        ->assertSee('변화가 시작됩니다')
        ->assertSee('마음안전 신호등 교실')   // 초등 프로그램
        ->assertSee('번아웃 리셋 마음관리')   // 직장인 프로그램
        ->assertSee('1:1 코칭');              // 유형 칩
});
