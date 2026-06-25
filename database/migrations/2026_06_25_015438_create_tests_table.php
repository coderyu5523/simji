<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('room');
            $t->string('title_easy'); $t->string('title_pro');
            $t->string('target'); $t->unsignedSmallInteger('duration_min');
            $t->unsignedSmallInteger('item_count');
            $t->json('areas'); $t->string('result_type')->default('signal');
            $t->string('thumbnail')->nullable(); $t->text('description');
            $t->string('status')->default('active');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
