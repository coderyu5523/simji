<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vouchers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('source')->default('purchase');
            $t->string('status')->default('active');
            $t->timestamp('issued_at');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->unsignedBigInteger('used_attempt_id')->nullable();
            $t->timestamps();
            $t->index(['user_id','test_id','status','issued_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('vouchers'); }
};
