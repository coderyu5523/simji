<?php
use App\Services\Scoring\OyMsi\ItemScorer;

test('일반 문항은 원점수를 그대로 쓴다', function () {
    expect((new ItemScorer())->score(['DEP01' => 0, 'DEP02' => 3], []))
        ->toBe(['DEP01' => 0, 'DEP02' => 3]);
});

test('역채점 문항은 3 - raw 로 뒤집는다 (007 T06·T07)', function () {
    $scored = (new ItemScorer())->score(
        ['FUT04' => 3, 'FUT05' => 0, 'FUT06' => 1],
        ['FUT04', 'FUT05', 'FUT06']
    );
    expect($scored['FUT04'])->toBe(0); // T06
    expect($scored['FUT05'])->toBe(3); // T07
    expect($scored['FUT06'])->toBe(2);
});

test('응답거부는 null 로 남고 역채점하지 않는다', function () {
    $scored = (new ItemScorer())->score(['FUT04' => null, 'DEP01' => null], ['FUT04']);
    expect($scored['FUT04'])->toBeNull();
    expect($scored['DEP01'])->toBeNull();
});
