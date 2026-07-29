<?php
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => bcrypt('secret1234')]);
});

function loginAs(array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->post('/login', array_merge([
        'email' => test()->user->email,
        'password' => 'secret1234',
    ], $extra));
}

test('next 없이 로그인하면 기존대로 내 검사함으로 간다', function () {
    loginAs()->assertRedirect(route('my.index', absolute: false));
});

test('로그인 화면을 next 와 함께 열면 로그인 후 그 주소로 돌아간다', function () {
    $this->get('/login?next='.urlencode('/tests/OY_MSI'))->assertOk();

    loginAs()->assertRedirect('/tests/OY_MSI');
});

// 오픈 리다이렉트 차단 — next 는 사용자가 주소창에서 조작할 수 있는 값이다.
test('외부 도메인으로 가는 next 는 무시한다', function () {
    $this->get('/login?next='.urlencode('https://evil.example.com/steal'))->assertOk();

    loginAs()->assertRedirect(route('my.index', absolute: false));
});

test('프로토콜 상대 주소(//evil.com)도 무시한다', function () {
    $this->get('/login?next='.urlencode('//evil.example.com/steal'))->assertOk();

    loginAs()->assertRedirect(route('my.index', absolute: false));
});

test('같은 사이트의 절대 주소는 허용한다', function () {
    $this->get('/login?next='.urlencode(url('/tests/OY_MSI')))->assertOk();

    loginAs()->assertRedirect('/tests/OY_MSI');
});

// auth 미들웨어가 막아서 로그인으로 보낸 경우는 프레임워크가 intended 를 저장한다.
// next 지원이 그 동작을 깨뜨리지 않아야 한다.
test('auth 미들웨어에 막혀 로그인한 경우 원래 가려던 곳으로 돌아간다', function () {
    $this->get('/profile')->assertRedirect(route('login'));

    loginAs()->assertRedirect('/profile');
});

test('상단 메뉴의 로그인 링크는 지금 보던 페이지를 next 로 달고 간다', function () {
    $this->get('/support')
        ->assertOk()
        ->assertSee('/login?next=%2Fsupport', escape: false);
});

test('로그인·회원가입 화면에서는 로그인 링크에 next 를 달지 않는다', function () {
    // 로그인 화면 자신을 next 로 넣으면 로그인 후 다시 로그인 화면으로 간다.
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('next=%2Flogin', escape: false);

    $this->get('/register')
        ->assertOk()
        ->assertDontSee('next=%2Fregister', escape: false);
});

test('검사 상세의 비로그인 버튼에 next 가 붙어 있다', function () {
    (new Database\Seeders\OyMsi\TestSeeder())->run();
    (new Database\Seeders\OyMsi\ScoringRuleSeeder())->run();

    $this->get(route('catalog.show', 'OY_MSI'))
        ->assertOk()
        ->assertSee('next=', escape: false);
});
