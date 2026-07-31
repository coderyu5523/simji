<?php
use App\Models\{Test, User};

function makeGuardianTest(): Test {
    return Test::create([
        'code'=>'ELEM-GC','room'=>'elem','title_easy'=>'초등 마음안전(샘플)','title_pro'=>'GC',
        'target'=>'초1~초6 부모·교사용','duration_min'=>7,'item_count'=>4,
        'areas'=>['불안'],'result_type'=>'signal','description'=>'d','status'=>'active',
        'requires_guardian_consent'=>true,
    ]);
}

// consent/agree는 로그인 필수 정책이므로 전부 로그인 사용자로 요청한다.

test('guardian test consent shows guardian section and reporting notice', function () {
    makeGuardianTest();
    $this->actingAs(User::factory()->create())
        ->get('/assessment/ELEM-GC/consent')->assertOk()
        ->assertSee('만 14세 미만')
        ->assertSee('법정대리인')
        ->assertSee('아동학대'); // 신고의무 임시 고지
});

test('guardian test agree requires guardian_agree', function () {
    makeGuardianTest();
    $this->actingAs(User::factory()->create())
        ->from('/assessment/ELEM-GC/consent')
        ->post('/assessment/ELEM-GC/agree', ['agree'=>'1']) // guardian_agree 누락
        ->assertSessionHasErrors('guardian_agree');
});

test('guardian test agree passes with both checks', function () {
    $test = makeGuardianTest();
    $user = User::factory()->create();
    $this->actingAs($user)
        ->post('/assessment/ELEM-GC/agree', ['agree'=>'1','guardian_agree'=>'1'])
        ->assertRedirect(route('assessment.take', [
            'ELEM-GC',
            \App\Models\TestAttempt::where('test_id', $test->id)->latest('id')->firstOrFail()->id,
        ]));
});

test('adult test consent unchanged (no guardian section)', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $this->actingAs(User::factory()->create())
        ->get('/assessment/KMSIA-SAMPLE/consent')->assertOk()
        ->assertSee('민감정보')
        ->assertDontSee('법정대리인');
});
