<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * OY_MSI 채점까지 끝난 응시 1건을 만든다.
 *
 * 원래 ResultScreenTest.php 안에 있었으나 Task 18(공유) 테스트에서도 같은 픽스처가
 * 필요해졌다. Pest 의 전역 함수는 파일마다 다시 정의할 수 없으므로 여기로 옮겼다.
 *
 * $overrides 는 item_code => 원점수. 지정하지 않은 문항은 전부 0점이다.
 * $user 가 null 이면 guest_token='g' 인 비회원 응시가 된다.
 */
function completedAttempt(array $overrides = [], ?App\Models\User $user = null): App\Models\TestAttempt
{
    $test = App\Models\Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $attempt = App\Models\TestAttempt::create([
        'test_id' => $test->id, 'user_id' => $user?->id, 'guest_token' => $user ? null : 'g',
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'nickname' => '민수', 'gender' => 'male', 'age_at_test' => 16,
        'assessment_version' => $test->assessment_version,
        'scoring_version' => $test->scoringRule->version,
    ]);
    foreach ($test->items as $item) {
        $raw = $overrides[$item->item_code] ?? 0;
        $attempt->answers()->create([
            'test_item_id' => $item->id,
            'value' => $raw, 'missing_code' => null,
        ]);
    }
    app(App\Services\ScoringService::class)->score($attempt);

    return $attempt->fresh();
}

/**
 * 연령 게이트 폼이 실제로 보내는 형태(년·월·일 세 칸)를 만든다.
 * 테스트가 폼과 다른 모양을 보내면 폼이 깨져도 초록으로 남는다 — 78625c8(405) 재발 방지.
 */
function birthdateFields(string $ymd): array
{
    [$y, $m, $d] = explode('-', $ymd);

    return ['birth_year' => $y, 'birth_month' => $m, 'birth_day' => $d];
}
