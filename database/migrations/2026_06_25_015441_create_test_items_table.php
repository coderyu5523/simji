<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('no');
            $t->text('text'); $t->string('type')->default('likert5');
            $t->json('options')->nullable(); $t->boolean('reverse')->default(false);
            $t->string('area'); $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_items');
    }
};
