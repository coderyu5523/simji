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

// 안내(intro) 단계 제거 후: 기본정보 단계가 없는 검사는 동의가 곧 시작이다.
test('agree starts the test and leads straight to take', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->post('/assessment/KMSIA-SAMPLE/agree', ['agree' => '1']);

    $attempt = TestAttempt::where('user_id', $user->id)->firstOrFail();
    expect($attempt->status)->toBe('in_progress');

    $this->actingAs($user)
        ->post('/assessment/KMSIA-SAMPLE/agree', ['agree' => '1'])
        ->assertRedirect(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]));
});

// 동의 화면 뒤로가기 후 재동의로 고아 attempt 가 쌓이면 안 된다.
test('re-agreeing reuses the unsubmitted attempt instead of piling up new ones', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/assessment/KMSIA-SAMPLE/agree', ['agree' => '1']);
    $this->actingAs($user)->post('/assessment/KMSIA-SAMPLE/agree', ['agree' => '1']);

    expect(TestAttempt::where('user_id', $user->id)->count())->toBe(1);
});

test('start creates an attempt and redirects to take', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->post('/assessment/KMSIA-SAMPLE/start');
    $attempt = TestAttempt::where('user_id', $user->id)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('in_progress');
});
