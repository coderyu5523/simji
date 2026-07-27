<?php
use App\Models\{Test, TestAttempt, User};
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

// 검사 진행(consent/agree/intro/start/take)은 로그인 필수 정책(routes/web.php)이므로
// 모든 요청을 로그인 사용자로 수행한다.

test('consent page shows sensitive-info agreement', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/assessment/KMSIA-SAMPLE/consent')->assertOk()->assertSee('민감정보')->assertSee('동의');
});

test('agree leads to intro', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->post('/assessment/KMSIA-SAMPLE/agree', ['agree' => '1'])
        ->assertRedirect(route('assessment.intro', 'KMSIA-SAMPLE'));
});

test('start creates an attempt and redirects to take', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->post('/assessment/KMSIA-SAMPLE/start');
    $attempt = TestAttempt::where('user_id', $user->id)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('in_progress');
});
