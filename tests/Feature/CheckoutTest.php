<?php
use App\Models\{Test, Product, Order, User};

function checkoutPaidTest(): array {
    $t = Test::create(['code'=>'CK','room'=>'univ','title_easy'=>'대학생 마음검사','title_pro'=>'CK','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $p = Product::create(['test_id'=>$t->id,'name'=>'CK 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    return [$t,$p];
}

test('checkout show requires login', function () {
    [$t,$p] = checkoutPaidTest();
    $this->get("/checkout/{$p->id}")->assertRedirect(route('login'));
});

test('checkout show renders product and price for logged-in user', function () {
    [$t,$p] = checkoutPaidTest();
    $this->actingAs(User::factory()->create())
        ->get("/checkout/{$p->id}")->assertOk()
        ->assertSee('CK 검사권')->assertSee('9,900');
});

test('checkout start creates pending order and shows pay step', function () {
    [$t,$p] = checkoutPaidTest();
    $u = User::factory()->create();
    $this->actingAs($u)->post("/checkout/{$p->id}")->assertOk()->assertSee('결제');
    $o = Order::where('user_id',$u->id)->first();
    expect($o)->not->toBeNull();
    expect($o->status)->toBe('pending');
    expect($o->total_amount)->toBe(9900);
    expect($o->items()->count())->toBe(1);
});
