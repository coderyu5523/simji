<?php
use App\Models\{Test, TestAttempt, User, Voucher};

test('링크 응시가 완료되면 명부에 청소년 닉네임이 응시자로 표시된다 (recipient_name 회귀 방지)', function () {
    // LinkController::start() 는 이제 recipient_name 을 쓰지 않는다(Task 14). 발급 시점 명부 이름
    // 수집 UI 가 아직 없으니, 응시가 끝난 링크 검사권은 recipient_name 이 null 로 남는다 — 명부 칸이
    // recipient_name 만 보면 빈칸이 되는 회귀를 nickname 폴백으로 막는다.
    (new Database\Seeders\OyMsi\TestSeeder())->run();
    (new Database\Seeders\OyMsi\ScoringRuleSeeder())->run();
    $test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $issuer = User::factory()->create();

    $voucher = Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id,
        'source' => 'issued_free', 'status' => 'active', 'issued_at' => now(),
        'access_token' => 'tok-myindex',
    ]);

    $this->post(route('link.age.submit', 'tok-myindex'),
        birthdateFields(now()->subYears(16)->subDay()->format('Y-m-d')));
    $this->post(route('link.start', 'tok-myindex'), ['nickname' => '민들레', 'gender' => 'female', 'agree' => '1'])
        ->assertRedirect();

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->firstOrFail();
    $answers = [];
    foreach ($test->items as $item) $answers[$item->id] = 0;
    $this->post(route('link.submit', ['tok-myindex', $attempt->id]), ['answers' => $answers])
        ->assertRedirect();

    expect($voucher->fresh()->status)->toBe('used');
    expect($voucher->fresh()->recipient_name)->toBeNull(); // 발급 시점 명부 이름 수집 UI 없음(회귀 전제)

    $this->actingAs($issuer)->get('/my')
        ->assertOk()
        ->assertSee('민들레'); // recipient_name 이 없으므로 nickname 폴백이 렌더된 것
});

test('my page lists active vouchers with test name', function () {
    // /my 리디자인(03f1597 "검사권 발급을 심리검사로 이동") 이후, 미발급(access_token 없음) 보유
    // 검사권은 개수만 표시되고 검사명으로 나열되는 건 "발급한 검사권"(access_token 있는 것) 목록이다.
    // 검사명이 보이는 실제 화면(발급 명부)에 맞춰 access_token을 부여한 발급 상태로 만든다.
    $u = User::factory()->create();
    $t = Test::create(['code'=>'MV','room'=>'univ','title_easy'=>'내 마음검사','title_pro'=>'MV','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear(),'access_token'=>'mv-token','assigned_at'=>now()]);
    $this->actingAs($u)->get('/my')->assertOk()->assertSee('내 마음검사')->assertSee('검사권');
});
