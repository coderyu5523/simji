<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->unique()->constrained('test_attempts')->cascadeOnDelete();
            $t->json('area_scores'); $t->string('overall_level');
            $t->string('overall_signal');
            $t->json('area_signals'); $t->text('interpretation'); $t->json('recommendations');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
