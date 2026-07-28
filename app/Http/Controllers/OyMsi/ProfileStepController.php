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
        $this->attemptOrFail($request, $code);

        return view('oymsi.profile-step', ['test' => $test]);
    }

    public function submit(Request $request, string $code)
    {
        $attempt = $this->attemptOrFail($request, $code);

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

    private function attemptOrFail(Request $request, string $code): TestAttempt
    {
        $id = $request->session()->get('oymsi_attempt:'.$code);
        $attempt = $id ? TestAttempt::find($id) : null;
        abort_unless($attempt, 403, '검사 전 동의가 확인되지 않았습니다.');
        return $attempt;
    }
}
