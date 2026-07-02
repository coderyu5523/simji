<?php
namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class MyTestController extends Controller
{
    public function index(Request $request)
    {
        // 내가 응시한 검사 이력
        $query = TestAttempt::where('status', 'submitted')->with('test', 'result')->latest('submitted_at');
        if (auth()->check()) $query->where('user_id', auth()->id());
        else $query->where('guest_token', $request->session()->get('guest_token'));

        $issued = collect();
        $vouchers = collect();
        $issuableTests = collect();

        if (auth()->check()) {
            // 발급 명부: 내가 링크로 발급한 검사권 + 응시 결과
            $issued = Voucher::with(['test', 'attempt.result'])
                ->where('user_id', auth()->id())
                ->whereNotNull('access_token')
                ->latest('assigned_at')
                ->get();

            // 보유 검사권(미발급) — 유료 발급 가능 수량 계산용
            $vouchers = Voucher::with('test')
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->whereNull('access_token')
                ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                ->orderBy('issued_at')
                ->get();

            // 발급 가능한 검사 목록 (무료=항상, 유료=보유 검사권 수)
            $available = $vouchers->groupBy('test_id')->map->count();
            $issuableTests = Test::where('status', 'active')->orderBy('title_easy')->get()
                ->map(function ($t) use ($available) {
                    $t->is_paid = $t->isPaid();
                    $t->available_credits = $available[$t->id] ?? 0;
                    return $t;
                });
        }

        return view('my.index', [
            'attempts' => $query->get(),
            'vouchers' => $vouchers,
            'issued' => $issued,
            'issuableTests' => $issuableTests,
        ]);
    }

    public function issue(Request $request, VoucherService $vouchers)
    {
        $data = $request->validate([
            'test_id' => 'required|exists:tests,id',
            'qty' => 'required|integer|min:1|max:100',
        ]);
        $test = Test::findOrFail($data['test_id']);

        try {
            $vouchers->issueLinks(auth()->user(), $test, (int) $data['qty']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['issue' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "{$test->title_easy} 검사권 {$data['qty']}개를 발급했습니다.");
    }

    public function toggleVisibility(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->user_id === auth()->id(), 403);
        $voucher->update(['result_visible' => ! $voucher->result_visible]);
        return back();
    }
}
