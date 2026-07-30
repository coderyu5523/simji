<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\Export\ResponseExporter;
use App\Services\ScoringService;
use Database\Seeders\SampleTestSeeder;

/**
 * OY_MSI 전용 채점 엔진(OyMsiScoringEngine)은 rules['factors'] 와 engine_result.factors 를
 * 쓰지만, 활성 검사 중 SignalScoringEngine 을 쓰는 것들(KMSIA-SAMPLE 등)은 rules['areas']
 * 만 갖고 engine_result 를 아예 안 쓴다. ResponseExporter 가 rules['factors']/engine_result
 * 만 본다면 이런 검사는 영역 컬럼이 0개가 되어 area_scores/area_signals 에 있는 데이터가
 * CSV 에서 조용히 사라진다 — 이 파일은 그 회귀를 잡는다.
 */
beforeEach(function () {
    (new SampleTestSeeder())->run();
    $this->test = Test::where('code', 'KMSIA-SAMPLE')->firstOrFail();
    $this->exporter = new ResponseExporter();
});

/** 실제 응답을 채워 넣고 진짜 채점 엔진(SignalScoringEngine)으로 채점한 응시를 만든다. */
function signalAttempt(Test $test): TestAttempt
{
    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.uniqid(),
        'test_id' => $test->id, 'status' => 'submitted',
        'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 25, 'gender' => 'female', 'nickname' => '샘플',
    ]);

    foreach ($test->items as $item) {
        $attempt->answers()->create(['test_item_id' => $item->id, 'value' => 4]);
    }

    app(ScoringService::class)->score($attempt);

    return $attempt->fresh();
}

test('연구용은 SignalScoringEngine 검사에서도 영역 컬럼이 나오고 값이 채워진다', function () {
    $attempt = signalAttempt($this->test);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    // rules['factors'] 가 없는 검사이므로 rules['areas'] 로 폴백해야 컬럼이 생긴다.
    expect($headers)->toContain('스트레스_raw')->toContain('스트레스_band');

    // engine_result.factors 가 없으므로 area_scores/area_signals 로 폴백해야 값이 채워진다.
    expect($byColumn['스트레스_raw'])->not->toBeNull();
    expect($byColumn['스트레스_band'])->not->toBeNull();
    expect($attempt->result->area_scores)->not->toBeEmpty();
    expect($byColumn['스트레스_raw'])->toBe($attempt->result->area_scores['스트레스']);
});

test('기관용도 SignalScoringEngine 검사에서 영역 컬럼이 나온다', function () {
    $attempt = signalAttempt($this->test);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    // rules['areas'] 엔 included_in_overall 개념이 없으므로 전부 포함으로 취급한다.
    expect($headers)->toContain('회복탄력성_raw');
    expect($byColumn['회복탄력성_raw'])->not->toBeNull();
});
