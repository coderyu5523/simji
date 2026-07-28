<?php
namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ScoringService;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    private function actorColumns(Request $request): array
    {
        if (auth()->check()) return ['user_id' => auth()->id(), 'guest_token' => null];
        if (!$request->session()->get('guest_token')) {
            $request->session()->put('guest_token', (string) \Illuminate\Support\Str::uuid());
        }
        return ['user_id' => null, 'guest_token' => $request->session()->get('guest_token')];
    }

    public function consent(string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        return view('assessment.consent', compact('test'));
    }

    public function agree(Request $request, string $code, \App\Services\OyMsi\ConsentGate $gate)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $rules = ['agree' => 'accepted'];
        if ($test->requires_guardian_consent) $rules['guardian_agree'] = 'accepted';
        $request->validate($rules);

        $request->session()->put('consent_ok:'.$code, true);

        // consent_required 검사는 이 시점에 attempt 를 만들고 동의를 영속화한다.
        // (세션 플래그만으로는 start() 직접 호출로 우회 가능했다 — 2026-06-26 spec 지적)
        if ($test->consent_required) {
            // 동의 폼 재제출(뒤로가기·새로고침) 시 아직 시작하지 않은 attempt 는 재사용한다.
            // 재사용하지 않으면 매 POST 마다 고아 attempt 와 sensitive 동의행이 쌓인다 —
            // 동의 기록은 법적 증거라 구분 불가한 중복행이 특히 나쁘다.
            $existingId = $request->session()->get('oymsi_attempt:'.$code);
            $existing = $existingId ? TestAttempt::find($existingId) : null;
            $reusable = $existing && $existing->test_id === $test->id && $existing->status === 'created';

            if (!$reusable) {
                $attempt = TestAttempt::create(array_merge(
                    $this->actorColumns($request),
                    [
                        'test_id' => $test->id,
                        'status' => 'created',
                        'assessment_version' => $test->assessment_version,
                        'scoring_version' => $test->scoringRule?->version,
                        'age_at_test' => $request->session()->get('oymsi_age:'.$code),
                    ]
                ));
                $gate->record($attempt, \App\Services\OyMsi\ConsentGate::SENSITIVE, 'youth', auth()->id());
                $request->session()->put('oymsi_attempt:'.$code, $attempt->id);
            }
        }

        return redirect()->route('assessment.intro', $code);
    }

    public function intro(string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        return view('assessment.intro', compact('test'));
    }

    public function start(Request $request, string $code, \App\Services\VoucherService $vouchers)
    {
        $test = Test::where('code', $code)->firstOrFail();

        // 보호자 동의 검사: 동의 통과 세션 플래그 없으면 동의로
        if ($test->requires_guardian_consent && !$request->session()->get('consent_ok:'.$code)) {
            return redirect()->route('assessment.consent', $code);
        }

        // 자격(entitlement) 확인은 consent_required 분기보다 먼저 돈다 — consent_required 라고
        // 유료 검사의 결제·검사권 확인을 건너뛰면 안 된다.
        $consume = null;
        if ($test->isPaid()) {
            if (!auth()->check()) return redirect()->route('login');
            $consume = $vouchers->firstActive(auth()->user(), $test);
            if (!$consume) {
                return redirect()->route('checkout.show', $test->activeProduct()->id);
            }
        }

        if ($test->consent_required) {
            $existingId = $request->session()->get('oymsi_attempt:'.$code);
            $attempt = $existingId ? TestAttempt::find($existingId) : null;
            abort_unless($attempt && $attempt->test_id === $test->id, 403, '검사 전 동의가 확인되지 않았습니다.');
            // 제출 완료된 attempt 로 start() 를 재호출해도 submitted -> in_progress 로 되돌리지 않는다
            // (되돌리면 submit() 의 이중제출 409 가드가 무력화되고 채점이 재실행된다).
            abort_if($attempt->status === 'submitted', 409, '이미 제출된 검사입니다.');
            $attempt->update(['status' => 'in_progress', 'started_at' => now()]);
            if ($consume && !$attempt->voucher_id) {
                $vouchers->consume(auth()->user(), $test, $attempt);
            }
            return redirect()->route('assessment.take', [$code, $attempt->id]);
        }

        $attempt = TestAttempt::create(array_merge(
            $this->actorColumns($request),
            ['test_id' => $test->id, 'status' => 'in_progress', 'started_at' => now()]
        ));

        if ($consume) {
            $vouchers->consume(auth()->user(), $test, $attempt);
        }

        return redirect()->route('assessment.take', [$code, $attempt->id]);
    }

    private function authorizeAttempt(Request $request, TestAttempt $attempt): void
    {
        abort_unless($attempt->isOwnedBy($request), 403);
    }

    public function take(Request $request, string $code, TestAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        app(\App\Services\OyMsi\ConsentGate::class)->assertSatisfied($attempt);
        $attempt->load('test.items');
        return view('assessment.take', ['test' => $attempt->test, 'attempt' => $attempt]);
    }

    public function submit(Request $request, string $code, TestAttempt $attempt, ScoringService $scoring)
    {
        $this->authorizeAttempt($request, $attempt);
        app(\App\Services\OyMsi\ConsentGate::class)->assertSatisfied($attempt);
        abort_if($attempt->status === 'submitted', 409);

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
