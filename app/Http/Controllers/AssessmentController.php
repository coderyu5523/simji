<?php
namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ScoringService;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function __construct(private \App\Services\AssessmentStarter $starter) {}

    /**
     * 연령 확인이 필요한 검사인데 세션에 만 나이가 없으면 연령 게이트로 보낸다.
     * "나이를 모르면 통과"가 아니라 "나이를 모르면 앞으로 못 간다" — fail closed.
     */
    private function ageGateRedirect(Request $request, Test $test): ?\Illuminate\Http\RedirectResponse
    {
        if ($test->requiresAgeVerification() && !$request->session()->has('oymsi_age:'.$test->code)) {
            return redirect()->route('oymsi.age.form', $test->code);
        }
        return null;
    }

    public function consent(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        if ($redirect = $this->ageGateRedirect($request, $test)) return $redirect;

        return view('assessment.consent', compact('test'));
    }

    public function agree(Request $request, string $code, \App\Services\OyMsi\ConsentGate $gate)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $rules = ['agree' => 'accepted'];
        if ($test->requires_guardian_consent) $rules['guardian_agree'] = 'accepted';
        $request->validate($rules);

        // 동의 폼을 건너뛰고 agree 를 직접 POST 해도 나이 없이는 확정하지 않는다.
        // (검증보다 뒤에 둔다 — 동의 체크 누락은 그대로 검증 오류로 돌려줘야 한다.)
        if ($redirect = $this->ageGateRedirect($request, $test)) return $redirect;

        $request->session()->put('consent_ok:'.$code, true);

        // consent_required 검사는 이 시점에 attempt 를 만들고 동의를 영속화한다.
        // (세션 플래그만으로는 start() 직접 호출로 우회 가능했다 — 2026-06-26 spec 지적)
        if ($test->consent_required) {
            // 동의 폼 재제출(뒤로가기·새로고침) 시 아직 시작하지 않은 attempt 는 재사용한다.
            // 재사용하지 않으면 매 POST 마다 고아 attempt 와 sensitive 동의행이 쌓인다 —
            // 동의 기록은 법적 증거라 구분 불가한 중복행이 특히 나쁘다.
            $age = $request->session()->get('oymsi_age:'.$code);
            $existingId = $request->session()->get('oymsi_attempt:'.$code);
            $existing = $existingId ? TestAttempt::find($existingId) : null;
            $reusable = $existing && $existing->test_id === $test->id && $existing->status === 'created';

            if ($reusable) {
                // 재사용 분기에서도 나이를 다시 채운다. 연령 게이트 이전에 만들어져 age_at_test 가
                // null 로 굳은 attempt 는 ConsentGate 의 fail closed 때문에 영구 403 이 되는데,
                // 이 갱신이 그 유일한 해소 경로다. (나이를 지우지는 않는다 — null 로 덮어쓰기 금지.)
                if ($age !== null && $existing->age_at_test !== $age) {
                    $existing->update(['age_at_test' => $age]);
                }
            } else {
                $attempt = TestAttempt::create(array_merge(
                    $this->starter->actorColumns($request),
                    [
                        'test_id' => $test->id,
                        'status' => 'created',
                        'assessment_version' => $test->assessment_version,
                        'scoring_version' => $test->scoringRule?->version,
                        'age_at_test' => $age,
                    ]
                ));
                $gate->record($attempt, \App\Services\OyMsi\ConsentGate::SENSITIVE, 'youth', auth()->id());
                $request->session()->put('oymsi_attempt:'.$code, $attempt->id);
            }
        }

        // 기본정보 단계가 있는 검사(consent_required)는 그리로 보내고, 없는 검사는 동의가 곧
        // 시작이다. 안내(intro) 화면은 거치지 않는다 — 그 경유가 같은 화면을 두 번 지나게 했다.
        if ($test->consent_required) {
            return redirect()->route('oymsi.profile.form', $code);
        }

        return $this->starter->start($request, $test);
    }

    public function start(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();

        return $this->starter->start($request, $test);
    }

    private function authorizeAttempt(Request $request, TestAttempt $attempt): void
    {
        abort_unless($attempt->isOwnedBy($request), 403);
    }

    public function take(Request $request, string $code, TestAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        app(\App\Services\OyMsi\ConsentGate::class)->assertSatisfied($attempt);
        // 이미 끝낸 검사의 문항 화면을 다시 열면(뒤로가기 등) 결과로 보낸다.
        // 다시 답할 수 있는 폼을 보여주면 제출 순간 409 를 마주하게 된다.
        if ($attempt->status === 'submitted') {
            return redirect()->route('result.show', $attempt->id);
        }
        $attempt->load('test.items');
        // start() 를 거치지 않고 take() 를 직접 호출해 기본정보(닉네임) 단계를 건너뛰는 것을 막는다.
        if ($attempt->test->consent_required && !$attempt->nickname) {
            return redirect()->route('oymsi.profile.form', $code);
        }
        return view('assessment.take', ['test' => $attempt->test, 'attempt' => $attempt]);
    }

    public function submit(Request $request, string $code, TestAttempt $attempt, ScoringService $scoring)
    {
        $this->authorizeAttempt($request, $attempt);
        app(\App\Services\OyMsi\ConsentGate::class)->assertSatisfied($attempt);
        // 이중 제출(버튼 두 번 클릭·뒤로가기 후 재제출)은 오류가 아니라 "이미 끝난 일"이다.
        // 재채점·응답 덮어쓰기는 하지 않고 결과로 보낸다 — 기존 409 가드가 지키던
        // "다시 채점하지 않는다"는 그대로다.
        if ($attempt->status === 'submitted') {
            return redirect()->route('result.show', $attempt->id);
        }
        // submit() 을 직접 호출해 기본정보 단계를 건너뛰고 채점까지 끝내는 것을 막는다.
        abort_if($attempt->test->consent_required && !$attempt->nickname, 403, '검사 전 기본정보가 확인되지 않았습니다.');

        $itemsById = $attempt->test->items()->get()->keyBy('id');
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => [new \App\Rules\AnswerValue($itemsById)],
        ]);

        foreach ($request->input('answers') as $itemId => $value) {
            if (!isset($itemsById[(int) $itemId])) continue;
            $prefersNot = $value === \App\Rules\AnswerValue::PREFER_NOT;
            $attempt->answers()->updateOrCreate(
                ['test_item_id' => (int) $itemId],
                [
                    'value' => $prefersNot ? null : (int) $value,
                    'missing_code' => $prefersNot ? \App\Rules\AnswerValue::PREFER_NOT : null,
                ]
            );
        }

        $attempt->update(['status' => 'submitted', 'submitted_at' => now()]);
        $scoring->score($attempt);
        return redirect()->route('result.show', $attempt->id);
    }
}
