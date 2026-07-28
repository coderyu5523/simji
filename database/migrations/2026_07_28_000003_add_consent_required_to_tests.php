<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $t) {
            $t->boolean('consent_required')->default(false)->after('guardian_consent_below_age');
        });
    }

    public function down(): void
    {
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn('consent_required'));
    }
};
