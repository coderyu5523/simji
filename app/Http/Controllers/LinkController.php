<?php
namespace App\Http\Controllers;

use App\Models\TestAttempt;
use App\Models\Voucher;
use App\Services\ScoringService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 검사권 링크 응시 (로그인 불필요).
 * 발급자가 전달한 /t/{token} 링크로 대상자가 직접 응시한다.
 */
class LinkController extends Controller
{
    private function voucherOrFail(string $token): Voucher
    {
        return Voucher::where('access_token', $token)->firstOrFail();
    }

    private function guestToken(Request $request): string
    {
        if (!$request->session()->get('guest_token')) {
            $request->session()->put('guest_token', (string) Str::uuid());
        }
        return $request->session()->get('guest_token');
    }

    public function landing(Request $request, string $token)
    {
        $voucher = $this->voucherOrFail($token);
        $voucher->load('test');

        // 이미 응시 완료된 링크
        if ($voucher->status === 'used' && $voucher->used_attempt_id) {
            return view('link.done', ['voucher' => $voucher, 'test' => $voucher->test]);
        }

        // 연령 확인이 필요한 검사는 시작 화면보다 먼저 나이를 받는다 (완료 안내 뒤에 둔다 —
        // 이미 끝난 링크에까지 나이를 물을 이유가 없다).
        if ($voucher->test->requiresAgeVerification()
            && !$request->session()->has('oymsi_age_token:'.$token)) {
            return redirect()->route('link.age.form', $token);
        }

        return view('link.landing', ['voucher' => $voucher, 'test' => $voucher->test]);
    }

    public function start(
        Request $request,
        string $token,
        \App\Services\OyMsi\AgeGate $ageGate,
        \App\Services\OyMsi\ConsentGate $consents
    ) {
        $voucher = $this->voucherOrFail($token);
        abort_if($voucher->status === 'used', 409, '이미 응시가 완료된 링크입니다.');
        $voucher->load('test');
        $test = $voucher->test;

        $rules = [
            'recipient_name' => 'required|string|max:100',
            'recipient_age' => 'nullable|string|max:20',
        ];
        // 링크 수신자용 동의 — landing 화면의 체크박스. 없으면 attempt 를 만들지 않는다.
        if ($test->consent_required) $rules['agree'] = 'accepted';
        $data = $request->validate($rules);

        $age = null;
        if ($test->requiresAgeVerification()) {
            $age = $request->session()->get('oymsi_age_token:'.$token);
            // 세션이 비었으면(만료·직접 POST) 통과가 아니라 연령 게이트로 되돌린다 — fail closed.
            if ($age === null) return redirect()->route('link.age.form', $token);
            $age = (int) $age;

            // 세션 값만 믿지 않는다. 담당자 확인이 사후에 철회됐거나 세션이 조작된 경우를 위해
            // 시작 시점에 규칙을 서버에서 다시 판정한다.
            abort_if(
                $ageGate->blockReason($test, $age, $voucher->guardian_consent_confirmed_at !== null, isLink: true) !== null,
                403,
                '이 검사를 응시할 수 있는 조건이 확인되지 않았습니다.'
            );
        }

        $attempt = TestAttempt::create([
            'user_id' => null,
            'guest_token' => $this->guestToken($request),
            'test_id' => $voucher->test_id,
            'voucher_id' => $voucher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'age_at_test' => $age,
            'assessment_version' => $test->assessment_version,
            'scoring_version' => $test->scoringRule?->version,
        ]);

        if ($test->consent_required) {
            $consents->record($attempt, \App\Services\OyMsi\ConsentGate::SENSITIVE, 'youth');

            // 만 14세 미만이 여기까지 왔다는 건 발급 기관 담당자가 법정대리인 동의를
            // 오프라인으로 확인했다는 뜻이다. 그 사실을 기록으로 남긴다 —
            // 남기지 않으면 ConsentGate 가 곧바로 403 을 던져 통과 자체가 불가능해진다.
            if ($test->needsGuardianConsentFor($age)) {
                $consents->record(
                    $attempt,
                    \App\Services\OyMsi\ConsentGate::GUARDIAN_OFFLINE,
                    'staff',
                    $voucher->guardian_consent_confirmed_by,
                    [
                        'voucher_id' => $voucher->id,
                        'confirmed_at' => $voucher->guardian_consent_confirmed_at?->toIso8601String(),
                        'age_at_test' => $age,
                    ]
                );
            }
        }

        $voucher->update([
            'recipient_name' => $data['recipient_name'],
            'recipient_age' => $data['recipient_age'] ?? null,
        ]);

        return redirect()->route('link.take', [$token, $attempt->id]);
    }

    public function take(Request $request, string $token, TestAttempt $attempt)
    {
        $voucher = $this->voucherOrFail($token);
        $this->authorizeLinkAttempt($request, $voucher, $attempt);
        app(\App\Services\OyMsi\ConsentGate::class)->assertSatisfied($attempt);

        $attempt->load('test.items');
        return view('assessment.take', [
            'test' => $attempt->test,
            'attempt' => $attempt,
            'submitUrl' => route('link.submit', [$token, $attempt->id]),
        ]);
    }

    public function submit(Request $request, string $token, TestAttempt $attempt, ScoringService $scoring, VoucherService $vouchers)
    {
        $voucher = $this->voucherOrFail($token);
        $this->authorizeLinkAttempt($request, $voucher, $attempt);
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
        $vouchers->markUsedByAttempt($voucher, $attempt);

        return redirect()->route('result.show', $attempt->id);
    }

    private function authorizeLinkAttempt(Request $request, Voucher $voucher, TestAttempt $attempt): void
    {
        abort_unless(
            $attempt->voucher_id === $voucher->id
                && $attempt->guest_token
                && $attempt->guest_token === $request->session()->get('guest_token'),
            403
        );
    }
}
