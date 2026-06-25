<?php
namespace App\Http\Controllers;
use App\Models\TestAttempt;
use Illuminate\Http\Request;

class MyTestController extends Controller
{
    public function index(Request $request)
    {
        $query = TestAttempt::where('status', 'submitted')->with('test', 'result')->latest('submitted_at');
        if (auth()->check()) $query->where('user_id', auth()->id());
        else $query->where('guest_token', $request->session()->get('guest_token'));
        return view('my.index', ['attempts' => $query->get()]);
    }
}
