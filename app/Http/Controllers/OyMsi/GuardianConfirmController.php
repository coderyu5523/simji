<?php
namespace App\Http\Controllers\OyMsi;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\OyMsi\GuardianConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 만 14세 미만 응시자의 법정대리인 동의를 담당자가 오프라인으로 확인했음을 기록한다.
 *
 * 이 기록이 없으면 AgeGate 가 링크 경로에서 만 14세 미만을 차단한다(GUARDIAN_LINK).
 * 규칙 판정은 GuardianConfirmation 한 곳에서만 한다.
 */
class GuardianConfirmController extends Controller
{
    public function __construct(private GuardianConfirmation $rules) {}

    public function confirm(Request $request, Voucher $voucher)
    {
        $this->authorizeChange($voucher);
        $request->validate(['confirm' => 'accepted']);

        // 최초 확인 시각이 증거다. 재제출로 시각이 밀리면 "언제 동의를 받았는가"의 기록이 훼손된다.
        if ($voucher->guardian_consent_confirmed_at !== null) {
            return back()->with('status', '이미 확인되어 있습니다.');
        }

        $voucher->update([
            'guardian_consent_confirmed_at' => now(),
            'guardian_consent_confirmed_by' => auth()->id(),
        ]);

        return back()->with('status', '법정대리인 동의 확인을 기록했습니다.');
    }

    public function release(Request $request, Voucher $voucher)
    {
        $this->authorizeChange($voucher);

        // 해제를 누르는 순간 응시자가 링크를 여는 경우가 있다. 잠근 뒤 응시 여부를 다시 본다.
        DB::transaction(function () use ($voucher) {
            $fresh = Voucher::whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();
            abort_if($this->rules->hasStarted($fresh), 403, '응시가 시작된 검사권은 변경할 수 없습니다.');

            if ($fresh->guardian_consent_confirmed_at === null) return;

            // 두 컬럼을 함께 비운다 — _by 만 남는 반쪽 상태를 만들지 않는다.
            $fresh->update([
                'guardian_consent_confirmed_at' => null,
                'guardian_consent_confirmed_by' => null,
            ]);
        });

        return back()->with('status', '법정대리인 동의 확인을 해제했습니다.');
    }

    /** 403 은 규칙 위반일 때만이다. 재요청(이미 확인됨·미확인 해제)은 각 액션에서 no-op 으로 처리한다. */
    private function authorizeChange(Voucher $voucher): void
    {
        $voucher->loadMissing('test');
        abort_unless($this->rules->canConfirm($voucher, auth()->user()), 403);
    }
}
