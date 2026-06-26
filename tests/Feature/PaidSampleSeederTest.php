<?php
use App\Models\Test;

test('paid sample seeder creates a paid test with product', function () {
    $this->seed(\Database\Seeders\PaidSampleSeeder::class);
    $t = Test::where('code','KPAID-SAMPLE')->first();
    expect($t)->not->toBeNull();
    expect($t->isPaid())->toBeTrue();
    expect($t->activeProduct()->price)->toBe(9900);
});
