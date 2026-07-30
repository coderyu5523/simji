<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'members'  => User::count(),
            'tests'    => Test::count(),
            'orders'   => Order::count(),
            'revenue'  => (int) Order::where('status', 'paid')->sum('total_amount'),
        ];
        $recentMembers = User::latest()->take(5)->get();
        $recentOrders  = Order::with('user')->latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentMembers', 'recentOrders'));
    }

    public function members()
    {
        $members = User::latest()->get();
        return view('admin.members', compact('members'));
    }

    public function orders()
    {
        $orders = Order::with('user')->latest()->get();
        return view('admin.orders', compact('orders'));
    }

    public function tests()
    {
        $tests = Test::orderBy('room')->get();
        $attemptCounts = TestAttempt::selectRaw('test_id, count(*) as c')
            ->groupBy('test_id')->pluck('c', 'test_id');

        return view('admin.tests', compact('tests', 'attemptCounts'));
    }
}
