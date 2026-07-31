<?php
use App\Models\Test;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->user = User::factory()->create();
});

function birthdateAged(int $age): string
{
    return now()->subYears($age)->subDays(1)->format('Y-m-d');
}

test('대상 연령이 아니어서 막히면 연령 입력으로 되돌아갈 링크가 있다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateAged(19)))
        ->assertOk()
        ->assertSee(route('oymsi.age.form', 'OY_MSI'), escape: false);
});

test('만 13세 개인 경로 차단에도 연령 입력으로 되돌아갈 링크가 있다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), birthdateFields(birthdateAged(13)))
        ->assertOk()
        ->assertSee(route('oymsi.age.form', 'OY_MSI'), escape: false);
});

test('링크 경로 차단은 개인 경로가 아니라 그 링크의 연령 입력으로 돌아간다', function () {
    Voucher::create([
        'user_id' => $this->user->id, 'test_id' => $this->test->id,
        'source' => 'issued_free', 'status' => 'active', 'issued_at' => now(),
        'access_token' => 'tok-retry',
    ]);

    $res = $this->post(route('link.age.submit', 'tok-retry'), birthdateFields(birthdateAged(13)));
    $res->assertOk();
    $res->assertSee(route('link.age.form', 'tok-retry'), escape: false);
    $res->assertDontSee(route('oymsi.age.form', 'OY_MSI'), escape: false);
});
