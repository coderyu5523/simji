<?php
namespace App\Http\Controllers;
use App\Models\TestAttempt;
use Illuminate\Http\Request;

class MyTestController extends Controller
{
    public function index(Request $request)
    {
        // 게스트 이력 지원: auth 미들웨어로 막지 말 것 (guest_token 기반 조회)
        $query = TestAttempt::where('status', 'submitted')->with('test', 'result')->latest('submitted_at');
        if (auth()->check()) $query->where('user_id', auth()->id());
        else $query->where('guest_token', $request->session()->get('guest_token'));
        return view('my.index', ['attempts' => $query->get()]);
    }
}
