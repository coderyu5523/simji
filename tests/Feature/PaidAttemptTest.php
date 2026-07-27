<?php
use App\Models\{Test, Product, User, Voucher, TestAttempt};

function paidAttemptTest(): Test {
    $t = Test::create(['code'=>'PA','room'=>'univ','title_easy'=>'검사','title_pro'=>'PA','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Product::create(['test_id'=>$t->id,'name'=>'PA 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    return $t;
}

test('start on paid test without voucher redirects to checkout', function () {
    $t = paidAttemptTest();
    $u = User::factory()->create();
    $p = $t->activeProduct();
    $this->actingAs($u)->post("/assessment/{$t->code}/start")
        ->assertRedirect(route('checkout.show', $p->id));
    expect(TestAttempt::count())->toBe(0);
});

test('start on paid test as guest redirects to login', function () {
    $t = paidAttemptTest();
    $this->post("/assessment/{$t->code}/start")->assertRedirect(route('login'));
});

test('start on paid test with voucher consumes it and creates attempt', function () {
    $t = paidAttemptTest();
    $u = User::factory()->create();
    $v = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $this->actingAs($u)->post("/assessment/{$t->code}/start")->assertRedirect();
    $a = TestAttempt::where('user_id',$u->id)->first();
    expect($a)->not->toBeNull();
    expect($a->voucher_id)->toBe($v->id);
    expect($v->fresh()->status)->toBe('used');
});

test('free sample test still works for logged-in user (regression)', function () {
    // 2026-07-02 정책으로 무료·유료 구분 없이 검사 응시는 로그인 필수가 됐다.
    // 이 테스트는 원래 게스트 기준이었으나 정책 변경에 맞춰 로그인 사용자 여정으로 바꿨다.
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $u = User::factory()->create();
    $this->actingAs($u)->post('/assessment/KMSIA-SAMPLE/start')->assertRedirect();
    expect(TestAttempt::where('user_id', $u->id)->count())->toBe(1);
});

test('free sample test blocks guest and redirects to login', function () {
    // 정책이 코드로 지켜지는지 검증: 비회원은 무료 샘플 검사도 응시할 수 없어야 한다.
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $this->post('/assessment/KMSIA-SAMPLE/start')->assertRedirect(route('login'));
    expect(TestAttempt::count())->toBe(0);
});
