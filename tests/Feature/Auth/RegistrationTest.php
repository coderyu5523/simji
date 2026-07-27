<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    // 2026-07-01 가입 필드 추가(마이그레이션 2건) + 회원가입 유형 선택 개편(personal/institution) 이후
    // RegisteredUserController::store()는 user_type·phone·terms를 필수로 요구하는데
    // (resources/views/auth/register-personal.blade.php 실제 폼과 동일 필드) 이 테스트는
    // 개편 이전 페이로드(name/email/password만)를 보내고 있었다. 검증 실패 → 리다이렉트
    // → 미인증 상태였을 뿐, 가입 기능 자체는 정상 동작한다(테스트가 낡은 케이스).
    $response = $this->post('/register', [
        'user_type' => 'personal',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '010-1234-5678',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('my.index', absolute: false));
});
