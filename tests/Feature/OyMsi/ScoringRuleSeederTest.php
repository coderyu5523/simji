<?php
use App\Models\Test;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
});

test('최상위 키가 모두 존재한다', function () {
    expect(array_keys($this->rules))->toContain(
        'factors', 'bands', 'overall_bands', 'safety', 'environment',
        'case_codes', 'priority', 'strengths', 'solutions', 'recheck'
    );
});

test('요인 10개 · SAF 만 총점 제외', function () {
    expect($this->rules['factors'])->toHaveCount(10);
    $excluded = collect($this->rules['factors'])
        ->reject(fn ($f) => $f['included_in_overall'])->keys()->all();
    expect($excluded)->toBe(['SAF']);
});

test('밴드 경계가 5/10 이다', function () {
    expect($this->rules['bands']['GREEN']['max'])->toBe(5);
    expect($this->rules['bands']['YELLOW']['min'])->toBe(6);
    expect($this->rules['bands']['YELLOW']['max'])->toBe(10);
    expect($this->rules['bands']['RED']['min'])->toBe(11);
});

test('S3 조건이 003 기준이다 (SAF04>=1, SAF01=3, SAF02=3, SAF05>=2 포함)', function () {
    $s3 = $this->rules['safety']['S3'];
    expect($s3)->toContain(['item' => 'SAF04', 'op' => '>=', 'value' => 1]);
    expect($s3)->toContain(['item' => 'SAF01', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF02', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF05', 'op' => '>=', 'value' => 2]);
    expect($s3)->toContain(['item' => 'SAF03', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF06', 'op' => '>=', 'value' => 1]);
});

test('S2 에는 007 잔여분만 남는다', function () {
    $s2Items = collect($this->rules['safety']['S2'])->pluck('item')->unique()->sort()->values()->all();
    expect($s2Items)->toBe(['SAF01', 'SAF02', 'SAF03']);
});

test('tie_break 우선순위가 DEP 9 … FUT 1 이다', function () {
    $tb = collect($this->rules['factors'])->map(fn ($f) => $f['tie_break']);
    expect($tb['DEP'])->toBe(9);
    expect($tb['TRM'])->toBe(8);
    expect($tb['FAM'])->toBe(7);
    expect($tb['RSK'])->toBe(6);
    expect($tb['IMP'])->toBe(5);
    expect($tb['ISO'])->toBe(4);
    expect($tb['LIF'])->toBe(3);
    expect($tb['ANX'])->toBe(2);
    expect($tb['FUT'])->toBe(1);
});

test('총점 포함 요인들의 tie_break 값이 서로 유일하다 (우선순위 결정성 보장)', function () {
    $tieBreaks = collect($this->rules['factors'])
        ->filter(fn ($f) => $f['included_in_overall'])
        ->map(fn ($f) => $f['tie_break'])
        ->values();

    expect($tieBreaks->unique())->toHaveCount($tieBreaks->count());
});

test('솔루션 10종에 dedupe_group 이 있다', function () {
    expect($this->rules['solutions'])->toHaveCount(10);
    foreach ($this->rules['solutions'] as $code => $sol) {
        expect($sol)->toHaveKeys(['factor', 'title', 'dedupe_group']);
    }
});

test('environment E3/E2/E1 조건이 정확하다 · E1 에는 RSK04 가 없다', function () {
    $e = $this->rules['environment'];

    expect($e['E3'])->toBe([
        ['item' => 'TRM06', 'op' => '=', 'value' => 3, 'factor' => 'TRM'],
        ['item' => 'FAM05', 'op' => '=', 'value' => 3, 'factor' => 'FAM'],
        ['item' => 'RSK06', 'op' => '=', 'value' => 3, 'factor' => 'RSK'],
    ]);

    expect($e['E2'])->toBe([
        ['item' => 'TRM06', 'op' => '=',  'value' => 2, 'factor' => 'TRM'],
        ['item' => 'FAM05', 'op' => '=',  'value' => 2, 'factor' => 'FAM'],
        ['item' => 'RSK06', 'op' => '=',  'value' => 2, 'factor' => 'RSK'],
        ['item' => 'RSK04', 'op' => '>=', 'value' => 2, 'factor' => 'RSK'],
        ['item' => 'RSK05', 'op' => '>=', 'value' => 2, 'factor' => 'RSK'],
    ]);

    expect($e['E1'])->toBe([
        ['item' => 'TRM06', 'op' => '=', 'value' => 1, 'factor' => 'TRM'],
        ['item' => 'FAM05', 'op' => '=', 'value' => 1, 'factor' => 'FAM'],
        ['item' => 'RSK06', 'op' => '=', 'value' => 1, 'factor' => 'RSK'],
        ['item' => 'RSK05', 'op' => '=', 'value' => 1, 'factor' => 'RSK'],
    ]);

    // RSK04 가 E1에 다시 들어오면 (가벼운 음주/흡연 1회만으로도) 최종 사례코드가
    // 과잉 상향될 수 있다 — 이 부재는 의도적이며 회귀 시 조용히 깨져서는 안 된다.
    $e1Items = collect($e['E1'])->pluck('item')->all();
    expect($e1Items)->not->toContain('RSK04');
});

test('environment 조건마다 007 §7.3 문항→요인 매핑이 factor 로 명시돼 있다', function () {
    // alert_bonus(§9.5) 가 "해당 요인에만" 붙으려면 조건마다 자기 요인을 알아야
    // 한다 — TRM06→TRM, FAM05→FAM, RSK04/05/06→RSK.
    $e = $this->rules['environment'];
    foreach (['E1', 'E2', 'E3'] as $level) {
        foreach ($e[$level] as $condition) {
            expect($condition)->toHaveKey('factor');
            $expectedFactor = str_starts_with($condition['item'], 'RSK') ? 'RSK' : substr($condition['item'], 0, 3);
            expect($condition['factor'])->toBe($expectedFactor);
        }
    }
});

test('case_codes.general 순서가 R2,R1,Y2,Y1,G0 이고 마지막 when 이 null 이다', function () {
    $general = $this->rules['case_codes']['general'];

    expect(collect($general)->pluck('code')->all())->toBe(['R2', 'R1', 'Y2', 'Y1', 'G0']);

    expect($general[0]['when'])->toBe(['red_count', '>=', 2]);
    expect($general[1]['when'])->toBe(['red_count', '>=', 1]);
    expect($general[2]['when'])->toBe(['yellow_count', '>=', 3]);
    expect($general[3]['when'])->toBe(['yellow_count', '>=', 1]);
    expect(collect($general)->last()['code'])->toBe('G0');
    expect(collect($general)->last()['when'])->toBeNull();
});

test('case_codes.escalation 이 1=>C1, 2=>C2, 3=>C3 이다', function () {
    expect($this->rules['case_codes']['escalation'])->toBe([
        1 => 'C1',
        2 => 'C2',
        3 => 'C3',
    ]);
});

test('priority 가중치가 GREEN0/YELLOW100/RED200, alert_bonus 1000, limit 3 이다', function () {
    $p = $this->rules['priority'];
    expect($p['severity_weight'])->toBe(['GREEN' => 0, 'YELLOW' => 100, 'RED' => 200]);
    expect($p['alert_bonus'])->toBe(1000);
    expect($p['limit'])->toBe(3);
});

test('recheck 이 순서대로 14일(C1/C2/C3/R1/R2) · 28일(Y1/Y2) · 90일(G0) 이다', function () {
    $recheck = $this->rules['recheck'];
    expect($recheck)->toHaveCount(3);

    expect($recheck[0]['days'])->toBe(14);
    expect($recheck[0]['when_case_in'])->toBe(['C1', 'C2', 'C3', 'R1', 'R2']);
    expect($recheck[0]['reason'])->toBe('RED_FACTOR_OR_SAFETY');

    expect($recheck[1]['days'])->toBe(28);
    expect($recheck[1]['when_case_in'])->toBe(['Y1', 'Y2']);
    expect($recheck[1]['reason'])->toBe('YELLOW_FACTOR');

    expect($recheck[2]['days'])->toBe(90);
    expect($recheck[2]['when_case_in'])->toBe(['G0']);
    expect($recheck[2]['reason'])->toBe('STABLE');
});

test('strengths 는 정확히 4개 키이고 HELP_SEEKING 은 없다', function () {
    $s = $this->rules['strengths'];

    expect(array_keys($s))->toBe(['TRY_NEW', 'SMALL_GOAL', 'RECOVERY_HOPE', 'HONEST_RESPONSE']);
    expect($s)->not->toHaveKey('HELP_SEEKING');

    expect($s['TRY_NEW'])->toBe(['item' => 'FUT04', 'op' => '>=', 'value' => 2]);
    expect($s['SMALL_GOAL'])->toBe(['item' => 'FUT05', 'op' => '>=', 'value' => 2]);
    expect($s['RECOVERY_HOPE'])->toBe(['item' => 'FUT06', 'op' => '>=', 'value' => 2]);
    expect($s['HONEST_RESPONSE'])->toBe(['always' => true]);
});

test('솔루션 dedupe_group 값이 정확하다 · SAF/TRM 은 안전, DEP/LIF 는 생활회복 로 묶인다', function () {
    $sol = $this->rules['solutions'];

    expect($sol['SOL_SAF_PLAN']['dedupe_group'])->toBe('안전');
    expect($sol['SOL_TRM_SAFETY']['dedupe_group'])->toBe('안전');
    expect($sol['SOL_DEP_ACTIVATION']['dedupe_group'])->toBe('생활회복');
    expect($sol['SOL_LIF_7DAY']['dedupe_group'])->toBe('생활회복');
    expect($sol['SOL_ANX_BREATHING']['dedupe_group'])->toBe('정서조절');
    expect($sol['SOL_IMP_STOP']['dedupe_group'])->toBe('충동조절');
    expect($sol['SOL_ISO_CONNECT']['dedupe_group'])->toBe('관계');
    expect($sol['SOL_FAM_PROTECT']['dedupe_group'])->toBe('가족/보호');
    expect($sol['SOL_RSK_DIGITAL']['dedupe_group'])->toBe('위험행동');
    expect($sol['SOL_FUT_3MONTH']['dedupe_group'])->toBe('진로');
});

test('safety.S1 조건이 SAF01/02/03/05 = 1 이다', function () {
    expect($this->rules['safety']['S1'])->toBe([
        ['item' => 'SAF01', 'op' => '=', 'value' => 1],
        ['item' => 'SAF02', 'op' => '=', 'value' => 1],
        ['item' => 'SAF03', 'op' => '=', 'value' => 1],
        ['item' => 'SAF05', 'op' => '=', 'value' => 1],
    ]);
});
