<?php
use App\Models\{Test, Product, Order, User, Voucher, Payment};
use App\Services\CheckoutService;

function paidOrderViaCheckout(User $u): array {
    $t = Test::create(['code'=>'PR','room'=>'univ','title_easy'=>'검사','title_pro'=>'PR','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $p = Product::create(['test_id'=>$t->id,'name'=>'PR 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    $o = app(CheckoutService::class)->createOrder($u, $p);
    return [$t,$p,$o];
}

test('successful return marks order paid and issues voucher', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $this->actingAs($u)->post('/payment/return', ['order_no'=>$o->order_no,'amount'=>$o->total_amount,'result'=>'success'])
        ->assertRedirect(route('payment.complete', $o->id));
    expect($o->fresh()->status)->toBe('paid');
    expect(Payment::where('order_id',$o->id)->where('status','paid')->count())->toBe(1);
    expect(Voucher::where('user_id',$u->id)->where('test_id',$t->id)->where('status','active')->count())->toBe(1);
});

test('duplicate return does not double-issue vouchers', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $payload = ['order_no'=>$o->order_no,'amount'=>$o->total_amount,'result'=>'success'];
    $this->actingAs($u)->post('/payment/return', $payload);
    $this->actingAs($u)->post('/payment/return', $payload)->assertRedirect(route('payment.complete', $o->id));
    expect(Voucher::where('user_id',$u->id)->count())->toBe(1);
});

test('amount mismatch fails the order', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $this->actingAs($u)->post('/payment/return', ['order_no'=>$o->order_no,'amount'=>100,'result'=>'success'])
        ->assertRedirect(route('payment.fail'));
    expect($o->fresh()->status)->toBe('failed');
    expect(Voucher::count())->toBe(0);
});

test('pg failure fails the order', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $this->actingAs($u)->post('/payment/return', ['order_no'=>$o->order_no,'amount'=>$o->total_amount,'result'=>'fail'])
        ->assertRedirect(route('payment.fail'));
    expect($o->fresh()->status)->toBe('failed');
});
