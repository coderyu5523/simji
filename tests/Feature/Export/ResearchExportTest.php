<?php
use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Models\User;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->admin = User::factory()->create(['is_admin' => true]);
});

// 이름을 submittedAttempt() 대신 submittedResearchAttempt() 로 둔다 —
// tests/Feature/OyMsi/ResubmitTest.php 가 이미 같은 이름(다른 시그니처)을
// 전역으로 선언하고 있어서, 두 파일이 같은 테스트 실행에서 함께 로드되면
// "Cannot redeclare submittedAttempt()" 로 전체 스위트가 죽는다.
function submittedResearchAttempt(Test $test, array $answers = ['SAF06' => 3]): TestAttempt
{
    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.uniqid(),
        'test_id' => $test->id, 'status' => 'submitted',
        'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 16, 'gender' => 'female', 'nickname' => '지은',
    ]);

    $itemsByCode = $test->items->keyBy('item_code');
    foreach ($answers as $code => $value) {
        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'test_item_id' => $itemsByCode[$code]->id,
            'value' => $value,
        ]);
    }

    TestResult::create([
        'attempt_id' => $attempt->id,
        'area_scores' => [], 'area_signals' => [], 'recommendations' => [],
        'overall_level' => 'low', 'overall_signal' => 'green', 'interpretation' => '',
        'safety_level' => 'S3', 'environment_level' => 'E0', 'score_status' => 'COMPLETE',
        'engine_result' => ['factors' => []],
    ]);

    return $attempt;
}

/** 스트리밍 응답 본문을 문자열로 받는다(Laravel 이 버퍼링해 준다). */
function csvBody($response): string
{
    return $response->streamedContent();
}

test('비관리자는 연구용 추출을 받을 수 없다', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('admin.exports.research', $this->test))
        ->assertForbidden();
});

test('비로그인은 연구용 추출을 받을 수 없다', function () {
    $this->get(route('admin.exports.research', $this->test))
        ->assertRedirect(route('login'));
});

test('관리자는 CSV 를 받는다', function () {
    submittedResearchAttempt($this->test);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.exports.research', $this->test))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('OY_MSI');
});

test('CSV 는 BOM 으로 시작하고 SAF 문항을 담는다', function () {
    submittedResearchAttempt($this->test, ['SAF06' => 3]);

    $response = $this->actingAs($this->admin)->get(route('admin.exports.research', $this->test));
    $body = csvBody($response);

    expect($body)->toStartWith("\xEF\xBB\xBF");
    expect($body)->toContain('SAF06');
});

test('이름이 CSV 에 없다', function () {
    submittedResearchAttempt($this->test);

    $body = csvBody($this->actingAs($this->admin)->get(route('admin.exports.research', $this->test)));

    expect($body)->not->toContain('지은');
});

test('미제출 응시는 빠진다', function () {
    TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-progress',
        'test_id' => $this->test->id, 'status' => 'in_progress',
        'started_at' => now(), 'age_at_test' => 15,
    ]);
    $submitted = submittedResearchAttempt($this->test);

    $body = csvBody($this->actingAs($this->admin)->get(route('admin.exports.research', $this->test)));
    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines)->toHaveCount(2);                       // 헤더 + 제출 1건
    expect($body)->toContain((string) $submitted->id);
});

test('추출이 감사 로그에 남는다', function () {
    submittedResearchAttempt($this->test);
    \Illuminate\Support\Facades\Log::spy();

    csvBody($this->actingAs($this->admin)->get(route('admin.exports.research', $this->test)));

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) => str_contains($message, '응답 추출')
            && ($context['profile'] ?? null) === 'research'
            && ($context['actor_id'] ?? null) === $this->admin->id);
});

test('관리자 검사 목록에 내려받기 링크가 있다', function () {
    $this->actingAs($this->admin)->get('/admin/tests')
        ->assertOk()
        ->assertSee(route('admin.exports.research', $this->test), escape: false);
});
