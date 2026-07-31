<?php
use App\Models\{Test, User};
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

// 검사 응시는 로그인 필수 정책(routes/web.php)이라 여정 전체를 로그인 사용자로 수행한다.
// 카탈로그·검사 상세는 비로그인에서도 열람 가능하므로 그대로 둔다.
test('logged-in user completes the whole assessment journey', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/')->assertOk();
    $this->get('/tests')->assertOk();
    $this->get('/tests/room/worker')->assertSee('직장인 마음상태 검사(샘플)');
    $this->get('/tests/KMSIA-SAMPLE')->assertSee('검사 시작');

    // 안내(intro) 단계 제거: 동의가 곧 시작이다. 리다이렉트 주소만 믿지 않고 실제로 따라간다.
    $this->get('/assessment/KMSIA-SAMPLE/consent')->assertOk();
    $agree = $this->post('/assessment/KMSIA-SAMPLE/agree', ['agree'=>'1']);

    $attempt = \App\Models\TestAttempt::latest('id')->first();
    $agree->assertRedirect(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]));
    $this->get($agree->headers->get('Location'))->assertOk();
    expect($attempt->user_id)->toBe($user->id);

    $test = Test::where('code','KMSIA-SAMPLE')->with('items')->first();
    $answers = [];
    foreach ($test->items as $item) $answers[$item->id] = 4;
    $this->post("/assessment/KMSIA-SAMPLE/take/{$attempt->id}", ['answers'=>$answers])
        ->assertRedirect(route('result.show', $attempt->id));

    $this->get(route('result.show', $attempt->id))->assertOk()->assertSee('나의 마음상태 결과');
    $this->get('/my')->assertSee('직장인 마음상태 검사(샘플)');
});
