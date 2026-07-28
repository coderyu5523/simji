<?php
namespace App\Http\Controllers\OyMsi;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Http\Request;

class ProfileStepController extends Controller
{
    public const GENDERS = ['male', 'female', 'no_answer'];

    public function form(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $this->attemptOrFail($request, $test);

        return view('oymsi.profile-step', ['test' => $test]);
    }

    public function submit(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $attempt = $this->attemptOrFail($request, $test);

        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:'.implode(',', self::GENDERS)],
        ]);

        $attempt->update([
            'nickname' => $data['nickname'],
            'gender' => $data['gender'],
            'age_at_test' => $attempt->age_at_test ?? $request->session()->get('oymsi_age:'.$code),
        ]);

        return redirect()->route('assessment.start', $code);
    }

    // 형제 코드(AssessmentController::start():117-122)와 동일하게 소유권·검사종류 일치를 확인한다.
    // oymsi_attempt:{code} 세션 키는 앱 전역에서 한 번도 삭제되지 않으므로(forget/flush 는
    // AgeGateController 의 oymsi_age* 두 곳뿐), 이 검사가 없으면 세션에 남은 attempt id 로
    // 남의 attempt·다른 검사의 attempt 를 조작할 수 있다 — fail closed.
    private function attemptOrFail(Request $request, Test $test): TestAttempt
    {
        $id = $request->session()->get('oymsi_attempt:'.$test->code);
        $attempt = $id ? TestAttempt::find($id) : null;
        abort_unless(
            $attempt && $attempt->test_id === $test->id && $attempt->isOwnedBy($request),
            403,
            '검사 전 동의가 확인되지 않았습니다.'
        );
        // 제출 완료된 attempt 의 식별 라벨(nickname·gender)은 보호자 공유 리포트·기관 명부에 이미
        // 반영됐을 수 있다 — 종결된 attempt 의 사후 변경을 막는다(final-review I1).
        abort_if($attempt->status === 'submitted', 409, '이미 제출된 검사입니다.');
        return $attempt;
    }
}
