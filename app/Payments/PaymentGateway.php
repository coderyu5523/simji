<?php
namespace App\Payments;
use App\Models\Order;

interface PaymentGateway
{
    /** 결제창 호출에 필요한 파라미터(주문번호·금액·return_url 등) */
    public function begin(Order $order): array;

    /** PG 인증결과 배열을 받아 승인 처리하고 결과 반환 */
    public function approve(array $return): PaymentResult;
}
