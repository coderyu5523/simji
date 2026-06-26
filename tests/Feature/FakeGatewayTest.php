<?php
use App\Models\{Order, User};
use App\Payments\{PaymentGateway, PaymentResult, FakeGateway};

test('container resolves PaymentGateway to FakeGateway in tests', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakeGateway::class);
});

test('begin returns order_no and amount and return_url', function () {
    $u = User::factory()->create();
    $o = Order::create(['order_no'=>'S-XYZ','user_id'=>$u->id,'status'=>'pending','total_amount'=>9900]);
    $params = app(PaymentGateway::class)->begin($o);
    expect($params['order_no'])->toBe('S-XYZ');
    expect($params['amount'])->toBe(9900);
    expect($params)->toHaveKey('return_url');
});

test('approve success returns PaymentResult with tid', function () {
    $r = app(PaymentGateway::class)->approve(['order_no'=>'S-XYZ','amount'=>9900,'result'=>'success']);
    expect($r)->toBeInstanceOf(PaymentResult::class);
    expect($r->success)->toBeTrue();
    expect($r->orderNo)->toBe('S-XYZ');
    expect($r->amount)->toBe(9900);
    expect($r->tid)->toBe('FAKE-S-XYZ');
});

test('approve failure returns unsuccessful result', function () {
    $r = app(PaymentGateway::class)->approve(['order_no'=>'S-XYZ','amount'=>9900,'result'=>'fail']);
    expect($r->success)->toBeFalse();
});
