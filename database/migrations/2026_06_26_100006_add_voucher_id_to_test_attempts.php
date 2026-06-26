<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('test_attempts', function (Blueprint $t) {
            $t->foreignId('voucher_id')->nullable()->after('test_id')->constrained()->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('test_attempts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('voucher_id');
        });
    }
};
