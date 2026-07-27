<?php
use App\Models\{Test, User, Voucher};

test('my page lists active vouchers with test name', function () {
    // /my 리디자인(03f1597 "검사권 발급을 심리검사로 이동") 이후, 미발급(access_token 없음) 보유
    // 검사권은 개수만 표시되고 검사명으로 나열되는 건 "발급한 검사권"(access_token 있는 것) 목록이다.
    // 검사명이 보이는 실제 화면(발급 명부)에 맞춰 access_token을 부여한 발급 상태로 만든다.
    $u = User::factory()->create();
    $t = Test::create(['code'=>'MV','room'=>'univ','title_easy'=>'내 마음검사','title_pro'=>'MV','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear(),'access_token'=>'mv-token','assigned_at'=>now()]);
    $this->actingAs($u)->get('/my')->assertOk()->assertSee('내 마음검사')->assertSee('검사권');
});
