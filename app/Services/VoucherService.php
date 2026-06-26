<?php
namespace App\Services;

use App\Models\{Order, Test, TestAttempt, User, Voucher};
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function issueForOrder(Order $order): void
    {
        if ($order->status !== 'paid') return;
        DB::transaction(function () use ($order) {
            foreach ($order->items()->get() as $item) {
                // 멱등: 이미 이 항목으로 발급된 검사권이 있으면 skip
                if (Voucher::where('order_item_id', $item->id)->exists()) continue;
                $count = max(1, $item->credit_qty) * max(1, $item->quantity);
                for ($i = 0; $i < $count; $i++) {
                    Voucher::create([
                        'user_id' => $order->user_id,
                        'test_id' => $item->test_id,
                        'order_item_id' => $item->id,
                        'source' => 'purchase',
                        'status' => 'active',
                        'issued_at' => now(),
                        'expires_at' => now()->addDays(max(1, $item->valid_days)),
                    ]);
                }
            }
        });
    }

    public function firstActive(User $user, Test $test): ?Voucher
    {
        return $this->activeQuery($user, $test)->orderBy('issued_at')->orderBy('id')->first();
    }

    public function availableCount(User $user, Test $test): int
    {
        return $this->activeQuery($user, $test)->count();
    }

    public function consume(User $user, Test $test, TestAttempt $attempt): Voucher
    {
        return DB::transaction(function () use ($user, $test, $attempt) {
            $voucher = $this->activeQuery($user, $test)
                ->orderBy('issued_at')->orderBy('id')
                ->lockForUpdate()->first();
            if (!$voucher) {
                throw new \RuntimeException('사용 가능한 검사권이 없습니다.');
            }
            $voucher->update(['status' => 'used', 'used_at' => now(), 'used_attempt_id' => $attempt->id]);
            $attempt->update(['voucher_id' => $voucher->id]);
            return $voucher;
        });
    }

    private function activeQuery(User $user, Test $test)
    {
        return Voucher::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
