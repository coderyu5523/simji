<?php
namespace App\Payments;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $orderNo,
        public readonly int $amount,
        public readonly ?string $tid = null,
        public readonly ?string $method = null,
        public readonly array $raw = [],
    ) {}
}
