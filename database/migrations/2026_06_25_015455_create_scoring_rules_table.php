<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->json('rules'); $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_rules');
    }
};
