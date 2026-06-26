<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedInteger('price');
            $t->unsignedSmallInteger('credit_qty')->default(1);
            $t->unsignedSmallInteger('valid_days')->default(365);
            $t->string('status')->default('active');
            $t->timestamps();
            $t->index(['test_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
