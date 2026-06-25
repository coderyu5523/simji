<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('guest_token')->nullable();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->enum('status', ['in_progress','submitted'])->default('in_progress');
            $t->timestamp('started_at')->nullable(); $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
    }
};
