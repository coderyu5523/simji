<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $t) {
            $t->string('scoring_engine', 30)->default('signal')->after('result_type');
            $t->string('assessment_version', 30)->default('1.0.0')->after('scoring_engine');
            $t->unsignedSmallInteger('min_age')->nullable()->after('target');
            $t->unsignedSmallInteger('max_age')->nullable()->after('min_age');
            $t->unsignedSmallInteger('guardian_consent_below_age')->nullable()->after('max_age');
        });

        Schema::table('test_items', function (Blueprint $t) {
            $t->string('item_code', 20)->nullable()->after('no');
            $t->string('scale_code', 30)->nullable()->after('type');
            $t->string('timeframe_code', 30)->nullable()->after('scale_code');
            $t->unique(['test_id', 'item_code']);
        });

        Schema::table('attempt_answers', function (Blueprint $t) {
            $t->unsignedTinyInteger('value')->nullable()->change();
            $t->string('missing_code', 30)->nullable()->after('value');
        });

        Schema::table('test_attempts', function (Blueprint $t) {
            $t->string('nickname', 50)->nullable()->after('guest_token');
            $t->unsignedSmallInteger('age_at_test')->nullable()->after('nickname');
            $t->string('gender', 20)->nullable()->after('age_at_test');
            $t->string('assessment_version', 30)->nullable()->after('status');
            $t->string('scoring_version', 30)->nullable()->after('assessment_version');
        });

        Schema::table('test_results', function (Blueprint $t) {
            $t->string('general_case_code', 5)->nullable()->after('overall_signal');
            $t->string('final_case_code', 5)->nullable()->after('general_case_code');
            $t->string('safety_level', 2)->nullable()->after('final_case_code');
            $t->string('environment_level', 2)->nullable()->after('safety_level');
            $t->string('score_status', 20)->default('COMPLETE')->after('environment_level');
            $t->json('engine_result')->nullable()->after('recommendations');
        });

        Schema::table('scoring_rules', function (Blueprint $t) {
            $t->string('version', 30)->default('1.0.0')->after('test_id');
        });

        Schema::table('vouchers', function (Blueprint $t) {
            $t->timestamp('guardian_consent_confirmed_at')->nullable()->after('result_visible');
            $t->foreignId('guardian_consent_confirmed_by')->nullable()
              ->after('guardian_consent_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('guardian_consent_confirmed_by');
            $t->dropColumn('guardian_consent_confirmed_at');
        });
        Schema::table('scoring_rules', fn (Blueprint $t) => $t->dropColumn('version'));
        Schema::table('test_results', fn (Blueprint $t) => $t->dropColumn([
            'general_case_code', 'final_case_code', 'safety_level',
            'environment_level', 'score_status', 'engine_result',
        ]));
        Schema::table('test_attempts', fn (Blueprint $t) => $t->dropColumn([
            'nickname', 'age_at_test', 'gender', 'assessment_version', 'scoring_version',
        ]));
        Schema::table('attempt_answers', function (Blueprint $t) {
            $t->dropColumn('missing_code');
            $t->unsignedTinyInteger('value')->nullable(false)->change();
        });
        Schema::table('test_items', function (Blueprint $t) {
            $t->dropUnique(['test_id', 'item_code']);
            $t->dropColumn(['item_code', 'scale_code', 'timeframe_code']);
        });
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn([
            'scoring_engine', 'assessment_version', 'min_age', 'max_age', 'guardian_consent_below_age',
        ]));
    }
};
