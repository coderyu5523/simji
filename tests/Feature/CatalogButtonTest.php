<?php
use App\Models\{Test, Product, User, Voucher};

function showTest(bool $paid): Test {
    $t = Test::create(['code'=>'CB'.($paid?'P':'F'),'room'=>'univ','title_easy'=>'마음검사','title_pro'=>'CB','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>['스트레스'],'result_type'=>'signal','description'=>'d','status'=>'active']);
    if ($paid) Product::create(['test_id'=>$t->id,'name'=>'CB 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    return $t;
}

test('free test shows 검사 시작 to guest', function () {
    $t = showTest(false);
    $this->get("/tests/{$t->code}")->assertOk()->assertSee('검사 시작');
});

test('paid test shows price and 구매 to guest', function () {
    $t = showTest(true);
    $this->get("/tests/{$t->code}")->assertOk()->assertSee('9,900')->assertSee('구매');
});

test('paid test shows 검사 시작 when user owns a voucher', function () {
    $t = showTest(true);
    $u = User::factory()->create();
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $this->actingAs($u)->get("/tests/{$t->code}")->assertOk()->assertSee('검사 시작');
});
