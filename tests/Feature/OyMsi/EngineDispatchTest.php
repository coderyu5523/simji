<?php
use App\Models\Test;
use App\Services\Scoring\ScoringEngine;
use App\Services\Scoring\SignalScoringEngine;
use App\Services\ScoringService;

function makeTest(string $code, ?string $engine = null): Test
{
    return Test::create([
        'code' => $code, 'room' => 'worker', 'title_easy' => 'x', 'title_pro' => 'X',
        'target' => 't', 'duration_min' => 1, 'item_count' => 1, 'areas' => ['A'],
        'result_type' => 'signal', 'description' => 'd', 'status' => 'draft',
    ] + ($engine ? ['scoring_engine' => $engine] : []));
}

test('scoring_engine 기본값이면 SignalScoringEngine 을 고른다', function () {
    expect(app(ScoringService::class)->engineFor(makeTest('DISPATCH1')))
        ->toBeInstanceOf(SignalScoringEngine::class);
});

test('알 수 없는 엔진 이름이면 예외를 던진다', function () {
    expect(fn () => app(ScoringService::class)->engineFor(makeTest('DISPATCH2', 'nope')))
        ->toThrow(InvalidArgumentException::class);
});

test('등록된 모든 엔진이 인터페이스를 구현한다', function () {
    foreach (ScoringService::ENGINES as $class) {
        expect(app($class))->toBeInstanceOf(ScoringEngine::class);
    }
});
