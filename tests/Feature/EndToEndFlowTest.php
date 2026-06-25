<?php
use App\Models\Test;
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

test('guest completes the whole assessment journey', function () {
    $this->get('/')->assertOk();
    $this->get('/tests')->assertOk();
    $this->get('/tests/room/worker')->assertSee('직장인 마음상태 검사(샘플)');
    $this->get('/tests/KMSIA-SAMPLE')->assertSee('검사 시작');

    $this->get('/assessment/KMSIA-SAMPLE/consent')->assertOk();
    $this->post('/assessment/KMSIA-SAMPLE/agree', ['agree'=>'1'])->assertRedirect();
    $this->get('/assessment/KMSIA-SAMPLE/intro')->assertOk();

    $start = $this->post('/assessment/KMSIA-SAMPLE/start');
    $attempt = \App\Models\TestAttempt::latest('id')->first();
    $start->assertRedirect(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]));

    $test = Test::where('code','KMSIA-SAMPLE')->with('items')->first();
    $answers = [];
    foreach ($test->items as $item) $answers[$item->id] = 4;
    $this->post("/assessment/KMSIA-SAMPLE/take/{$attempt->id}", ['answers'=>$answers])
        ->assertRedirect(route('result.show', $attempt->id));

    $this->get(route('result.show', $attempt->id))->assertOk()->assertSee('나의 마음상태 결과');
    $this->get('/my')->assertSee('직장인 마음상태 검사(샘플)');
});
