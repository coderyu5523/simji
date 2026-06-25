<?php
use App\Models\{Test, TestAttempt};
use App\Services\ScoringService;
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

test('result page shows overall signal, areas, recommendations', function () {
    $test = Test::where('code','KMSIA-SAMPLE')->with('items')->first();
    $attempt = TestAttempt::create(['test_id'=>$test->id,'guest_token'=>'g-9','status'=>'in_progress','started_at'=>now()]);
    foreach ($test->items as $item) $attempt->answers()->create(['test_item_id'=>$item->id,'value'=>5]);
    $attempt->update(['status'=>'submitted','submitted_at'=>now()]);
    app(ScoringService::class)->score($attempt);

    $this->withSession(['guest_token'=>'g-9'])
        ->get("/result/{$attempt->id}")
        ->assertOk()
        ->assertSee('나의 마음상태 결과')
        ->assertSee('스트레스')
        ->assertSee('추천 솔루션');
});

test('cannot view others result', function () {
    $test = Test::where('code','KMSIA-SAMPLE')->first();
    $attempt = TestAttempt::create(['test_id'=>$test->id,'guest_token'=>'g-owner','status'=>'submitted','started_at'=>now()]);
    app(ScoringService::class)->score($attempt->load('test.items','test.scoringRule'));
    $this->withSession(['guest_token'=>'intruder'])->get("/result/{$attempt->id}")->assertForbidden();
});
