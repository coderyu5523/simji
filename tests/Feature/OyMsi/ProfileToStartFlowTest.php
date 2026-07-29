<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

/** 연령 → 동의 까지 통과한 상태를 만든다. */
function reachProfileStep(): TestAttempt
{
    $birthdate = now()->subYears(16)->subDays(1)->format('Y-m-d');

    test()->actingAs(test()->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => $birthdate])
        ->assertRedirect(route('assessment.consent', 'OY_MSI'));

    test()->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])->assertRedirect();

    return TestAttempt::where('test_id', test()->test->id)->latest('id')->firstOrFail();
}

// ★ 이 테스트가 없어서 405 가 배포까지 나갔다.
//   기존 테스트는 리다이렉트 "주소"만 단언하고 직접 POST 를 쏴서, 브라우저가 실제로 하는
//   GET 이동을 한 번도 재현하지 않았다. 여기서는 Location 을 실제로 따라간다.
test('기본정보를 제출한 뒤 리다이렉트를 따라가면 GET 으로 열리는 화면이 나온다', function () {
    reachProfileStep();

    $res = $this->post(route('oymsi.profile.submit', 'OY_MSI'), [
        'nickname' => '민수', 'gender' => 'male',
    ]);
    $res->assertRedirect();

    // POST 전용 라우트로 보내면 여기서 405 가 난다.
    $this->get($res->headers->get('Location'))->assertOk();
});

test('기본정보 제출 → 안내 → 검사 시작 → 문항 화면까지 이어진다', function () {
    $attempt = reachProfileStep();

    $this->post(route('oymsi.profile.submit', 'OY_MSI'), ['nickname' => '민수', 'gender' => 'male'])
        ->assertRedirect(route('assessment.intro', 'OY_MSI'));

    $this->get(route('assessment.intro', 'OY_MSI'))
        ->assertOk()
        ->assertSee('검사 시작');

    $this->post(route('assessment.start', 'OY_MSI'))
        ->assertRedirect(route('assessment.take', ['OY_MSI', $attempt->id]));

    $this->get(route('assessment.take', ['OY_MSI', $attempt->id]))->assertOk();

    expect($attempt->fresh()->nickname)->toBe('민수');
    expect($attempt->fresh()->status)->toBe('in_progress');
});

test('닉네임 없이 검사 시작을 누르면 기본정보 화면으로 되돌린다 (기존 가드 유지)', function () {
    reachProfileStep();

    $this->post(route('assessment.start', 'OY_MSI'))
        ->assertRedirect(route('oymsi.profile.form', 'OY_MSI'));
});
