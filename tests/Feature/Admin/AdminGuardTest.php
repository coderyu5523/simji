<?php
use App\Models\User;

test('비로그인은 관리자 화면에 접근할 수 없다', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('일반 회원은 관리자 화면에 접근할 수 없다', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('관리자는 접근할 수 있다', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('관리자 하위 화면도 모두 막힌다', function () {
    $member = User::factory()->create();

    foreach (['/admin/members', '/admin/orders', '/admin/tests'] as $path) {
        $this->actingAs($member)->get($path)->assertForbidden();
    }
});
