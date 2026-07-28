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

test('보호자 화면에 담당자용 보호절차 지침이 나오지 않는다', function () {
    // E2·E3 — 교체 전 문안에 있던 담당자 프로토콜 문장들. 수신자가 보호자가 아니다.
    foreach ([2, 3] as $raw) {
        $attempt = completedAttempt(['TRM06' => $raw], $this->user);
        $html = guardianHtml($this, $attempt, $this->user);

        foreach ([
            '가해 가능성이 있는 보호자',
            '보호자 통보가 위험을 높일 가능성',
            '증거를 보존하고',
            '신고·보호절차',
            '가해 가능성이 있는 사람과 청소년을 분리',
            '비공개 면담',
        ] as $staffOnly) {
            expect($html)->not->toContain($staffOnly);
        }
    }
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
