<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('provider');
            $t->string('method')->nullable();
            $t->string('pg_tid')->nullable()->unique();
            $t->unsignedInteger('amount');
            $t->string('status')->default('ready');
            $t->timestamp('paid_at')->nullable();
            $t->json('raw_response')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('payments'); }
};
