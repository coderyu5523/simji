<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained('test_attempts')->cascadeOnDelete();
            $t->foreignId('test_item_id')->constrained('test_items')->cascadeOnDelete();
            $t->unsignedTinyInteger('value');
            $t->timestamps();
            $t->unique(['attempt_id','test_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
