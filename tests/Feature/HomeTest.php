<?php
use App\Models\Test;
test('home shows rooms and recommended tests', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $this->get('/')
        ->assertOk()
        ->assertSee('마음을 검사하고')
        ->assertSee('대학생')->assertSee('직장인·성인')->assertSee('실버')
        ->assertSee('직장인 마음상태 검사(샘플)');
});
