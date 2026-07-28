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

        return view('link.landing', ['voucher' => $voucher, 'test' => $voucher->test]);
    }

    public function start(Request $request, string $token)
    {
        $voucher = $this->voucherOrFail($token);
        abort_if($voucher->status === 'used', 409, '이미 응시가 완료된 링크입니다.');

        // consent_required 검사는 링크 수신자용 동의 확인 화면이 아직 없다(Task 13에서 추가).
        // 화면이 생기기 전까지는 통과시키지 않고 막는다 — fail closed.
        abort_if($voucher->test->consent_required, 403, '이 검사는 아직 링크 응시를 지원하지 않습니다.');

        $data = $request->validate([
            'recipient_name' => 'required|string|max:100',
            'recipient_age' => 'nullable|string|max:20',
        ]);

        $attempt = TestAttempt::create([
            'user_id' => null,
            'guest_token' => $this->guestToken($request),
            'test_id' => $voucher->test_id,
            'voucher_id' => $voucher->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

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
