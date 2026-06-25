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
        $request->validate(['agree' => 'accepted']);
        return redirect()->route('assessment.intro', $code);
    }

    public function intro(string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        return view('assessment.intro', compact('test'));
    }

    public function start(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $attempt = TestAttempt::create(array_merge(
            $this->actorColumns($request),
            ['test_id' => $test->id, 'status' => 'in_progress', 'started_at' => now()]
        ));
        return redirect()->route('assessment.take', [$code, $attempt->id]);
    }

    private function authorizeAttempt(Request $request, TestAttempt $attempt): void
    {
        $ok = $attempt->user_id
            ? $attempt->user_id === auth()->id()
            : $attempt->guest_token === $request->session()->get('guest_token');
        abort_unless($ok, 403);
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
        $data = $request->validate(['answers' => 'required|array']);
        foreach ($data['answers'] as $itemId => $value) {
            $attempt->answers()->updateOrCreate(
                ['test_item_id' => (int) $itemId],
                ['value' => (int) $value]
            );
        }
        $attempt->update(['status' => 'submitted', 'submitted_at' => now()]);
        $scoring->score($attempt);
        return redirect()->route('result.show', $attempt->id);
    }
}
