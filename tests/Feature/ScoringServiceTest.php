<?php
use App\Models\{Test, TestAttempt};
use App\Services\ScoringService;

test('scores attempt with reverse items and assigns worst-area signal', function () {
    $t = Test::create(['code'=>'X','room'=>'worker','title_easy'=>'x','title_pro'=>'X','target'=>'성인','duration_min'=>3,'item_count'=>2,'areas'=>['스트레스','회복탄력성'],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $i1 = $t->items()->create(['no'=>1,'text'=>'스트레스1','type'=>'likert5','reverse'=>false,'area'=>'스트레스']);
    $i2 = $t->items()->create(['no'=>2,'text'=>'회복1','type'=>'likert5','reverse'=>true,'area'=>'회복탄력성']);
    $t->scoringRule()->create(['rules'=>[
        'areas'=>['스트레스'=>['yellow'=>3,'red'=>5],'회복탄력성'=>['yellow'=>3,'red'=>5]],
        'interpretation'=>['green'=>'양호','yellow'=>'관심','red'=>'주의'],
        'recommendations'=>['green'=>['유지'],'yellow'=>['점검'],'red'=>['상담']],
    ]]);
    $a = TestAttempt::create(['test_id'=>$t->id,'guest_token'=>'g','status'=>'in_progress','started_at'=>now()]);
    $a->answers()->create(['test_item_id'=>$i1->id,'value'=>5]); // 스트레스 5 → red
    $a->answers()->create(['test_item_id'=>$i2->id,'value'=>5]); // 회복 reverse: 6-5=1 → green

    $result = app(ScoringService::class)->score($a);

    expect($result->area_scores)->toBe(['스트레스'=>5,'회복탄력성'=>1]);
    expect($result->area_signals)->toBe(['스트레스'=>'red','회복탄력성'=>'green']);
    expect($result->overall_signal)->toBe('red');
    expect($result->recommendations)->toBe(['상담']);
});

test('sample seeder creates a runnable test', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $t = \App\Models\Test::where('code','KMSIA-SAMPLE')->with('items','scoringRule')->first();
    expect($t)->not->toBeNull();
    expect($t->items)->toHaveCount(10);
    expect($t->scoringRule)->not->toBeNull();
});
