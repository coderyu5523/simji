<?php
test('home shows all five rooms and updated copy', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $this->get('/')
        ->assertOk()
        ->assertSee('마음을 검사하고')
        ->assertSee('초등학생')->assertSee('중고등학생')
        ->assertSee('대학생')->assertSee('직장인·성인')->assertSee('실버')
        ->assertSee('초등학생부터 실버');
});
