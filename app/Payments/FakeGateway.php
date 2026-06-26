<?php
namespace App\Payments;
use App\Models\Order;

class FakeGateway implements PaymentGateway
{
    public function begin(Order $order): array
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('FakeGateway는 운영 환경에서 사용할 수 없습니다. PG_DRIVER=inicis 설정 필요.');
        }
        return [
            'order_no' => $order->order_no,
            'amount' => $order->total_amount,
            'return_url' => route('payment.return'),
            'provider' => 'fake',
        ];
    }

    public function approve(array $return): PaymentResult
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('FakeGateway는 운영 환경에서 사용할 수 없습니다. PG_DRIVER=inicis 설정 필요.');
        }
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
