<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_no')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('pending');
            $t->unsignedInteger('total_amount');
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('canceled_at')->nullable();
            $t->timestamps();
            $t->index(['user_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
