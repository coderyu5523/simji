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

    public function agree(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $rules = ['agree' => 'accepted'];
        if ($test->requires_guardian_consent) $rules['guardian_agree'] = 'accepted';
        $request->validate($rules);
        $request->session()->put('consent_ok:'.$code, true);
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

        $consume = null;
        if ($test->isPaid()) {
            if (!auth()->check()) return redirect()->route('login');
            $consume = $vouchers->firstActive(auth()->user(), $test);
            if (!$consume) {
                return redirect()->route('checkout.show', $test->activeProduct()->id);
            }
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
        $attempt->load('test.items');
        return view('assessment.take', ['test' => $attempt->test, 'attempt' => $attempt]);
    }

    public function submit(Request $request, string $code, TestAttempt $attempt, ScoringService $scoring)
    {
        $this->authorizeAttempt($request, $attempt);
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
