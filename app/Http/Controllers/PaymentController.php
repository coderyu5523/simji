<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function return(Request $request, PaymentGateway $gateway, VoucherService $vouchers)
    {
        $result = $gateway->approve($request->all());
        $order = Order::where('order_no', $result->orderNo)->first();
        if (!$order) abort(404);

        // 멱등: 이미 결제 완료된 주문이면 그대로 완료 페이지
        if ($order->status === 'paid') {
            return redirect()->route('payment.complete', $order->id);
        }

        // PG 실패 또는 금액 위변조 → 실패 처리
        if (!$result->success || $result->amount !== $order->total_amount) {
            DB::transaction(function () use ($order, $result) {
                $order->update(['status' => 'failed']);
                $order->payments()->create([
                    'provider' => $result->raw['provider'] ?? config('services.pg.driver'),
                    'method' => $result->method,
                    'amount' => $result->amount,
                    'status' => 'failed',
                    'raw_response' => $result->raw,
                ]);
            });
            return redirect()->route('payment.fail');
        }

        DB::transaction(function () use ($order, $result, $vouchers) {
            $order->payments()->create([
                'provider' => $result->raw['provider'] ?? config('services.pg.driver'),
                'method' => $result->method,
                'pg_tid' => $result->tid,
                'amount' => $result->amount,
                'status' => 'paid',
                'paid_at' => now(),
                'raw_response' => $result->raw,
            ]);
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $vouchers->issueForOrder($order);
        });

        return redirect()->route('payment.complete', $order->id);
    }

    public function complete(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $order->load('items');
        return view('payment.complete', ['order' => $order]);
    }

    public function fail()
    {
        return view('payment.fail');
    }
}
