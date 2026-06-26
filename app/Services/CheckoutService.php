<?php
namespace App\Services;

use App\Models\{Order, Product, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function createOrder(User $user, Product $product, int $qty = 1): Order
    {
        $qty = max(1, $qty);
        return DB::transaction(function () use ($user, $product, $qty) {
            $order = Order::create([
                'order_no' => 'S'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $product->price * $qty,
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'test_id' => $product->test_id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $qty,
                'credit_qty' => $product->credit_qty,
                'valid_days' => $product->valid_days,
            ]);
            return $order;
        });
    }
}
