<?php
use Illuminate\Support\Facades\Schema;

test('oy_msi 확장 컬럼이 존재한다', function () {
    expect(Schema::hasColumns('tests', [
        'scoring_engine', 'assessment_version', 'min_age', 'max_age', 'guardian_consent_below_age',
    ]))->toBeTrue();
    expect(Schema::hasColumns('test_items', ['item_code', 'scale_code', 'timeframe_code']))->toBeTrue();
    expect(Schema::hasColumn('attempt_answers', 'missing_code'))->toBeTrue();
    expect(Schema::hasColumns('test_attempts', [
        'nickname', 'age_at_test', 'gender', 'assessment_version', 'scoring_version',
    ]))->toBeTrue();
    expect(Schema::hasColumns('test_results', [
        'general_case_code', 'final_case_code', 'safety_level', 'environment_level',
        'score_status', 'engine_result',
    ]))->toBeTrue();
    expect(Schema::hasColumn('scoring_rules', 'version'))->toBeTrue();
    expect(Schema::hasColumns('vouchers', [
        'guardian_consent_confirmed_at', 'guardian_consent_confirmed_by',
    ]))->toBeTrue();
});

test('신규 테이블 3개가 존재한다', function () {
    expect(Schema::hasTable('interpretation_templates'))->toBeTrue();
    expect(Schema::hasTable('report_shares'))->toBeTrue();
    expect(Schema::hasTable('consent_records'))->toBeTrue();
});

test('응답값은 null 을 허용한다 (응답거부)', function () {
    $t = \App\Models\Test::create([
        'code' => 'NULLCHK', 'room' => 'middle', 'title_easy' => 'x', 'title_pro' => 'X',
        'target' => 't', 'duration_min' => 1, 'item_count' => 1, 'areas' => ['A'],
        'result_type' => 'signal', 'description' => 'd', 'status' => 'draft',
    ]);
    $i = $t->items()->create(['no' => 1, 'text' => 'q', 'type' => 'likert4', 'reverse' => false, 'area' => 'A']);
    $a = \App\Models\TestAttempt::create([
        'test_id' => $t->id, 'guest_token' => 'g', 'status' => 'in_progress', 'started_at' => now(),
    ]);

    $ans = $a->answers()->create(['test_item_id' => $i->id, 'value' => null, 'missing_code' => 'PREFER_NOT']);

    expect($ans->fresh()->value)->toBeNull();
    expect($ans->fresh()->missing_code)->toBe('PREFER_NOT');
});
