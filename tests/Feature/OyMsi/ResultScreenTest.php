<?php

use App\Models\InterpretationTemplate;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\OyMsi\ReportComposer;
use App\Services\OyMsi\TemplateLineParser;
use App\Services\ScoringService;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TemplateSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    (new TemplateSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

// completedAttempt() 는 Task 18(ShareTest)에서도 쓰므로 tests/Pest.php 로 옮겼다.

/** 중첩 배열 전체에서 특정 키의 값을 모아 온다 (누출 검사용) */
function collectKeyDeep(array $data, string $key): array
{
    $out = [];
    $walk = function ($node) use (&$walk, $key, &$out) {
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $k => $v) {
            if ($k === $key && !is_array($v)) {
                $out[] = $v;
            }
            $walk($v);
        }
    };
    $walk($data);

    return $out;
}

// ── 브리프 규정 테스트 ──────────────────────────────────────────────────────

test('섹션 순서가 005 부록1 기준이다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3, 'SAF01' => 2]);
    $sections = app(ReportComposer::class)->compose($attempt->result, 'YOUTH');

    expect(array_column($sections, 'type'))->toBe([
        'SAFETY_NOTICE', 'OVERALL', 'FACTORS', 'PRIORITY', 'STRENGTH', 'SOLUTIONS', 'RECHECK', 'DISCLAIMER',
    ]);
});

test('S0·E0 이면 안전 섹션을 넣지 않는다', function () {
    $sections = app(ReportComposer::class)->compose(completedAttempt()->result, 'YOUTH');
    expect(array_column($sections, 'type'))->not->toContain('SAFETY_NOTICE');
});

test('상위 3영역에 문안이 채워진다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    $sections = collect(app(ReportComposer::class)->compose($attempt->result, 'YOUTH'))->keyBy('type');

    $priority = $sections['PRIORITY']['items'];
    expect($priority[0]['factor'])->toBe('DEP');
    expect($priority[0]['meaning'])->not->toBeEmpty();
    expect($priority[0]['actions'])->not->toBeEmpty();
});

test('강점이 최소 1개 들어간다', function () {
    $sections = collect(app(ReportComposer::class)->compose(completedAttempt()->result, 'YOUTH'))->keyBy('type');
    expect($sections['STRENGTH']['items'])->not->toBeEmpty();
});

test('보호자용에는 피해야 할 반응이 들어간다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    $sections = collect(app(ReportComposer::class)->compose($attempt->result, 'GUARDIAN'))->keyBy('type');
    expect($sections['PRIORITY']['items'][0]['avoid'])->not->toBeEmpty();
});

test('SAF 요인 점수는 어떤 대상에게도 노출되지 않는다', function () {
    $attempt = completedAttempt(['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3]);
    foreach (['YOUTH', 'GUARDIAN'] as $audience) {
        $sections = collect(app(ReportComposer::class)->compose($attempt->result, $audience))->keyBy('type');
        expect(array_column($sections['FACTORS']['items'], 'factor'))->not->toContain('SAF');
    }
});

test('결과 화면이 렌더된다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('민수')
        ->assertSee('1388');
});

test('안전등급 S2 이상이면 결과 화면에 연락처가 최상단에 뜬다', function () {
    $attempt = completedAttempt(['SAF01' => 2], $this->user);
    $html = $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('먼저 읽어야 할 안내')              // 안전 패널
        ->assertSee('자살예방 상담전화 109')            // 패널 전용 문구 (상시 블록은 '자살예방 상담 109')
        ->getContent();

    // 상시 도움기관 블록에도 109 가 있으므로, '최상단'은 위치로 확인한다.
    expect(mb_strpos($html, '먼저 읽어야 할 안내'))
        ->toBeLessThan(mb_strpos($html, '종합 마음상태'));
    expect(mb_strpos($html, '먼저 읽어야 할 안내'))
        ->toBeLessThan(mb_strpos($html, '도움받을 수 있는 곳'));
});

test('안전등급과 무관하게 위기 연락처 4종과 꿈드림이 상시 노출된다', function () {
    // 설계 §5.1 #6 — 109 · 1388 · 112 · 119 + 꿈드림
    $attempt = completedAttempt([], $this->user);   // S0 · E0
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('도움받을 수 있는 곳')
        ->assertSee('tel:109', false)
        ->assertSee('tel:1388', false)
        ->assertSee('tel:112', false)
        ->assertSee('tel:119', false)
        ->assertSee('꿈드림');
});

// ── SAF 원점수 비노출 고정 ─────────────────────────────────────────────────

test('compose 결과 어디에도 SAF 요인 객체가 없다', function () {
    // SAF 전 문항 응답 → SAF raw 는 채점되지만 보고서에는 절대 실리지 않아야 한다.
    $attempt = completedAttempt(['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3, 'SAF04' => 2, 'SAF05' => 2, 'SAF06' => 2]);
    expect($attempt->result->engine_result['factors']['SAF']['raw'])->not->toBeNull(); // 엔진은 계산한다

    foreach (['YOUTH', 'GUARDIAN'] as $audience) {
        $sections = app(ReportComposer::class)->compose($attempt->result, $audience);
        expect(collectKeyDeep($sections, 'factor'))->not->toContain('SAF');
        expect(collectKeyDeep($sections, 'name'))->not->toContain('자해·자살 안전');
    }
});

test('결과 화면 HTML 에 SAF 요인 이름·원점수 칸이 없다', function () {
    $attempt = completedAttempt(['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3, 'SAF04' => 2, 'SAF05' => 2, 'SAF06' => 2], $this->user);
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertDontSee('자해·자살 안전')   // SAF 요인 이름
        ->assertDontSee('area_scores')       // 원본 점수 덤프
        ->assertDontSee('engine_result')
        ->assertDontSee('"SAF"', false);
});

test('S0·E0 이면 결과 화면에 안전 패널이 뜨지 않는다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertDontSee('먼저 읽어야 할 안내')          // 안전 패널 제목
        ->assertDontSee('주변 환경 안전')                // 환경위험 블록
        ->assertDontSee('자살예방 상담전화 109');        // 패널 전용 통화 버튼 문구
});

// ── 종합 신호등이 안전 경보를 상쇄해 보이지 않게 하는 보정 (007 §68 / §246) ──

test('안전·환경 경보가 있으면 종합 블록에 안전 우선 안내가 붙는다', function () {
    // DEP 만점(빨강) + S2 인데 전체 위험지수는 낮아 종합은 초록으로 나온다.
    $attempt = completedAttempt([
        'DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3,
        'SAF01' => 2,
    ], $this->user);

    $overall = collect(app(ReportComposer::class)->compose($attempt->result, 'YOUTH'))
        ->firstWhere('type', 'OVERALL');
    expect($overall['band'])->toBe('GREEN');           // 상쇄가 실제로 일어나는 조건인지 확인
    expect($overall['has_safety_alert'])->toBeTrue();

    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('이 종합 신호에는 안전에 관한 문항이 들어가 있지 않습니다. 위에 있는 안전 안내를 먼저 읽어 주세요.');
});

test('환경 경보만 있어도 종합 블록에 안전 우선 안내가 붙는다', function () {
    $attempt = completedAttempt(['TRM06' => 1], $this->user);   // E1, S0
    $overall = collect(app(ReportComposer::class)->compose($attempt->result, 'YOUTH'))
        ->firstWhere('type', 'OVERALL');
    expect($overall['has_safety_alert'])->toBeTrue();
});

test('S0·E0 이면 종합 블록에 안전 우선 안내가 붙지 않는다', function () {
    $attempt = completedAttempt([], $this->user);

    foreach (['YOUTH', 'GUARDIAN'] as $audience) {
        $overall = collect(app(ReportComposer::class)->compose($attempt->result, $audience))
            ->firstWhere('type', 'OVERALL');
        expect($overall['has_safety_alert'])->toBeFalse();
    }

    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertDontSee('위에 있는 안전 안내를 먼저 읽어 주세요');
});

test('보호자용 조립물도 같은 안전 우선 플래그를 받는다', function () {
    // GUARDIAN 화면은 Task 18 이지만 ReportComposer 는 공용이므로 플래그가 있어야 한다.
    $attempt = completedAttempt(['SAF01' => 2]);
    $overall = collect(app(ReportComposer::class)->compose($attempt->result, 'GUARDIAN'))
        ->firstWhere('type', 'OVERALL');
    expect($overall['has_safety_alert'])->toBeTrue();
});

// ── SAF 비노출이 컨트롤러 분기 하나에만 의존하지 않는다 ─────────────────────

test('레거시 컬럼 area_scores·area_signals 에 SAF 가 없다', function () {
    $attempt = completedAttempt(['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3, 'SAF04' => 2, 'SAF05' => 2, 'SAF06' => 2]);
    $result = $attempt->result;

    expect($result->engine_result['factors']['SAF']['raw'])->not->toBeNull();  // 엔진은 계산한다
    expect(array_keys($result->area_scores))->not->toContain('SAF');
    expect(array_keys($result->area_signals))->not->toContain('SAF');
    expect(array_keys($result->area_scores))->toHaveCount(9);
    expect($result->area_scores['DEP'])->not->toBeNull();                      // 나머지 9요인은 그대로
});

test('공용 결과 화면으로 렌더해도 SAF 점수가 새지 않는다', function () {
    // ResultController 의 oy_msi 분기를 우회한 상황(Task 18 공유 화면·명부·PDF 등이
    // 분기를 재현하지 않는 경우)을 가정한다. 방어는 데이터 쪽에 있어야 한다.
    $attempt = completedAttempt(['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3, 'SAF04' => 2, 'SAF05' => 2, 'SAF06' => 2]);
    $attempt->load('test', 'result');

    $html = view('result.show', [
        'attempt' => $attempt, 'test' => $attempt->test, 'result' => $attempt->result,
    ])->render();

    // 공용 뷰가 SAF 점수를 찍을 수 있는 통로는 둘뿐이다.
    expect($html)->not->toContain('>SAF<');        // 영역별 결과 목록의 요인 이름 칸
    expect($html)->not->toContain('"SAF"');        // Chart.js 라벨 배열(array_keys(area_scores))
    expect($html)->toContain('>DEP<');             // 나머지 9요인은 정상 렌더 (빈 화면이 아님)

    // 남는 'SAF' 문자열은 솔루션 코드 SOL_SAF_PLAN 하나뿐이다 — 점수가 아니라
    // "안전계획을 권한다"는 질적 정보이고, 안전 안내와 같은 층위다.
    expect(substr_count($html, 'SAF'))->toBe(substr_count($html, 'SOL_SAF_PLAN'));
});

// ── 문안 누락은 조용히 넘어가지 않는다 ──────────────────────────────────────

test('문안 키가 없으면 예외를 던진다', function () {
    InterpretationTemplate::where('template_key', 'result.YOUTH.OVERALL.GREEN.meaning')->delete();
    $attempt = completedAttempt();

    expect(fn () => app(ReportComposer::class)->compose($attempt->result, 'YOUTH'))
        ->toThrow(RuntimeException::class, 'result.YOUTH.OVERALL.GREEN.meaning');
});

test('문안이 비활성(active=false)이어도 예외를 던진다', function () {
    InterpretationTemplate::where('template_key', 'disclaimer.YOUTH')->update(['active' => false]);
    $attempt = completedAttempt();

    expect(fn () => app(ReportComposer::class)->compose($attempt->result, 'YOUTH'))
        ->toThrow(RuntimeException::class, 'disclaimer.YOUTH');
});

// ── 소제목·질문 줄 처리 규칙 고정 (Task 15 리뷰 지적 ③) ─────────────────────

test('실천 문안의 소제목은 불릿이 아니라 소제목으로 분류된다', function () {
    $parser = app(TemplateLineParser::class);
    $text = InterpretationTemplate::where('template_key', 'result.YOUTH.IMP.YELLOW.actions')->value('text');
    $lines = $parser->parse($text, TemplateLineParser::MODE_LIST);

    expect($lines[0])->toBe(['kind' => 'heading', 'text' => '멈춤 4단계']);
    expect($lines[1]['kind'])->toBe('item');
    expect($lines[5])->toBe(['kind' => 'heading', 'text' => '함께 확인할 것']);
    expect($lines[6]['kind'])->toBe('question');
    expect($lines[6]['text'])->toEndWith('?');
});

test('시딩된 전 문안에서 소제목으로 분류되는 줄은 알려진 목록뿐이다', function () {
    $parser = app(TemplateLineParser::class);
    $headings = [];

    foreach (InterpretationTemplate::all() as $tpl) {
        $mode = str_ends_with($tpl->template_key, '.safety_notice')
            ? TemplateLineParser::MODE_MIXED
            : TemplateLineParser::MODE_LIST;
        if (!preg_match('/\.(actions|avoid|steps|safety_notice)$/', $tpl->template_key)) {
            continue;
        }
        foreach ($parser->parse($tpl->text, $mode) as $line) {
            if ($line['kind'] === 'heading') {
                $headings[$line['text']] = true;
            }
        }
    }

    expect(array_keys($headings))->toEqualCanonicalizing([
        // 실천(actions) 문안 안의 소제목
        '멈춤 4단계', '함께 확인할 것', '감각접지 5－4－3－2－1', '추가 도움', '7일 생활회복 방법',
        // 안전(safety_notice) 문안 안의 소제목
        '기억할 연락처', '지금 할 일', '지금 바로 해야 할 일', '즉시 행동',
        '부모·보호자 행동', '피해야 할 말', '권장 표현',
    ]);
});

test('안전 문안의 도입 문장은 문단으로, 소제목 아래 줄은 항목으로 분류된다', function () {
    $parser = app(TemplateLineParser::class);
    $text = InterpretationTemplate::where('template_key', 'result.YOUTH.SAF.S2.safety_notice')->value('text');
    $lines = $parser->parse($text, TemplateLineParser::MODE_MIXED);

    expect($lines[0]['kind'])->toBe('paragraph');
    expect($lines[1]['kind'])->toBe('paragraph');
    expect($lines[2])->toBe(['kind' => 'heading', 'text' => '지금 바로 해야 할 일']);
    expect($lines[3]['kind'])->toBe('item');
});

test('안전 문안의 연락처 나열은 소제목이 아니라 항목이다', function () {
    $parser = app(TemplateLineParser::class);
    $text = InterpretationTemplate::where('template_key', 'result.YOUTH.SAF.S0.safety_notice')->value('text');
    $kinds = array_column($parser->parse($text, TemplateLineParser::MODE_MIXED), 'kind', 'text');

    expect($kinds['기억할 연락처'])->toBe('heading');
    expect($kinds['자살예방 상담전화: 109'])->toBe('item');
    expect($kinds['청소년상담: 1388'])->toBe('item');
    expect($kinds['긴급상황: 112 또는 119'])->toBe('item');
});

// ── ENV(환경위험) 문안 노출 위치 고정 (인계 ④) ──────────────────────────────

test('환경위험 문안은 안전 섹션 안에서 자살안전 문안 다음에 노출된다', function () {
    // TRM06=3 → E3 (환경 위험). 안전등급은 S0.
    $attempt = completedAttempt(['TRM06' => 3]);
    $sections = collect(app(ReportComposer::class)->compose($attempt->result, 'YOUTH'))
        ->keyBy('type')->all();

    expect(array_keys($sections))->toContain('SAFETY_NOTICE');
    $notice = $sections['SAFETY_NOTICE'];
    expect($notice['safety_level'])->toBe('S0');
    expect($notice['environment_level'])->toBe('E3');
    expect($notice['environment_lines'])->not->toBeEmpty();
    // 배열 순서상 자살안전 문안이 먼저, 환경 문안이 뒤
    $keys = array_keys($notice);
    expect(array_search('safety_lines', $keys, true))
        ->toBeLessThan(array_search('environment_lines', $keys, true));
});

test('환경위험 문안은 결과 화면 안전 섹션 안에 실제로 렌더된다', function () {
    $attempt = completedAttempt(['TRM06' => 3], $this->user);
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('지금 네 안전이 위험할 수 있어. 즉시 안전한 곳을 확보하고 도움을 받아야 해.');
});

// ── 기존 검사 결과 화면 무영향 ──────────────────────────────────────────────

test('OY_MSI 가 아닌 검사는 공용 결과 화면을 그대로 쓴다', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $test = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'guest_token' => 'g-shared',
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
    ]);
    foreach ($test->items as $item) {
        $attempt->answers()->create(['test_item_id' => $item->id, 'value' => 5]);
    }
    app(ScoringService::class)->score($attempt);

    $this->withSession(['guest_token' => 'g-shared'])
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('나의 마음상태 결과')
        ->assertSee('영역별 점수')
        ->assertSee('추천 솔루션')
        ->assertSee('영역별 결과')
        // OY_MSI 전용 화면의 표식이 공용 화면으로 새지 않는다
        ->assertDontSee('지금 먼저 살펴볼 3가지')
        ->assertDontSee('다시 확인할 시점')
        ->assertDontSee('나에게 남아 있는 강점');
});
