<?php
test('home page loads', function () {
    $this->get('/')->assertOk()->assertSee('심지');
});
