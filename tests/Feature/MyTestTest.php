<?php
use App\Models\{Test, TestAttempt};
use App\Services\ScoringService;
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

test('my page lists my submitted attempts', function () {
    $test = Test::where('code','KMSIA-SAMPLE')->with('items')->first();
    $attempt = TestAttempt::create(['test_id'=>$test->id,'guest_token'=>'me','status'=>'submitted','started_at'=>now(),'submitted_at'=>now()]);
    foreach ($test->items as $item) $attempt->answers()->create(['test_item_id'=>$item->id,'value'=>2]);
    app(ScoringService::class)->score($attempt);

    $this->withSession(['guest_token'=>'me'])->get('/my')
        ->assertOk()->assertSee('직장인 마음상태 검사(샘플)');
});

test('my page empty state when none', function () {
    $this->withSession(['guest_token'=>'nobody'])->get('/my')
        ->assertOk()->assertSee('아직 받은 검사가 없어요');
});
