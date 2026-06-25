<?php
use App\Models\{Test, TestItem, TestAttempt};

test('test has items and casts areas to array', function () {
    $t = Test::create([
        'code' => 'SMP', 'room' => 'worker', 'title_easy' => '샘플', 'title_pro' => 'SAMPLE',
        'target' => '성인', 'duration_min' => 5, 'item_count' => 2,
        'areas' => ['스트레스','우울'], 'result_type' => 'signal', 'description' => 'desc', 'status' => 'active',
    ]);
    $t->items()->create(['no' => 1, 'text' => 'Q1', 'type' => 'likert5', 'reverse' => false, 'area' => '스트레스']);
    expect($t->areas)->toBe(['스트레스','우울']);
    expect($t->items)->toHaveCount(1);
});

test('attempt links to answers', function () {
    $t = Test::create(['code'=>'A','room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>1,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $a = TestAttempt::create(['test_id'=>$t->id,'guest_token'=>'g','status'=>'in_progress','started_at'=>now()]);
    expect($a->test->id)->toBe($t->id);
});
