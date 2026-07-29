<?php
use App\Models\Test;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->items = $this->test->items()->orderBy('no')->get();
});

test('60문항이고 표시순서가 1~60 연속이다', function () {
    expect($this->items)->toHaveCount(60);
    expect($this->items->pluck('no')->all())->toBe(range(1, 60));
});

test('item_code 가 유일하고 요인마다 정확히 6문항이다', function () {
    expect($this->items->pluck('item_code')->unique())->toHaveCount(60);

    $byFactor = $this->items->groupBy('area');
    expect($byFactor->keys()->sort()->values()->all())
        ->toBe(['ANX', 'DEP', 'FAM', 'FUT', 'IMP', 'ISO', 'LIF', 'RSK', 'SAF', 'TRM']);
    foreach ($byFactor as $factor => $group) {
        expect($group)->toHaveCount(6, "요인 {$factor} 문항 수");
    }
});

test('역채점은 FUT04·FUT05·FUT06 셋뿐이다', function () {
    $reversed = $this->items->where('reverse', true)->pluck('item_code')->sort()->values()->all();
    expect($reversed)->toBe(['FUT04', 'FUT05', 'FUT06']);
});

test('척도 배정이 GEN 54 · SAF-T 4 · SAF-B 2 이다', function () {
    $counts = $this->items->countBy('scale_code');
    expect($counts['GEN_4PT'])->toBe(54);
    expect($counts['SAF_THOUGHT_4PT'])->toBe(4);
    expect($counts['SAF_BEHAVIOR_4PT'])->toBe(2);
});

test('12개월 기준 문항은 SAF05·SAF06 둘뿐이다', function () {
    $yearly = $this->items->where('timeframe_code', 'PAST_12_MONTHS')
        ->pluck('item_code')->sort()->values()->all();
    expect($yearly)->toBe(['SAF05', 'SAF06']);
});

test('동일 요인이 연속 배치되지 않는다 (007 §4.1)', function () {
    $factors = $this->items->pluck('area')->all();
    for ($i = 1; $i < count($factors); $i++) {
        expect($factors[$i])->not->toBe(
            $factors[$i - 1],
            sprintf('Q%03d 와 Q%03d 가 같은 요인(%s)', $i, $i + 1, $factors[$i])
        );
    }
});

test('안전문항이 Q010·Q018·Q026·Q034·Q042·Q060 에 위치한다', function () {
    $safPositions = $this->items->where('area', 'SAF')->pluck('no')->sort()->values()->all();
    expect($safPositions)->toBe([10, 18, 26, 34, 42, 60]);
});

test('10문항 사이클마다 10개 요인이 정확히 1회씩 나온다', function () {
    foreach (range(0, 5) as $cycle) {
        $slice = $this->items->slice($cycle * 10, 10)->pluck('area');
        expect($slice->unique())->toHaveCount(10, "사이클 " . ($cycle + 1));
    }
});

test('역채점 문항은 후반(Q031 이후)에 분산된다', function () {
    $positions = $this->items->where('reverse', true)->pluck('no')->sort()->values()->all();
    expect($positions)->toBe([31, 44, 57]);
});

test('검사 메타가 spec 과 일치한다', function () {
    expect($this->test->scoring_engine)->toBe('oy_msi');
    expect($this->test->assessment_version)->toBe('1.0.1');
    expect($this->test->min_age)->toBe(13);
    expect($this->test->max_age)->toBe(18);
    expect($this->test->guardian_consent_below_age)->toBe(14);
    // 2026-07-29 사용자 결정: 실서비스 오픈으로 방향 확정 → 시더가 active 로 만든다.
    // (그 전까지는 1단계 비공개 방침에 따라 draft 였다)
    expect($this->test->status)->toBe('active');
});
