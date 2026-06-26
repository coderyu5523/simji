<?php
use App\Models\{Test, Product, Order, User, TestAttempt, Voucher};
use App\Services\VoucherService;

function paidTest(int $price=9900, int $qty=1, int $credit=1): Test {
    $t = Test::create(['code'=>'VS'.$qty.$credit.$price,'room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Product::create(['test_id'=>$t->id,'name'=>'권','price'=>$price,'credit_qty'=>$credit,'valid_days'=>365,'status'=>'active']);
    return $t;
}
function paidOrder(User $u, Test $t, int $qty=1, int $credit=1): Order {
    $o = Order::create(['order_no'=>'S-'.uniqid(),'user_id'=>$u->id,'status'=>'paid','total_amount'=>9900]);
    $o->items()->create(['test_id'=>$t->id,'product_name'=>'권','unit_price'=>9900,'quantity'=>$qty,'credit_qty'=>$credit,'valid_days'=>365]);
    return $o;
}

test('issueForOrder issues credit_qty*quantity vouchers and is idempotent', function () {
    $u = User::factory()->create(); $t = paidTest();
    $o = paidOrder($u, $t, qty:2, credit:3); // 2*3 = 6장
    $svc = app(VoucherService::class);
    $svc->issueForOrder($o);
    expect(Voucher::where('user_id',$u->id)->count())->toBe(6);
    $svc->issueForOrder($o); // 재호출 — 이중발급 없어야
    expect(Voucher::where('user_id',$u->id)->count())->toBe(6);
});

test('issueForOrder skips when order not paid', function () {
    $u = User::factory()->create(); $t = paidTest();
    $o = paidOrder($u, $t); $o->update(['status'=>'pending']);
    app(VoucherService::class)->issueForOrder($o);
    expect(Voucher::count())->toBe(0);
});

test('consume picks oldest active first (FIFO) and links attempt', function () {
    $u = User::factory()->create(); $t = paidTest();
    $old = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'free','status'=>'active','issued_at'=>now()->subDays(2),'expires_at'=>now()->addYear()]);
    $new = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'purchase','status'=>'active','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $a = TestAttempt::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'in_progress','started_at'=>now()]);
    $used = app(VoucherService::class)->consume($u, $t, $a);
    expect($used->id)->toBe($old->id);
    expect($used->fresh()->status)->toBe('used');
    expect($used->fresh()->used_attempt_id)->toBe($a->id);
    expect($a->fresh()->voucher_id)->toBe($old->id);
});

test('consume ignores expired vouchers', function () {
    $u = User::factory()->create(); $t = paidTest();
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'purchase','status'=>'active','issued_at'=>now()->subDays(400),'expires_at'=>now()->subDay()]);
    $a = TestAttempt::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'in_progress','started_at'=>now()]);
    expect(fn() => app(VoucherService::class)->consume($u, $t, $a))->toThrow(RuntimeException::class);
});

test('consume prefers free/referral over purchase even if purchase is older', function () {
    $u = User::factory()->create(); $t = paidTest();
    $paidOld = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'purchase','status'=>'active','issued_at'=>now()->subDays(5),'expires_at'=>now()->addYear()]);
    $freeNew = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'free','status'=>'active','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $a = TestAttempt::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'in_progress','started_at'=>now()]);
    $used = app(\App\Services\VoucherService::class)->consume($u, $t, $a);
    expect($used->id)->toBe($freeNew->id); // 무료가 더 늦게 발급됐어도 먼저 소비
});

test('availableCount counts only active non-expired', function () {
    $u = User::factory()->create(); $t = paidTest();
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'used','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    expect(app(VoucherService::class)->availableCount($u, $t))->toBe(1);
});
