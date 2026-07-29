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
        $ownedCount = 0;

        if (auth()->check()) {
            // 발급 명부: 내가 링크로 발급한 검사권 + 응시 결과
            $issued = Voucher::with(['test', 'attempt.result'])
                ->withCount('attempts') // 보호자 동의 확인 버튼 판정의 N+1 방지
                ->where('user_id', auth()->id())
                ->whereNotNull('access_token')
                ->latest('assigned_at')
                ->get();

            // 보유 검사권(미발급) 수 — 발급은 심리검사 상세에서 진행
            $ownedCount = Voucher::where('user_id', auth()->id())
                ->where('status', 'active')
                ->whereNull('access_token')
                ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                ->count();
        }

        return view('my.index', [
            'attempts' => $query->get(),
            'issued' => $issued,
            'ownedCount' => $ownedCount,
            // 보호자 동의 확인 버튼 노출 판정 — 컨트롤러 인가와 같은 규칙을 쓴다.
            'guardianRules' => app(\App\Services\OyMsi\GuardianConfirmation::class),
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

        // 발급 후 내 검사함(명부)으로 이동 — 생성된 링크를 바로 복사·관리
        return redirect()->route('my.index')->with('status', "{$test->title_easy} 검사권 {$data['qty']}개를 발급했습니다. 아래에서 링크를 복사해 전달하세요.");
    }

    public function toggleVisibility(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->user_id === auth()->id(), 403);
        $voucher->update(['result_visible' => ! $voucher->result_visible]);
        return back();
    }
}
