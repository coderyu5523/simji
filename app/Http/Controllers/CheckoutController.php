<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Payments\PaymentGateway;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show(Product $product)
    {
        abort_unless($product->status === 'active', 404);
        return view('checkout.show', ['product' => $product, 'order' => null, 'pay' => null]);
    }

    public function start(Request $request, Product $product, CheckoutService $checkout, PaymentGateway $gateway)
    {
        abort_unless($product->status === 'active', 404);
        $order = $checkout->createOrder($request->user(), $product);
        $pay = $gateway->begin($order);
        return view('checkout.show', ['product' => $product, 'order' => $order, 'pay' => $pay]);
    }
}
