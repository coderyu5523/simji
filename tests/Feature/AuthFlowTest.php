<?php

test('guest can start a guest session via route', function () {
    $res = $this->get('/guest/start');
    $res->assertRedirect(route('catalog.index'));
    expect(session('guest_token'))->not->toBeNull();
});

test('kakao redirect route exists', function () {
    $this->get('/auth/kakao')->assertRedirectContains('kakao'); // socialite redirect
})->skip(env('KAKAO_CLIENT_ID') === null || env('KAKAO_CLIENT_ID') === '', '카카오 키 미설정 — 키 등록 후 활성화');
