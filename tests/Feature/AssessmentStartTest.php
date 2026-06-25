<?php
use App\Models\{Test, TestAttempt};
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

test('consent page shows sensitive-info agreement', function () {
    $this->get('/assessment/KMSIA-SAMPLE/consent')->assertOk()->assertSee('민감정보')->assertSee('동의');
});

test('agree leads to intro', function () {
    $this->post('/assessment/KMSIA-SAMPLE/agree', ['agree' => '1'])
        ->assertRedirect(route('assessment.intro', 'KMSIA-SAMPLE'));
});

test('start creates an attempt and redirects to take', function () {
    $this->withSession(['guest_token' => 'g-123'])
        ->post('/assessment/KMSIA-SAMPLE/start');
    $attempt = TestAttempt::where('guest_token','g-123')->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('in_progress');
});
