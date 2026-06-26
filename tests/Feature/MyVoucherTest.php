<?php
use App\Models\{Test, User, Voucher};

test('my page lists active vouchers with test name', function () {
    $u = User::factory()->create();
    $t = Test::create(['code'=>'MV','room'=>'univ','title_easy'=>'내 마음검사','title_pro'=>'MV','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $this->actingAs($u)->get('/my')->assertOk()->assertSee('내 마음검사')->assertSee('검사권');
});
