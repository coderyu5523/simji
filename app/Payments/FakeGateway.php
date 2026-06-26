<?php
namespace App\Payments;
use App\Models\Order;

class FakeGateway implements PaymentGateway
{
    public function begin(Order $order): array
    {
        return [
            'order_no' => $order->order_no,
            'amount' => $order->total_amount,
            'return_url' => route('payment.return'),
            'provider' => 'fake',
        ];
    }

    public function approve(array $return): PaymentResult
    {
        $ok = ($return['result'] ?? 'success') === 'success';
        $orderNo = (string) ($return['order_no'] ?? '');
        $amount = (int) ($return['amount'] ?? 0);
        return new PaymentResult(
            success: $ok,
            orderNo: $orderNo,
            amount: $amount,
            tid: $ok ? 'FAKE-'.$orderNo : null,
            method: 'card',
            raw: $return,
        );
    }
}
