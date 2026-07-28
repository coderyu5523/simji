<?php
namespace App\Http\Controllers\OyMsi;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\Voucher;
use App\Services\OyMsi\AgeGate;
use Illuminate\Http\Request;

/**
 * 연령 게이트 — 동의·검사 시작보다 앞선 단계.
 *
 * 만 나이 정수만 세션에 담고(개인: oymsi_age:{code} / 링크: oymsi_age_token:{token})
 * 생년월일은 저장하지 않는다.
 */
class AgeGateController extends Controller
{
    public function __construct(private AgeGate $ageGate) {}

    public function form(string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();

        return view('oymsi.age-gate', [
            'test' => $test,
            'action' => route('oymsi.age.submit', $code),
        ]);
    }

    public function submit(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $age = $this->validateAge($request);

        if ($blocked = $this->ageGate->blockReason($test, $age, guardianConfirmed: false, isLink: false)) {
            // 차단이면 세션에 나이를 남기지 않는다 — 남기면 뒤로 가서 동의로 직행할 수 있다.
            $request->session()->forget('oymsi_age:'.$code);

            return response()->view('oymsi.age-blocked', [
                'test' => $test, 'reason' => $blocked, 'age' => $age,
            ]);
        }

        $request->session()->put('oymsi_age:'.$code, $age);

        return redirect()->route('assessment.consent', $code);
    }

    public function linkForm(string $token)
    {
        $voucher = Voucher::where('access_token', $token)->with('test')->firstOrFail();

        return view('oymsi.age-gate', [
            'test' => $voucher->test,
            'action' => route('link.age.submit', $token),
        ]);
    }

    public function linkSubmit(Request $request, string $token)
    {
        $voucher = Voucher::where('access_token', $token)->with('test')->firstOrFail();
        $test = $voucher->test;
        $age = $this->validateAge($request);

        $confirmed = $voucher->guardian_consent_confirmed_at !== null;

        if ($blocked = $this->ageGate->blockReason($test, $age, guardianConfirmed: $confirmed, isLink: true)) {
            $request->session()->forget('oymsi_age_token:'.$token);

            return response()->view('oymsi.age-blocked', [
                'test' => $test, 'reason' => $blocked, 'age' => $age,
            ]);
        }

        $request->session()->put('oymsi_age_token:'.$token, $age);

        return redirect()->route('link.landing', $token);
    }

    /** 만 나이. 생년월일은 반환하지 않는다 — 저장 금지. */
    public function calculateAge(string $birthdate): int
    {
        return $this->ageGate->calculateAge($birthdate);
    }

    private function validateAge(Request $request): int
    {
        $data = $request->validate([
            'birthdate' => ['required', 'date', 'before:today', 'after:'.now()->subYears(120)->format('Y-m-d')],
        ]);

        return $this->calculateAge($data['birthdate']);
    }
}
