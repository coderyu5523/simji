<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // 관리자 권한. user_type(personal|institution)은 가입 유형이라는 다른 축이므로
            // 거기에 값을 끼우지 않고 직교하는 플래그로 둔다.
            $t->boolean('is_admin')->default(false)->after('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('is_admin');
        });
    }
};
