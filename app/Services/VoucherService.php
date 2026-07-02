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

    /**
     * 검사권을 대상자 전달용 링크로 발급한다.
     * - 유료 검사: 보유 active·미발급(access_token 없음) 검사권에 토큰 부여. 부족하면 예외.
     * - 무료 검사: qty개 새 검사권 생성(source=issued_free) + 토큰.
     * @return \Illuminate\Support\Collection<int,Voucher>
     */
    public function issueLinks(User $user, Test $test, int $qty)
    {
        $qty = max(1, $qty);

        return DB::transaction(function () use ($user, $test, $qty) {
            $issued = collect();

            if ($test->isPaid()) {
                $available = $this->activeQuery($user, $test)
                    ->whereNull('access_token')
                    ->orderBy('issued_at')->orderBy('id')
                    ->lockForUpdate()->take($qty)->get();

                if ($available->count() < $qty) {
                    throw new \RuntimeException("보유 검사권이 부족합니다. (필요 {$qty}개, 보유 {$available->count()}개)");
                }

                foreach ($available as $voucher) {
                    $voucher->update([
                        'access_token' => $this->newToken(),
                        'result_visible' => true,
                        'assigned_at' => now(),
                    ]);
                    $issued->push($voucher);
                }
            } else {
                for ($i = 0; $i < $qty; $i++) {
                    $issued->push(Voucher::create([
                        'user_id' => $user->id,
                        'test_id' => $test->id,
                        'source' => 'issued_free',
                        'status' => 'active',
                        'access_token' => $this->newToken(),
                        'result_visible' => true,
                        'issued_at' => now(),
                        'assigned_at' => now(),
                    ]));
                }
            }

            return $issued;
        });
    }

    /** 링크 응시 제출 시 해당 검사권을 소비 처리한다. */
    public function markUsedByAttempt(Voucher $voucher, TestAttempt $attempt): void
    {
        $voucher->update([
            'status' => 'used',
            'used_at' => now(),
            'used_attempt_id' => $attempt->id,
        ]);
    }

    private function newToken(): string
    {
        do {
            $token = \Illuminate\Support\Str::random(40);
        } while (Voucher::where('access_token', $token)->exists());
        return $token;
    }

    public function firstActive(User $user, Test $test): ?Voucher
    {
        return $this->activeQuery($user, $test)
            ->orderByRaw("CASE source WHEN 'free' THEN 0 WHEN 'referral' THEN 1 ELSE 2 END")
            ->orderBy('issued_at')
            ->orderBy('id')
            ->first();
    }

    public function availableCount(User $user, Test $test): int
    {
        return $this->activeQuery($user, $test)->count();
    }

    public function consume(User $user, Test $test, TestAttempt $attempt): Voucher
    {
        return DB::transaction(function () use ($user, $test, $attempt) {
            $voucher = $this->activeQuery($user, $test)
                ->orderByRaw("CASE source WHEN 'free' THEN 0 WHEN 'referral' THEN 1 ELSE 2 END")
                ->orderBy('issued_at')
                ->orderBy('id')
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
