<?php
use App\Models\{Test, Product, Order, OrderItem, Payment, Voucher, User};

test('test isPaid reflects active product', function () {
    $t = Test::create(['code'=>'PT','room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    expect($t->isPaid())->toBeFalse();
    expect($t->activeProduct())->toBeNull();
    Product::create(['test_id'=>$t->id,'name'=>'PT 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    expect($t->fresh()->isPaid())->toBeTrue();
    expect($t->fresh()->activeProduct()->price)->toBe(9900);
});

test('order has items payments and user', function () {
    $u = User::factory()->create();
    $t = Test::create(['code'=>'PT2','room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $o = Order::create(['order_no'=>'S-1','user_id'=>$u->id,'status'=>'pending','total_amount'=>9900]);
    $o->items()->create(['product_id'=>null,'test_id'=>$t->id,'product_name'=>'PT 검사권','unit_price'=>9900,'quantity'=>1,'credit_qty'=>1,'valid_days'=>365]);
    $o->payments()->create(['provider'=>'fake','amount'=>9900,'status'=>'ready']);
    expect($o->items)->toHaveCount(1);
    expect($o->payments)->toHaveCount(1);
    expect($o->user->id)->toBe($u->id);
});

test('voucher belongs to user and test with casts', function () {
    $u = User::factory()->create();
    $t = Test::create(['code'=>'PT3','room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $v = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'purchase','status'=>'active','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    expect($v->status)->toBe('active');
    expect($v->user->id)->toBe($u->id);
    expect($v->test->id)->toBe($t->id);
});
