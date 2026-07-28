<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ScoringService;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items', 'scoringRule')->firstOrFail();
    $this->itemsByCode = $this->test->items->keyBy('item_code');
});

/**
 * 007 §7.3 의 환경 문항→요인 1:1 매핑을 fixture 의 원응답에서 **독립적으로** 재도출한다.
 * PHP 엔진(EnvironmentEvaluator::alertedFactors)을 호출하거나 그 코드를 베끼지 않는다 —
 * 그러면 엔진을 엔진으로 검증하는 것이 되어 아무것도 증명하지 못한다. 여기서 쓰는
 * "TRM06/FAM05/RSK04/RSK05/RSK06 >= 2" 는 007 §7.3 표(E2/E3 조건)에서 나온 고정된
 * 스펙 사실이지, PHP 구현이 임의로 고른 값이 아니다. E1(=1, WARN 수준)은 alert_bonus
 * 대상이 아니므로 제외한다(007 §9.5 "HIGH/CRITICAL 경보"만 +1000).
 *
 * @param  array<string,int>  $answers
 * @return list<string>  경보(HIGH/CRITICAL)가 걸린 요인 코드
 */
function alertedFactorsFromAnswers(array $answers): array
{
    $alerted = [];
    if (($answers['TRM06'] ?? 0) >= 2) $alerted['TRM'] = true;
    if (($answers['FAM05'] ?? 0) >= 2) $alerted['FAM'] = true;
    if (($answers['RSK06'] ?? 0) >= 2 || ($answers['RSK04'] ?? 0) >= 2 || ($answers['RSK05'] ?? 0) >= 2) {
        $alerted['RSK'] = true;
    }
    return array_keys($alerted);
}

/**
 * alert_bonus(007 §9.5) 는 모든 경보 요인에 동일하게 +1000 을 주고, 그 외에는
 * PHP 와 JS 가 severity_weight+risk_index+tie_break 로 완전히 동일한 공식을 쓴다
 * (요인별 raw/risk_index/band 0 diff 는 이 파일의 다른 테스트에서 이미 검증됨).
 * 따라서 PHP 의 기대 순서는 "JS 9요인 전체 순서를, 경보 요인은 그 상대순서를
 * 유지한 채 맨 앞으로 당기고 나머지는 그 상대순서대로 뒤에 붙인" 것과 완전히
 * 같다 — 이 함수는 PHP 랭킹 공식(weight+riskIndex+tieBreak+bonus)을 재계산하지
 * 않는다. JS 가 이미 계산해 둔 순서를 재배열할 뿐이다.
 *
 * @param  list<string>  $jsFullOrder  JS priorityFactorsFull() 결과(9개 요인 코드)
 * @param  list<string>  $alertedFactors
 * @return list<string>  PHP 엔진이 반환해야 할 상위 3개
 */
function expectedTop3(array $jsFullOrder, array $alertedFactors): array
{
    $alertedSet = array_flip($alertedFactors);
    $alerted = array_values(array_filter($jsFullOrder, fn ($f) => isset($alertedSet[$f])));
    $rest = array_values(array_filter($jsFullOrder, fn ($f) => !isset($alertedSet[$f])));
    return array_slice(array_merge($alerted, $rest), 0, 3);
}

test('JS 참조 구현과 0 diff (요인·전체지수·일반코드·환경등급·상위3)', function () {
    $path = base_path('tests/fixtures/oy-msi-reference-cases.json');
    expect(file_exists($path))->toBeTrue('먼저 tools/oy-msi-reference/generate-cases.js 를 실행하라');

    $cases = json_decode(file_get_contents($path), true);
    expect(count($cases))->toBeGreaterThanOrEqual(1000);
    expect(array_key_exists('priority_full', $cases[0]['expected']))->toBeTrue(
        'fixture 가 예전 top-3(priority) 형식이다 — tools/oy-msi-reference/generate-cases.js 를 다시 실행하라');

    $mismatches = [];

    foreach ($cases as $index => $case) {
        $attempt = TestAttempt::create([
            'test_id' => $this->test->id, 'guest_token' => 'parity', 'status' => 'in_progress',
            'started_at' => now(),
            'assessment_version' => $this->test->assessment_version,
            'scoring_version' => $this->test->scoringRule->version,
        ]);
        $rows = [];
        foreach ($case['answers'] as $code => $value) {
            $rows[] = [
                'attempt_id' => $attempt->id,
                'test_item_id' => $this->itemsByCode[$code]->id,
                'value' => $value, 'missing_code' => null,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        App\Models\AttemptAnswer::insert($rows);

        $result = app(ScoringService::class)->score($attempt);
        $engine = $result->engine_result;
        $exp = $case['expected'];

        foreach ($exp['factors'] as $factor => $expected) {
            $actual = $engine['factors'][$factor];
            if (abs($actual['raw'] - $expected['raw']) > 0.001
                || abs($actual['risk_index'] - $expected['riskIndex']) > 0.05
                || $actual['band'] !== $expected['band']) {
                $mismatches[] = "case {$index} factor {$factor}: "
                    . json_encode($actual) . ' vs ' . json_encode($expected);
            }
        }

        if (abs($engine['overall']['risk_index'] - $exp['overall_index']) > 0.05) {
            $mismatches[] = "case {$index} overall_index: "
                . $engine['overall']['risk_index'] . ' vs ' . $exp['overall_index'];
        }
        if ($result->general_case_code !== $exp['general_case_code']) {
            $mismatches[] = "case {$index} general_case_code: "
                . $result->general_case_code . ' vs ' . $exp['general_case_code'];
        }
        if ($result->environment_level !== $exp['environment_level']) {
            $mismatches[] = "case {$index} environment_level: "
                . $result->environment_level . ' vs ' . $exp['environment_level'];
        }

        // 상위 3영역: alert_bonus(007 §9.5, EnvironmentEvaluator::alertedFactors) 가
        // "해당 요인에만" 붙으므로, JS 전체순서를 경보요인 우선으로 재배열한 것이
        // PHP 의 기대값과 완전히(순서까지) 일치해야 한다. 3000건 전부가 순서 대조
        // 신호를 갖는다 — 더 이상 "경보면 집합만 확인" 하는 완화가 없다.
        $alertedFactors = alertedFactorsFromAnswers($case['answers']);
        $expected = expectedTop3($exp['priority_full'], $alertedFactors);
        $actual = array_column($engine['priority'], 'factor');
        if ($actual !== $expected) {
            $mismatches[] = "case {$index} priority: "
                . implode(',', $actual) . ' vs ' . implode(',', $expected)
                . ' (alerted=' . implode(',', $alertedFactors) . ')';
        }

        if (count($mismatches) > 20) break; // 로그 폭주 방지
    }

    expect($mismatches)->toBe([], "불일치 " . count($mismatches) . "건:\n" . implode("\n", array_slice($mismatches, 0, 20)));
})->group('parity');

test('003 기준 채택 때문에 안전등급만 JS 와 갈린다', function () {
    $cases = json_decode(file_get_contents(base_path('tests/fixtures/oy-msi-reference-cases.json')), true);

    $promoted = 0;
    foreach ($cases as $case) {
        // JS(007) 기준 S2 인데 003 상향 조건에 걸리는 케이스
        $a = $case['answers'];
        $isPromoted = ($a['SAF04'] ?? 0) >= 1 || ($a['SAF01'] ?? 0) === 3
                   || ($a['SAF02'] ?? 0) === 3 || ($a['SAF05'] ?? 0) >= 2;
        if ($case['expected']['js_safety_level'] === 'S2' && $isPromoted) $promoted++;
    }

    // 무작위 3000건이면 이런 케이스가 반드시 다수 존재한다.
    // 0 이면 fixture 생성이 잘못됐거나 참조 구현이 003 기준으로 오염된 것이다.
    expect($promoted)->toBeGreaterThan(0);
})->group('parity');

test('환경경보(TRM06/FAM05/RSK04/RSK05/RSK06 >= 2)로 인한 alert_bonus 상위3 재배열이 실제로 다수 발생한다', function () {
    // expectedTop3() 의 "경보 요인이 있으면 순서가 바뀐다" 는 전제가 fixture 를
    // 우회하는 눈속임이 아님을 확인한다: 무작위 3000건 중 경보가 있는 케이스와
    // 없는 케이스가 각각 다수 있어야 한다 — 둘 중 하나가 0 건이면 위 0-diff
    // 테스트가 실질적으로 한쪽 분기만 검사한 것이다.
    $cases = json_decode(file_get_contents(base_path('tests/fixtures/oy-msi-reference-cases.json')), true);

    $alertedCount = 0;
    $noAlertCount = 0;
    // JS 순서와 alert_bonus 재배열 후 순서가 실제로 달라지는 케이스도 별도로 센다 —
    // 경보는 있지만 우연히 그 요인이 이미 JS top-3 안에 있어 순서가 안 바뀌는
    // 경우까지 포함하면 "재배열이 실제로 검증되는지"를 과대평가하게 된다.
    $reordered = 0;
    foreach ($cases as $case) {
        $alertedFactors = alertedFactorsFromAnswers($case['answers']);
        if ($alertedFactors === []) { $noAlertCount++; continue; }
        $alertedCount++;

        $jsTop3 = array_slice($case['expected']['priority_full'], 0, 3);
        $expected = expectedTop3($case['expected']['priority_full'], $alertedFactors);
        if ($expected !== $jsTop3) $reordered++;
    }

    expect($alertedCount)->toBeGreaterThan(0);
    expect($noAlertCount)->toBeGreaterThan(0);
    expect($reordered)->toBeGreaterThan(0);
})->group('parity');
