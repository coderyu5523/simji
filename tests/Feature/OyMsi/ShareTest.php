<?php

use App\Models\ReportShare;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ScoringService;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TemplateSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    (new TemplateSeeder())->run();
    $this->user = User::factory()->create();
});

// completedAttempt() 는 tests/Pest.php 에 있다 (ResultScreenTest 와 공용).

// ── 브리프 규정 테스트 ──────────────────────────────────────────────────────

test('S0 이면 공유 링크가 바로 만들어진다', function () {
    $attempt = completedAttempt([], $this->user);

    $this->actingAs($this->user)
        ->post(route('oymsi.share.create', $attempt->id))
        ->assertOk()
        ->assertSee('/r/');

    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();
    expect($share->audience)->toBe('guardian');
    expect($share->source)->toBe('youth_self');
    expect($share->expires_at->isFuture())->toBeTrue();
});

test('S2 이상이면 공유 화면이 연결 안내를 먼저 보여준다', function () {
    $attempt = completedAttempt(['SAF01' => 2], $this->user);

    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertOk()
        ->assertSee('먼저 이야기할 사람')
        ->assertSee('109');
});

test('공유 링크로 로그인 없이 보호자용 결과를 본다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->post(route('logout'));
    $res = $this->get(route('oymsi.share.view', $share->token));

    $res->assertOk();
    $res->assertSee('피해야 할 반응');
    expect($share->fresh()->viewed_at)->not->toBeNull();
});

test('보호자용에는 자해·자살 문항별 응답이 없다', function () {
    $attempt = completedAttempt(['SAF01' => 3, 'SAF03' => 3], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $html = $this->get(route('oymsi.share.view', $share->token))->getContent();
    expect($html)->not->toContain('SAF01');
    expect($html)->not->toContain('SAF03');
    expect($html)->not->toContain('목숨을 끊');
});

test('철회한 링크는 열리지 않는다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('oymsi.share.revoke', $attempt->id))
        ->assertRedirect();

    $this->get(route('oymsi.share.view', $share->token))->assertNotFound();
});

test('만료된 링크는 열리지 않는다', function () {
    $attempt = completedAttempt([], $this->user);
    $share = ReportShare::create([
        'attempt_id' => $attempt->id, 'audience' => 'guardian',
        'token' => 'expiredtoken', 'source' => 'youth_self',
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('oymsi.share.view', $share->token))->assertNotFound();
});

test('남의 결과를 공유할 수 없다', function () {
    $attempt = completedAttempt([], $this->user);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->post(route('oymsi.share.create', $attempt->id))
        ->assertForbidden();
});

// ── 토큰 (추측 불가능성) ────────────────────────────────────────────────────

test('토큰은 길고 예측 불가능하며 매번 다르다', function () {
    $a = completedAttempt([], $this->user);
    $b = completedAttempt([], $this->user);

    $this->actingAs($this->user)->post(route('oymsi.share.create', $a->id));
    $this->actingAs($this->user)->post(route('oymsi.share.create', $b->id));

    $tokens = ReportShare::pluck('token')->all();
    expect($tokens)->toHaveCount(2);
    expect($tokens[0])->not->toBe($tokens[1]);
    foreach ([$a, $b] as $i => $attempt) {
        $token = $tokens[$i];
        expect(strlen($token))->toBeGreaterThanOrEqual(40);
        expect($token)->toMatch('/^[A-Za-z0-9]+$/');
        // 순차 ID 나 그것을 뻔하게 변형한 값이 아니다
        expect($token)->not->toBeIn([
            (string) $attempt->id,
            md5((string) $attempt->id),
            sha1((string) $attempt->id),
            base64_encode((string) $attempt->id),
        ]);
    }
});

test('같은 결과를 다시 공유해도 살아 있는 링크는 하나다', function () {
    // 중복 제출로 유출 대상 비밀이 늘어나지 않게 한다. 철회하면 새로 만들 수 있다.
    $attempt = completedAttempt([], $this->user);

    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertOk();
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertOk();
    expect(ReportShare::where('attempt_id', $attempt->id)->count())->toBe(1);

    $this->actingAs($this->user)->post(route('oymsi.share.revoke', $attempt->id));
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertOk();
    expect(ReportShare::where('attempt_id', $attempt->id)->whereNull('revoked_at')->count())->toBe(1);
    expect(ReportShare::where('attempt_id', $attempt->id)->count())->toBe(2);
});

test('보호자 화면은 색인·캐시되지 않는다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $res = $this->get(route('oymsi.share.view', $share->token))->assertOk();
    expect($res->headers->get('X-Robots-Tag'))->toContain('noindex');
    expect($res->headers->get('Cache-Control'))->toContain('no-store');
});

test('존재하지 않는 토큰은 404 다', function () {
    $this->get(route('oymsi.share.view', 'nope-nope-nope'))->assertNotFound();
});

test('공유하지 않은 남의 결과는 열람도 철회도 할 수 없다', function () {
    $attempt = completedAttempt([], $this->user);
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('oymsi.share.form', $attempt->id))->assertForbidden();
    $this->actingAs($other)->post(route('oymsi.share.revoke', $attempt->id))->assertForbidden();
    expect(ReportShare::count())->toBe(0);
});

test('로그인하지 않으면 공유를 만들 수 없다', function () {
    $attempt = completedAttempt([], $this->user);

    $this->get(route('oymsi.share.form', $attempt->id))->assertRedirect(route('login'));
    $this->post(route('oymsi.share.create', $attempt->id))->assertRedirect(route('login'));
    expect(ReportShare::count())->toBe(0);
});

// ── 새 진입점이 기존 게이트를 우회하지 않는다 ───────────────────────────────

test('OY_MSI 가 아닌 검사는 공유 진입점을 쓸 수 없다', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $test = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'user_id' => $this->user->id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
    ]);
    foreach ($test->items as $item) {
        $attempt->answers()->create(['test_item_id' => $item->id, 'value' => 5]);
    }
    app(ScoringService::class)->score($attempt);

    $this->actingAs($this->user)->get(route('oymsi.share.form', $attempt->id))->assertNotFound();
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertNotFound();
    expect(ReportShare::count())->toBe(0);
});

test('채점 결과가 없는 응시는 공유할 수 없다', function () {
    $test = Test::where('code', 'OY_MSI')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)->get(route('oymsi.share.form', $attempt->id))->assertNotFound();
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertNotFound();
});

test('발급자가 열람을 막아둔 결과는 공유할 수 없고 이미 만든 링크도 닫힌다', function () {
    // result.show 의 열람 통제(voucher.result_visible=false)를 공유가 우회하면 안 된다.
    $test = Test::where('code', 'OY_MSI')->firstOrFail();
    $issuer = User::factory()->create();
    $voucher = Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id, 'status' => 'used',
        'source' => 'purchase', 'issued_at' => now(), 'expires_at' => now()->addYear(),
        'result_visible' => true,
    ]);
    $attempt = completedAttempt([], $this->user);
    $attempt->update(['voucher_id' => $voucher->id]);

    // 승인 상태에서는 만들 수 있다
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertOk();
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();
    $this->get(route('oymsi.share.view', $share->token))->assertOk();

    // 발급자가 열람을 '대기'로 되돌리면 새 발급도, 이미 나간 링크도 닫힌다
    $voucher->update(['result_visible' => false]);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertForbidden();
    $this->get(route('oymsi.share.view', $share->token))->assertNotFound();
});

// ── Important 2 — 철회는 열람 비공개 상태에서도 언제나 가능하다 ─────────────

test('열람이 비공개로 바뀌어도 청소년은 자기 링크를 철회할 수 있다', function () {
    // 막아 두면, 기관이 다시 공개하는 순간 청소년이 취소할 기회를 갖지 못한 채
    // 옛 링크가 되살아난다.
    $test = Test::where('code', 'OY_MSI')->firstOrFail();
    $issuer = User::factory()->create();
    $voucher = Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id, 'status' => 'used',
        'source' => 'purchase', 'issued_at' => now(), 'expires_at' => now()->addYear(),
        'result_visible' => true,
    ]);
    $attempt = completedAttempt([], $this->user);
    $attempt->update(['voucher_id' => $voucher->id]);

    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertOk();
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    // 기관이 비공개로 되돌린다 → 링크는 이미 404 지만 철회는 여전히 가능해야 한다
    $voucher->update(['result_visible' => false]);
    $this->actingAs($this->user)
        ->post(route('oymsi.share.revoke', $attempt->id))
        ->assertRedirect();
    expect($share->fresh()->revoked_at)->not->toBeNull();

    // 기관이 다시 공개해도 철회된 링크는 되살아나지 않는다
    $voucher->update(['result_visible' => true]);
    $this->get(route('oymsi.share.view', $share->token))->assertNotFound();
});

// ── Important 3 — 차단은 유지하되 이유가 보이는 화면 ────────────────────────

test('미채점이면 이유가 보이는 안내 화면이 나온다 (404 유지)', function () {
    $test = Test::where('code', 'OY_MSI')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertNotFound()                          // 차단은 그대로
        ->assertSee('아직 결과가 준비되지 않았어')   // 이유가 보인다
        ->assertSee('1388');
});

test('열람 대기면 이유가 보이는 안내 화면이 나온다 (403 유지)', function () {
    $test = Test::where('code', 'OY_MSI')->firstOrFail();
    $issuer = User::factory()->create();
    $voucher = Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id, 'status' => 'used',
        'source' => 'purchase', 'issued_at' => now(), 'expires_at' => now()->addYear(),
        'result_visible' => false,
    ]);
    $attempt = completedAttempt([], $this->user);
    $attempt->update(['voucher_id' => $voucher->id]);

    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertForbidden()                          // 차단은 그대로
        ->assertSee('지금은 결과를 공유할 수 없어')   // 이유가 보인다
        ->assertSee('1388');
});

// ── S2 이상 분기 (spec §5.3) ────────────────────────────────────────────────

test('S0·E0 이면 공유 화면은 연결 안내가 아니라 공유 버튼을 먼저 보여준다', function () {
    $attempt = completedAttempt([], $this->user);

    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertOk()
        ->assertSee('보호자와 공유할까?')
        ->assertSee('공유 링크 만들기')
        ->assertDontSee('먼저 이야기할 사람');
});

test('환경위험 E2 만으로도 연결 안내가 먼저 뜬다', function () {
    // 브리프의 분기 기준은 max(자살안전, 환경위험) >= 2 다. 학대·폭력 상황에서
    // 보호자 공유를 1순위로 들이밀지 않기 위한 분기이므로 환경축도 포함해야 한다.
    $attempt = completedAttempt(['TRM06' => 2], $this->user);
    expect($attempt->result->engine_result['safety']['environment_level'])->toBe('E2');
    expect($attempt->result->engine_result['safety']['suicide_level'])->toBe('S0');

    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertOk()
        ->assertSee('먼저 이야기할 사람');
});

test('S2 이상이어도 공유는 2차 선택으로 남아 있다', function () {
    // 연결 안내가 먼저지만 공유 자체를 막지는 않는다 — 눈에 덜 띄는 2차 선택.
    $attempt = completedAttempt(['SAF01' => 2], $this->user);

    $html = $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))->getContent();

    expect(mb_strpos($html, '먼저 이야기할 사람'))
        ->toBeLessThan(mb_strpos($html, '그래도 보호자와 공유할래'));

    $this->actingAs($this->user)
        ->post(route('oymsi.share.create', $attempt->id))
        ->assertOk();
});

// ── 인계 ① 보호자 화면의 종합 안전 보정 문구 ───────────────────────────────

test('안전 경보가 있으면 보호자 화면 종합 블록에 안전 우선 안내가 붙는다', function () {
    // DEP 만점(빨강) + S2 인데 종합은 초록으로 나온다 — 007 §246 상쇄 상황.
    $attempt = completedAttempt([
        'DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3,
        'SAF01' => 2,
    ], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->get(route('oymsi.share.view', $share->token))
        ->assertOk()
        ->assertSee('이 종합 신호에는 안전에 관한 문항이 포함되어 있지 않습니다. 위의 안전 안내를 먼저 읽어 주십시오.');
});

test('S0·E0 이면 보호자 화면에 안전 우선 안내가 붙지 않는다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->get(route('oymsi.share.view', $share->token))
        ->assertOk()
        ->assertDontSee('위의 안전 안내를 먼저 읽어 주십시오')
        ->assertDontSee('먼저 읽어야 할 안내');   // 안전 섹션 자체가 없다
});

// ── 인계 ② 보호자 화면에도 SAF 원점수 비노출 ───────────────────────────────

test('보호자 화면에 SAF 요인 이름·원점수가 없다', function () {
    $attempt = completedAttempt(
        ['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3, 'SAF04' => 2, 'SAF05' => 2, 'SAF06' => 2],
        $this->user
    );
    expect($attempt->result->engine_result['factors']['SAF']['raw'])->not->toBeNull(); // 엔진은 계산한다

    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $res = $this->get(route('oymsi.share.view', $share->token))->assertOk();
    $res->assertDontSee('자해·자살 안전')      // SAF 요인 이름
        ->assertDontSee('area_scores')
        ->assertDontSee('engine_result')
        ->assertDontSee('"SAF"', false)
        ->assertSee('우울');                    // 나머지 9요인은 정상 렌더 (빈 화면이 아님)
});

// ── 인계 ③ 내부 솔루션 코드는 보호자에게 보이지 않는다 ─────────────────────

test('보호자 화면에 내부 솔루션 코드가 노출되지 않는다', function () {
    $attempt = completedAttempt(['SAF01' => 3, 'DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $html = $this->get(route('oymsi.share.view', $share->token))->getContent();
    expect($html)->not->toContain('SOL_');     // 코드가 아니라 사람이 읽는 제목만 나온다
    expect($html)->toContain('개인 안전계획·즉시 연결');
});

// ── 인계 ④ ENV(환경위험) 문안 노출 위치 ────────────────────────────────────

/** 공유 링크를 만들고 보호자 화면 HTML 을 돌려준다 ($t 는 테스트 케이스 자신) */
function guardianHtml($t, TestAttempt $attempt, User $user): string
{
    $t->actingAs($user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    return $t->get(route('oymsi.share.view', $share->token))->assertOk()->getContent();
}

test('환경위험 보호자 문안은 안전 섹션 안에서만 렌더된다', function () {
    $attempt = completedAttempt(['TRM06' => 3], $this->user);   // E3, S0
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $html = $this->get(route('oymsi.share.view', $share->token))
        ->assertOk()
        ->assertSee('주변 환경과 관련해 확인이 필요한 응답이 있었습니다.')
        ->assertSee('주변 환경 안전')
        ->getContent();

    // 안전 섹션(맨 위) 안이다 — 종합 블록보다 앞
    expect(mb_strpos($html, '주변 환경 안전'))->toBeLessThan(mb_strpos($html, '종합 마음상태'));
});

// ── Critical 1 — 보호자 화면에 상담자용 보호절차 지침이 없다 ────────────────

test('환경위험(ENV) 문안에서 담당자용 보호절차 지침이 빠졌다', function () {
    // ★ 범위 주의: 이 테스트의 픽스처는 TRM06 만 올리므로 TRM 요인 자체는 GREEN 이고,
    //   TRM.RED 문안은 애초에 렌더되지 않는다. 즉 여기서 검증되는 것은 ENV(E2·E3) 문안뿐이다.
    //   TRM/FAM 의 RED 문안은 아래 '보호자 화면에 …(TRM·FAM 이 실제로 RED 일 때)' 가 맡는다.
    foreach ([2, 3] as $raw) {
        $attempt = completedAttempt(['TRM06' => $raw], $this->user);
        $html = guardianHtml($this, $attempt, $this->user);

        foreach ([
            '가해 가능성이 있는 보호자',
            '보호자 통보가 위험을 높일 가능성',
            '증거를 보존하고',
            '신고·보호절차',
            '비공개 면담',
        ] as $staffOnly) {
            expect($html)->not->toContain($staffOnly);
        }

        // ↑ 목록에 있던 '가해 가능성이 있는 사람과 청소년을 분리' 는 뺐다.
        //   그 문장은 TRM.RED.actions 에 **의도적으로 남긴** 것이라(006 8페이지, 주체가
        //   보호자로 성립해 2026-07-28 제거 대상이 아니었다) 픽스처가 TRM RED 로 바뀌는 순간
        //   정상 문안을 회귀로 오탐한다. 이 테스트의 목적은 ENV 축 검증이므로,
        //   아래처럼 "ENV 문안이 실제로 무엇을 말하는가"를 직접 단언하는 것으로 대체한다.
        expect($html)->toContain('주변 환경 안전');                      // ENV 블록이 렌더됐고
        expect($html)->toContain('전문기관의 상담을 받아 보시기를 권합니다'); // 중립 안내로 채워져 있다
        expect($html)->toContain('1388');
    }
});

// ── Task 18b — TRM 이 실제로 RED 로 렌더될 때의 보호자 화면 ──────────────────

/**
 * TRM 만 RED(원점수 12/18)로 만든다. severity_weight RED=200 이므로 상위3에 반드시 들고,
 * 그 결과 result.GUARDIAN.TRM.RED.{meaning,actions,avoid} 가 실제로 화면에 찍힌다.
 * SAF·TRM06·FAM05 는 0 이라 S0·E0 다(안전 패널 문안과 섞이지 않는다).
 *
 * ★ FAM 은 일부러 올리지 않는다 — FAM 이 RED 면 공유 자체가 차단되어(2026-07-28 결정,
 *   ShareController::familyRiskBlocksShare) 보호자 화면에 도달할 수 없기 때문이다.
 *   그 차단은 아래 '가족·보호환경(FAM)' 묶음이 따로 검증한다.
 */
function trmRedAttempt(User $user): TestAttempt
{
    return completedAttempt([
        'TRM01' => 3, 'TRM02' => 3, 'TRM03' => 3, 'TRM04' => 3,
    ], $user);
}

test('TRM RED 프로필에서 해당 문안이 실제로 렌더된다 (부재 단언의 전제)', function () {
    // ★ 이 테스트가 없으면 아래 부재 단언이 공허해진다.
    //   "문장이 없다"는 "그 문안 자체가 렌더되지 않았다"로도 참이 되기 때문이다.
    $attempt = trmRedAttempt($this->user);

    $engine = $attempt->result->engine_result;
    expect($engine['factors']['TRM']['band'])->toBe('RED');
    expect($engine['factors']['FAM']['band'])->not->toBe('RED');   // 공유가 차단되지 않는 조건
    expect($engine['safety']['suicide_level'])->toBe('S0');
    expect($engine['safety']['environment_level'])->toBe('E0');
    expect(collect($engine['priority'])->pluck('factor')->all())->toContain('TRM');

    $html = guardianHtml($this, $attempt, $this->user);

    // TRM.RED 세 칸이 모두 화면에 있다
    expect($html)->toContain('심각한 불안과 안전감 저하를 경험할 수 있습니다');   // meaning
    expect($html)->toContain('현재 실제 위험이 있는지 먼저 확인합니다');           // actions 첫 줄
    expect($html)->toContain('청소년의 진술을 비난하거나 의심하지 않습니다');      // avoid (제거 후 남은 줄)
});

test('TRM 이 RED 여도 보호자 화면에 담당자용 위험 문장이 나오지 않는다', function () {
    // 위 테스트로 TRM.RED 문안이 실제 렌더되는 것이 확인된 조건에서의 부재 단언이다.
    $html = guardianHtml($this, trmRedAttempt($this->user), $this->user);

    // ↓ TRM.RED 계열 — 이 픽스처에서 실제로 렌더되는 문안이라 단언에 힘이 있다.
    foreach ([
        '가해 가능성이 있는 보호자',   // (a) 제거 #1 — TRM.RED.avoid 에서 뺀 줄
        '제3의 전문기관을 우선',       // (b) 제거 #2 — TRM.RED.actions 에서 뺀 줄
        '신고·보호절차를 시행',        // 라운드1 에서 ENV 문안에서 뺀 줄
        '보호자 통보가 위험을 높일 가능성',
    ] as $staffOnly) {
        expect($html)->not->toContain($staffOnly);
    }

    // ↓ FAM.RED 계열 — ★ 이 픽스처(FAM=GREEN)에서는 **정의상 참**이라 회귀를 잡지 못한다.
    //   솔직히 적어 둔다: FAM.RED 문안은 어떤 보호자 경로로도 렌더될 수 없다.
    //     (1) 문장 자체를 시더에서 제거했고(제거 #3, TemplateSeeder FAM.RED.actions),
    //     (2) FAM 이 RED 면 공유 발급·열람이 모두 차단된다(ShareController::familyRiskBlocksShare).
    //   즉 이중으로 닫혀 있어 "화면에 없다"를 렌더 조건과 함께 단언할 방법이 없다.
    //   실효 있는 회귀 방어는 시드 레벨에서 한다 —
    //   TemplateCompletenessTest > '보호자 문안에 담당자용 위험 문장이 남아 있지 않다'.
    //   (task-18b 리포트 §5 의 mutation check 는 픽스처 재구성 이전 실행분이다. §15 참조)
    foreach ([
        '결과공유를 제한',              // (b) 제거 #3 — FAM.RED.actions 에서 뺀 줄
        '가족이 안전한 보호자원인지',   // 시더에 애초에 전사되지 않은 003 Ⅶ.6 문장
    ] as $unreachable) {
        expect($html)->not->toContain($unreachable);
    }
});

test('청소년용 TRM·FAM RED 문안은 그대로다 — 수신자가 맞는 쪽은 손대지 않았다', function () {
    // 청소년 본인 결과 화면(result.show)에는 FAM 차단이 걸리지 않는다 — 차단 대상은 보호자 공유다.
    $attempt = completedAttempt([
        'TRM01' => 3, 'TRM02' => 3, 'TRM03' => 3, 'TRM04' => 3,
        'FAM01' => 3, 'FAM02' => 3, 'FAM03' => 3, 'FAM04' => 3,
    ], $this->user);
    expect($attempt->result->engine_result['factors']['TRM']['band'])->toBe('RED');
    expect($attempt->result->engine_result['factors']['FAM']['band'])->toBe('RED');

    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('혼자 해결하거나 가해 가능성이 있는 사람과 직접 맞서지 않기')   // YOUTH.TRM.RED.actions
        ->assertSee('보호자에게 알리는 것이 위험하다면 상담자에게 먼저 말하기')      // YOUTH.TRM.RED.actions
        ->assertSee('보호자에게 결과를 알려도 안전한지 상담자에게 말하기');          // YOUTH.FAM.RED.actions
});

// ── 가족·보호환경(FAM) RED — 보호자 공유 차단 (2026-07-28 결정) ─────────────

/** FAM 만 RED(원점수 12/18). TRM06·FAM05 는 0 이라 S0·E0 — 차단 사유가 FAM 하나로 특정된다. */
function famRedAttempt(User $user): TestAttempt
{
    return completedAttempt([
        'FAM01' => 3, 'FAM02' => 3, 'FAM03' => 3, 'FAM04' => 3,
    ], $user);
}

test('FAM RED 픽스처가 실제로 FAM 을 RED 로 만든다 (차단 단언의 전제)', function () {
    // ★ 픽스처가 정말 RED 인지 먼저 못 박는다. GREEN 픽스처로 차단을 단언하면
    //   "차단됐다"가 아니라 "애초에 조건이 아니었다"를 통과시키게 된다(옛 ENV 테스트의 전례).
    $engine = famRedAttempt($this->user)->result->engine_result;

    expect($engine['factors']['FAM']['band'])->toBe('RED');
    expect((float) $engine['factors']['FAM']['raw'])->toBe(12.0);   // 밴드 컷오프 RED=11 이상
    expect($engine['safety']['suicide_level'])->toBe('S0');    // S2 분기와 겹치지 않는다
    expect($engine['safety']['environment_level'])->toBe('E0'); // E2 분기와도 겹치지 않는다
});

test('FAM 이 RED 면 공유 링크를 만들 수 없고 이유가 보인다', function () {
    $attempt = famRedAttempt($this->user);

    // 공유 화면 진입부터 막힌다 — "그래도 공유할래" 버튼이 있는 화면 자체가 뜨지 않는다.
    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertStatus(403)
        ->assertSee('이 결과는 상담자와 먼저 이야기해 보자')
        ->assertSee('1388')
        ->assertDontSee('공유 링크 만들기')
        ->assertDontSee('그래도 보호자와 공유할래');

    // POST 를 직접 쏴도 막힌다(화면만 가린 것이 아니다)
    $this->actingAs($this->user)
        ->post(route('oymsi.share.create', $attempt->id))
        ->assertStatus(403)
        ->assertSee('이 결과는 상담자와 먼저 이야기해 보자');

    expect(ReportShare::where('attempt_id', $attempt->id)->count())->toBe(0);
});

test('FAM 이 RED 로 바뀌면 이미 나간 링크도 열리지 않는다', function () {
    // FAM 이 GREEN 일 때 정상 발급 → 응답 수정 후 재채점으로 FAM 이 RED 가 되는 시나리오.
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3], $this->user);
    expect($attempt->result->engine_result['factors']['FAM']['band'])->not->toBe('RED');

    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id))->assertOk();
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();
    $this->get(route('oymsi.share.view', $share->token))->assertOk();   // 이 시점에는 열린다

    // 재채점 — FAM 을 RED 로 올린다
    $test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    foreach (['FAM01', 'FAM02', 'FAM03', 'FAM04'] as $code) {
        $itemId = $test->items->firstWhere('item_code', $code)->id;
        $attempt->answers()->where('test_item_id', $itemId)->update(['value' => 3]);
    }
    app(ScoringService::class)->score($attempt->fresh());
    expect($attempt->fresh()->result->engine_result['factors']['FAM']['band'])->toBe('RED');

    // 링크는 살아 있지만(철회·만료 아님) 열리지 않는다. 보호자에게는 이유 없는 404.
    expect($share->fresh()->isUsable())->toBeTrue();
    $this->get(route('oymsi.share.view', $share->token))
        ->assertNotFound()
        ->assertDontSee('가족');
});

test('FAM 이 RED 여도 공유 철회는 언제나 할 수 있다', function () {
    // 차단 상태에서 철회까지 막으면, 이미 나간 링크를 청소년이 스스로 끊을 수 없게 된다.
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    foreach (['FAM01', 'FAM02', 'FAM03', 'FAM04'] as $code) {
        $itemId = $test->items->firstWhere('item_code', $code)->id;
        $attempt->answers()->where('test_item_id', $itemId)->update(['value' => 3]);
    }
    app(ScoringService::class)->score($attempt->fresh());

    $this->actingAs($this->user)
        ->post(route('oymsi.share.revoke', $attempt->id))
        ->assertRedirect();

    expect($share->fresh()->revoked_at)->not->toBeNull();
});

// ── 가족관계 솔루션 카드는 보호자 화면에서 제외 (2026-07-28 결정) ────────────

/**
 * FAM YELLOW(원점수 9) — FAM 이 상위3에 들어 SOL_FAM_PROTECT 가 **실제로 추천되는** 조건이다.
 * (FAM RED 는 공유 자체가 차단되므로 이 카드가 보호자 화면에 닿는 경로는 여기뿐이다.)
 */
function famYellowAttempt(User $user): TestAttempt
{
    return completedAttempt(['FAM01' => 3, 'FAM02' => 3, 'FAM03' => 3], $user);
}

test('SOL_FAM_PROTECT 가 실제로 추천되는 조건이다 (제외 단언의 전제)', function () {
    // ★ 엔진이 이 코드를 내지 않는 픽스처로 "화면에 없다"를 단언하면 공허해진다.
    $attempt = famYellowAttempt($this->user);
    $engine = $attempt->result->engine_result;

    expect($engine['factors']['FAM']['band'])->toBe('YELLOW');   // RED 였다면 공유가 차단된다
    expect(collect($engine['priority'])->pluck('factor')->all())->toContain('FAM');
    expect($engine['solutions'])->toContain('SOL_FAM_PROTECT');  // 채점 결과에는 남아 있다
});

test('보호자 화면에서 가족관계 솔루션 카드가 통째로 빠진다', function () {
    $attempt = famYellowAttempt($this->user);
    $html = guardianHtml($this, $attempt, $this->user);

    // 제목(전문가용 어휘)도, 실천항목(청소년이 할 행동)도 보이지 않는다
    expect($html)->not->toContain('보호자 안전성 평가·중재');
    expect($html)->not->toContain('상담자나 믿을 수 있는 어른에게 가족상황 말하기');
    expect($html)->not->toContain('가족상담이 정말 안전하고 도움이 될지 먼저 확인하기');

    // 실천 섹션 자체는 남고 다른 카드는 그대로다 — 섹션이 통째로 사라진 게 아니다
    expect($html)->toContain('함께 해볼 수 있는 실천');
    expect($html)->toContain('3개월 진로탐색·작은 목표');
});

test('제외는 조용한 생략이 아니다 — 조립 결과에 제외 코드가 남는다', function () {
    $attempt = famYellowAttempt($this->user);
    $composer = app(App\Services\OyMsi\ReportComposer::class);

    $guardian = collect($composer->compose($attempt->result, 'GUARDIAN'))
        ->firstWhere('type', 'SOLUTIONS');
    expect($guardian['excluded_for_audience'])->toBe(['SOL_FAM_PROTECT']);
    expect(collect($guardian['items'])->pluck('code')->all())->not->toContain('SOL_FAM_PROTECT');

    // 청소년 쪽은 제외 없음
    $youth = collect($composer->compose($attempt->result, 'YOUTH'))
        ->firstWhere('type', 'SOLUTIONS');
    expect($youth['excluded_for_audience'])->toBe([]);
    expect(collect($youth['items'])->pluck('code')->all())->toContain('SOL_FAM_PROTECT');
});

test('제외 후 남는 카드가 없으면 실천 섹션 자체를 넣지 않는다 (빈 블록 방지)', function () {
    // ★ 실제로 도달 가능한 상태다(리포트 §14). FAM 외 8요인이 전부 UNSCORABLE 이면
    //   상위3이 FAM 하나뿐이라 추천 솔루션도 SOL_FAM_PROTECT 하나뿐이 된다.
    //   FAM 은 YELLOW 라 공유 차단에도 걸리지 않는다 — 보호자가 실제로 열 수 있는 화면이다.
    $test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'user_id' => $this->user->id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'nickname' => '민수', 'gender' => 'male', 'age_at_test' => 16,
        'assessment_version' => $test->assessment_version,
        'scoring_version' => $test->scoringRule->version,
    ]);
    foreach ($test->items as $item) {
        // FAM 만 응답한다(FAM01·FAM02=3, FAM03=2, 나머지 0 → raw 8 = YELLOW).
        // SAF 는 0 으로 응답해 S0 을 만든다(무응답이면 최소 S1 이 되고, 안전 솔루션이 붙어 섹션이 비지 않는다).
        $fam = str_starts_with($item->item_code, 'FAM');
        $saf = str_starts_with($item->item_code, 'SAF');
        $value = match (true) {
            in_array($item->item_code, ['FAM01', 'FAM02'], true) => 3,
            $item->item_code === 'FAM03' => 2,
            $fam || $saf => 0,
            default => null,
        };
        $attempt->answers()->create([
            'test_item_id' => $item->id,
            'value' => $value,
            'missing_code' => $value === null ? 'PREFER_NOT' : null,
        ]);
    }
    app(ScoringService::class)->score($attempt);

    $engine = $attempt->fresh()->result->engine_result;
    expect($engine['factors']['FAM']['band'])->toBe('YELLOW');            // 공유 차단 대상이 아니다
    expect($engine['solutions'])->toBe(['SOL_FAM_PROTECT']);              // 추천이 이것 하나뿐

    // 조립 결과에서 SOLUTIONS 섹션이 아예 빠진다 — 빈 items 를 가진 섹션을 남기지 않는다
    $composer = app(App\Services\OyMsi\ReportComposer::class);
    expect(collect($composer->compose($attempt->fresh()->result, 'GUARDIAN'))->pluck('type')->all())
        ->not->toContain('SOLUTIONS');
    // 청소년 쪽은 그대로 남는다
    expect(collect($composer->compose($attempt->fresh()->result, 'YOUTH'))->pluck('type')->all())
        ->toContain('SOLUTIONS');

    // 화면에도 제목만 남은 빈 블록이 생기지 않는다
    $html = guardianHtml($this, $attempt->fresh(), $this->user);
    expect($html)->not->toContain('함께 해볼 수 있는 실천');
    expect($html)->toContain('자녀의 마음상태');   // 나머지 화면은 정상
});

test('청소년 화면에는 가족관계 솔루션이 그대로 나온다 — 수신자가 맞다', function () {
    $attempt = famYellowAttempt($this->user);

    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('보호자 안전성 평가·중재')                        // 제목
        ->assertSee('상담자나 믿을 수 있는 어른에게 가족상황 말하기'); // 실천항목
});

test('FAM 이 RED 가 아니면 공유는 평소대로 된다', function () {
    // 차단이 과확장되지 않았는지 — FAM YELLOW(원점수 6~10)는 막지 않는다.
    $attempt = completedAttempt(['FAM01' => 3, 'FAM02' => 3, 'FAM03' => 2], $this->user);
    expect($attempt->result->engine_result['factors']['FAM']['band'])->toBe('YELLOW');

    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertOk()
        ->assertSee('공유 링크 만들기');

    $html = guardianHtml($this, $attempt, $this->user);
    expect($html)->toContain('자녀의 마음상태');
});

test('S0+E3 에서 안전 패널이 안심 문구만 남지 않는다', function () {
    // ★ 이번 수정의 핵심 회귀 방어.
    // 안전 패널은 S·E 두 축의 문안을 함께 조회한다. E 블록을 비우면 S0 문안
    // ("자해·자살과 관련한 뚜렷한 위험은 확인되지 않았습니다") 한 줄만 남아
    // 빨간 경보 패널이 안심 문구로 뒤집힌다.
    $attempt = completedAttempt(['TRM06' => 3], $this->user);
    expect($attempt->result->engine_result['safety']['suicide_level'])->toBe('S0');
    expect($attempt->result->engine_result['safety']['environment_level'])->toBe('E3');

    $html = guardianHtml($this, $attempt, $this->user);

    expect($html)->toContain('자해·자살과 관련한 뚜렷한 위험은 확인되지 않았습니다');   // S0 문안
    expect($html)->toContain('주변 환경 안전');                                        // 소제목 유지
    expect($html)->toContain('전문기관의 상담을 받아 보시기를 권합니다');               // 중립 안내
    expect($html)->toContain('1388');

    // 안심 문구가 중립 안내보다 앞이지만, 패널이 거기서 끝나지 않는다
    expect(mb_strpos($html, '주변 환경 안전'))
        ->toBeGreaterThan(mb_strpos($html, '자해·자살과 관련한 뚜렷한 위험은 확인되지 않았습니다'));
});

test('환경위험이 없으면 보호자 화면에 상담 권고가 붙지 않는다', function () {
    // S2 + E0 — 패널은 뜨지만 환경축은 신호가 없다. 없는 위험을 있는 것처럼 쓰지 않는다.
    $attempt = completedAttempt(['SAF01' => 2], $this->user);
    expect($attempt->result->engine_result['safety']['environment_level'])->toBe('E0');

    $html = guardianHtml($this, $attempt, $this->user);
    expect($html)->toContain('주변 환경과 관련한 안전 신호는 나타나지 않았습니다');
    expect($html)->not->toContain('전문기관의 상담을 받아 보시기를 권합니다');
});

test('청소년용 환경위험 문안은 그대로 남아 있다', function () {
    // 수신자가 맞는 쪽(청소년 본인)은 손대지 않았다.
    $attempt = completedAttempt(['TRM06' => 3], $this->user);
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('지금 네 안전이 위험할 수 있어. 즉시 안전한 곳을 확보하고 도움을 받아야 해.')
        ->assertSee('청소년쉼터, 1388, 경찰 또는 보호기관 도움받기');
});

// ── 보호자 화면 자체 ────────────────────────────────────────────────────────

test('보호자 화면은 존댓말이고 재공유 버튼이 없다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->get(route('oymsi.share.view', $share->token))
        ->assertOk()
        ->assertSee('자녀의 마음상태')
        ->assertDontSee('보호자와 공유하기')
        ->assertDontSee('공유 링크 만들기')
        ->assertSee('1388');
});

test('열람 시각은 첫 열람만 기록한다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->get(route('oymsi.share.view', $share->token))->assertOk();
    $first = $share->fresh()->viewed_at;
    expect($first)->not->toBeNull();

    $this->travel(2)->minutes();
    $this->get(route('oymsi.share.view', $share->token))->assertOk();
    expect($share->fresh()->viewed_at->eq($first))->toBeTrue();
});

// ── 청소년 결과 화면의 공유 버튼이 실제로 이 라우트를 가리킨다 ─────────────

test('청소년 결과 화면의 공유 버튼이 공유 폼으로 연결된다', function () {
    $attempt = completedAttempt([], $this->user);

    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee(route('oymsi.share.form', $attempt->id), false)
        ->assertSee('보호자와 공유하기');
});
