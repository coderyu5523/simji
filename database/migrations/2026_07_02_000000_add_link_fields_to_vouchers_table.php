<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $t) {
            $t->string('access_token', 64)->nullable()->unique()->after('source'); // 링크 토큰 (발급 시 부여)
            $t->string('recipient_name', 100)->nullable()->after('access_token');   // 응시자가 입력
            $t->string('recipient_age', 20)->nullable()->after('recipient_name');   // 응시자가 입력(선택)
            $t->boolean('result_visible')->default(true)->after('recipient_age');    // 열람 승인 여부
            $t->timestamp('assigned_at')->nullable()->after('issued_at');            // 발급 시각
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $t) {
            $t->dropColumn(['access_token', 'recipient_name', 'recipient_age', 'result_visible', 'assigned_at']);
        });
    }
};
