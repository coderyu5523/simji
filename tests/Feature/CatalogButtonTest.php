<?php
use App\Models\{Test, Product, User, Voucher};

function showTest(bool $paid): Test {
    $t = Test::create(['code'=>'CB'.($paid?'P':'F'),'room'=>'univ','title_easy'=>'마음검사','title_pro'=>'CB','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>['스트레스'],'result_type'=>'signal','description'=>'d','status'=>'active']);
    if ($paid) Product::create(['test_id'=>$t->id,'name'=>'CB 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    return $t;
}

test('free test shows 검사 시작 to guest', function () {
    $t = showTest(false);
    $this->get("/tests/{$t->code}")->assertOk()->assertSee('검사 시작');
});

test('paid test does not reveal price or purchase button to guest', function () {
    // commit d457889 "검사 상세 가격/단건구매 제거 → 검사권 차감 모델"로 검사 상세에서
    // 가격 표시·단건구매 버튼은 의도적으로 제거됐다(가격은 이제 checkout 페이지에서만 보임).
    // 비로그인 사용자는 무료·유료 구분 없이 "로그인하고 검사 시작"만 보고, 가격/구매 문구는 보지 않는다.
    // 주의: task-0-brief.md는 "가격 노출은 비로그인에서도 보여야 한다"고 전제했지만,
    // 이는 이 커밋으로 이미 뒤집힌 실제 정책과 배치된다 — task-0-report.md에 기록.
    $t = showTest(true);
    $this->get("/tests/{$t->code}")->assertOk()
        ->assertSee('로그인하고 검사 시작')
        ->assertDontSee('9,900')
        ->assertDontSee('구매');
});

test('paid test shows 검사 시작 when user owns a voucher', function () {
    $t = showTest(true);
    $u = User::factory()->create();
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $this->actingAs($u)->get("/tests/{$t->code}")->assertOk()->assertSee('검사 시작');
});
