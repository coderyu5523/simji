<?php
use App\Services\Scoring\OyMsi\ConditionMatcher;

beforeEach(function () {
    $this->matcher = new ConditionMatcher();
});

test('알 수 없는 연산자는 예외를 던진다 (false 로 조용히 낮아지면 안 된다)', function () {
    $this->matcher->matches(
        ['item' => 'SAF01', 'op' => '!=', 'value' => 1],
        ['SAF01' => 1]
    );
})->throws(InvalidArgumentException::class);

test('문항이 입력 맵에 없으면 조건은 거짓이다', function () {
    expect($this->matcher->matches(
        ['item' => 'SAF01', 'op' => '=', 'value' => 1],
        [] // SAF01 키 자체가 없음
    ))->toBeFalse();
});

test('문항이 null(응답거부)이면 조건은 거짓이다', function () {
    expect($this->matcher->matches(
        ['item' => 'SAF01', 'op' => '=', 'value' => 1],
        ['SAF01' => null]
    ))->toBeFalse();
});
