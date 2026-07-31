<?php
namespace App\Services;

use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 검사를 실제로 "시작"시키는 단 하나의 지점.
 *
 * 시작은 부작용이 있는 동작이다(attempt 를 in_progress 로 바꾸고 검사권을 소비한다).
 * 그래서 GET 으로 열려서는 안 되고, 반드시 POST 핸들러 안에서 호출돼야 한다.
 *
 * 예전에는 이 부작용 때문에 POST 전용 assessment.start 라우트만 두고, 다른 POST 단계들이
 * 그리로 "리다이렉트"하려다 405 를 냈다. 그 회피책으로 GET 으로 열리는 안내(intro) 화면을
 * 경유지로 삼았는데, 안내가 기본정보 단계 앞에 있어서 결국 같은 화면을 두 번 지나게 됐다.
 * 리다이렉트 대신 이 서비스를 직접 호출하면 경유지 자체가 필요 없다.
 */
class AssessmentStarter
{
    public function __construct(private VoucherService $vouchers) {}

    /**
     * 로그인·비로그인 응시자를 구분하는 소유권 컬럼.
     * 비로그인이면 세션에 guest_token 을 만들어 그 값으로 소유권을 표시한다.
     */
    public function actorColumns(Request $request): array
    {
        if (auth()->check()) return ['user_id' => auth()->id(), 'guest_token' => null];
        if (!$request->session()->get('guest_token')) {
            $request->session()->put('guest_token', (string) \Illuminate\Support\Str::uuid());
        }
        return ['user_id' => null, 'guest_token' => $request->session()->get('guest_token')];
    }

    /**
     * 자격·동의·기본정보를 확인하고 검사를 시작한다.
     * 통과하면 문항 화면으로, 못 갖춘 단계가 있으면 그 단계로 되돌리는 리다이렉트를 돌려준다.
     */
    public function start(Request $request, Test $test): RedirectResponse
    {
        $code = $test->code;

        // 보호자 동의 검사: 동의 통과 세션 플래그 없으면 동의로
        if ($test->requires_guardian_consent && !$request->session()->get('consent_ok:'.$code)) {
            return redirect()->route('assessment.consent', $code);
        }

        // 자격(entitlement) 확인은 consent_required 분기보다 먼저 돈다 — consent_required 라고
        // 유료 검사의 결제·검사권 확인을 건너뛰면 안 된다.
        $consume = null;
        if ($test->isPaid()) {
            if (!auth()->check()) return redirect()->route('login');
            $consume = $this->vouchers->firstActive(auth()->user(), $test);
            if (!$consume) {
                return redirect()->route('checkout.show', $test->activeProduct()->id);
            }
        }

        if ($test->consent_required) {
            $existingId = $request->session()->get('oymsi_attempt:'.$code);
            $attempt = $existingId ? TestAttempt::find($existingId) : null;
            abort_unless($attempt && $attempt->test_id === $test->id, 403, '검사 전 동의가 확인되지 않았습니다.');
            // 제출 완료된 attempt 로 start() 를 재호출해도 submitted -> in_progress 로 되돌리지 않는다
            // (되돌리면 이중제출 가드가 무력화되고 채점이 재실행된다). 상태는 그대로 두고 결과로 보낸다.
            if ($attempt->status === 'submitted') {
                return redirect()->route('result.show', $attempt->id);
            }
            // 기본정보(닉네임)를 건너뛰고 응시로 직행할 수 없다 — 없으면 기본정보 화면으로 되돌린다.
            if (!$attempt->nickname) {
                return redirect()->route('oymsi.profile.form', $code);
            }
            $attempt->update(['status' => 'in_progress', 'started_at' => now()]);
            if ($consume && !$attempt->voucher_id) {
                $this->vouchers->consume(auth()->user(), $test, $attempt);
            }
            return redirect()->route('assessment.take', [$code, $attempt->id]);
        }

        // 기본정보 단계가 없는 검사는 동의 직후 여기로 온다. 동의 화면에서 뒤로가기 후 다시
        // 동의하면 매번 새 attempt 가 생기므로, 아직 제출하지 않은 attempt 는 재사용한다
        // (consent_required 쪽 재사용 규칙과 같은 이유 — 고아 attempt 를 쌓지 않는다).
        $existingId = $request->session()->get('oymsi_attempt:'.$code);
        $existing = $existingId ? TestAttempt::find($existingId) : null;
        if ($existing && $existing->test_id === $test->id
            && $existing->status !== 'submitted' && $existing->isOwnedBy($request)) {
            return redirect()->route('assessment.take', [$code, $existing->id]);
        }

        $attempt = TestAttempt::create(array_merge(
            $this->actorColumns($request),
            [
                'test_id' => $test->id, 'status' => 'in_progress', 'started_at' => now(),
                // consent_required 가 아니어도 연령 게이트를 거친 검사면 나이를 남긴다
                // (ConsentGate 의 fail closed 가 이 값을 본다).
                'age_at_test' => $request->session()->get('oymsi_age:'.$code),
            ]
        ));
        $request->session()->put('oymsi_attempt:'.$code, $attempt->id);

        if ($consume) {
            $this->vouchers->consume(auth()->user(), $test, $attempt);
        }

        return redirect()->route('assessment.take', [$code, $attempt->id]);
    }
}
