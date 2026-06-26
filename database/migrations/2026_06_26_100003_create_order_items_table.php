<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('test_id');
            $t->string('product_name');
            $t->unsignedInteger('unit_price');
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->unsignedSmallInteger('credit_qty')->default(1);
            $t->unsignedSmallInteger('valid_days')->default(365);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_items'); }
};
