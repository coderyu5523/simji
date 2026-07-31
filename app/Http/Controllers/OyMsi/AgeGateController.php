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
                // 잘못 입력했을 때 주소를 직접 치지 않고 되돌아올 수 있게 한다.
                // 세션에 나이를 남기지 않았으므로 재입력해도 게이트는 그대로 다시 판정한다.
                'retryUrl' => route('oymsi.age.form', $code),
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
                // 링크 응시자는 개인 경로가 아니라 자기 링크의 연령 입력으로 돌아가야 한다.
                'retryUrl' => route('link.age.form', $token),
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

    /**
     * 폼은 년·월·일을 세 칸으로 나눠 받는다(달력 피커의 연도 스크롤을 없애려고).
     * 검증은 조립한 날짜 하나로 한다 — 칸마다 따로 보면 2월 30일 같은 조합 오류를
     * 아무도 잡지 못한다. 오류 메시지는 전부 birthdate 키로 모은다(화면 표시 위치 고정).
     */
    private function validateAge(Request $request): int
    {
        $parts = [];
        foreach (['birth_year' => 4, 'birth_month' => 2, 'birth_day' => 2] as $field => $maxLen) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw === '' || !preg_match('/^\d{1,'.$maxLen.'}$/', $raw)) {
                $this->failBirthdate('생년월일을 숫자로 모두 입력해 주세요.');
            }
            $parts[$field] = (int) $raw;
        }

        if (!checkdate($parts['birth_month'], $parts['birth_day'], $parts['birth_year'])) {
            $this->failBirthdate('없는 날짜입니다. 생년월일을 다시 확인해 주세요.');
        }

        $birthdate = sprintf('%04d-%02d-%02d', $parts['birth_year'], $parts['birth_month'], $parts['birth_day']);
        $parsed = \Carbon\Carbon::parse($birthdate)->startOfDay();

        // 예전 date 규칙(before:today, after:120년 전)과 같은 범위를 유지한다.
        if ($parsed->greaterThanOrEqualTo(now()->startOfDay())) {
            $this->failBirthdate('오늘보다 뒤의 날짜는 입력할 수 없습니다.');
        }
        if ($parsed->lessThanOrEqualTo(now()->subYears(120)->startOfDay())) {
            $this->failBirthdate('생년월일을 다시 확인해 주세요.');
        }

        return $this->calculateAge($birthdate);
    }

    private function failBirthdate(string $message): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages(['birthdate' => $message]);
    }
}
