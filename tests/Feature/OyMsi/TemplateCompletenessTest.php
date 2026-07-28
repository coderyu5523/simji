<?php

use App\Models\InterpretationTemplate;
use Database\Seeders\OyMsi\TemplateSeeder;

// Pest 는 모든 테스트 파일을 한 프로세스에서 로드하므로 전역 상수는 이름을 좁힌다.
// (브리프의 FACTORS/BANDS 는 너무 일반적이라 향후 다른 테스트 파일과 충돌한다)
const OY_MSI_TPL_FACTORS = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT'];
const OY_MSI_TPL_BANDS = ['GREEN', 'YELLOW', 'RED'];

/**
 * 005 부록1 §3 "금지 표현" 목록.
 * 청소년용 보고서에서 사용하지 않기로 원문이 명시한 표현들이다.
 */
const OY_MSI_FORBIDDEN_PHRASES = [
    '문제가 심각하다', '비정상이다', '정신적으로 이상하다', '위험한 청소년이다',
    '부모의 말을 듣지 않는다', '의지가 부족하다', '게으르다', '부적응자이다',
    '자살성향이 있다', '치료가 반드시 필요하다',
];

/**
 * 금지 표현 스캐너. 실제 문안 검사와 "검사기 자체가 잡는지" 검증에 같은 함수를 쓴다.
 *
 * @param  iterable<array{0:string,1:string}>  $rows  [template_key, text] 쌍
 * @return list<string>  "키: 금지표현" 형태의 위반 목록
 */
function oyMsiForbiddenHits(iterable $rows): array
{
    $hits = [];
    foreach ($rows as [$key, $text]) {
        foreach (OY_MSI_FORBIDDEN_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                $hits[] = "{$key}: {$phrase}";
            }
        }
    }

    return $hits;
}

/** 시더가 낸 [key, text] 쌍 전체 */
function oyMsiTemplateRows(): array
{
    return InterpretationTemplate::all()
        ->map(fn ($t) => [$t->template_key, $t->text])
        ->all();
}

beforeEach(function () {
    (new TemplateSeeder())->run();
    $this->keys = InterpretationTemplate::pluck('text', 'template_key');
});

test('청소년 요인 문안 54건이 모두 있다', function () {
    $missing = [];
    foreach (OY_MSI_TPL_FACTORS as $f) {
        foreach (OY_MSI_TPL_BANDS as $b) {
            foreach (['meaning', 'actions'] as $c) {
                $key = "result.YOUTH.{$f}.{$b}.{$c}";
                if (!isset($this->keys[$key])) $missing[] = $key;
            }
        }
    }
    expect($missing)->toBe([]);
});

test('보호자 요인 문안 81건이 모두 있다', function () {
    $missing = [];
    foreach (OY_MSI_TPL_FACTORS as $f) {
        foreach (OY_MSI_TPL_BANDS as $b) {
            foreach (['meaning', 'actions', 'avoid'] as $c) {
                $key = "result.GUARDIAN.{$f}.{$b}.{$c}";
                if (!isset($this->keys[$key])) $missing[] = $key;
            }
        }
    }
    expect($missing)->toBe([]);
});

test('안전·환경·종합 문안이 모두 있다', function () {
    $missing = [];
    foreach (['YOUTH', 'GUARDIAN'] as $a) {
        foreach (['S0', 'S1', 'S2', 'S3'] as $lv) {
            $k = "result.{$a}.SAF.{$lv}.safety_notice";
            if (!isset($this->keys[$k])) $missing[] = $k;
        }
        foreach (['E0', 'E1', 'E2', 'E3'] as $lv) {
            $k = "result.{$a}.ENV.{$lv}.safety_notice";
            if (!isset($this->keys[$k])) $missing[] = $k;
        }
        foreach (OY_MSI_TPL_BANDS as $b) {
            $k = "result.{$a}.OVERALL.{$b}.meaning";
            if (!isset($this->keys[$k])) $missing[] = $k;
        }
    }
    expect($missing)->toBe([]);
});

test('강점 5 · 솔루션 10 · 고지문 2 가 있다', function () {
    foreach (['TRY_NEW', 'SMALL_GOAL', 'RECOVERY_HOPE', 'HONEST_RESPONSE', 'HELP_SEEKING'] as $s) {
        expect($this->keys)->toHaveKey("strength.{$s}.text");
    }
    foreach ([
        'SOL_SAF_PLAN', 'SOL_TRM_SAFETY', 'SOL_DEP_ACTIVATION', 'SOL_LIF_7DAY', 'SOL_ANX_BREATHING',
        'SOL_IMP_STOP', 'SOL_ISO_CONNECT', 'SOL_FAM_PROTECT', 'SOL_RSK_DIGITAL', 'SOL_FUT_3MONTH',
    ] as $sol) {
        expect($this->keys)->toHaveKey("solution.{$sol}.steps");
    }
    expect($this->keys)->toHaveKey('disclaimer.YOUTH');
    expect($this->keys)->toHaveKey('disclaimer.GUARDIAN');
});

test('총 174건이다', function () {
    expect(InterpretationTemplate::count())->toBe(174);
});

test('빈 문안이 없다', function () {
    expect(InterpretationTemplate::where('text', '')->orWhereNull('text')->count())->toBe(0);
});

test('모든 문안이 locale·version·active 규약을 지킨다', function () {
    expect(InterpretationTemplate::where('locale', 'ko-KR')->where('version', '1.0.0')->where('active', true)->count())
        ->toBe(174);
});

test('시더는 멱등하다 — 재실행해도 174건 그대로다', function () {
    (new TemplateSeeder())->run();
    (new TemplateSeeder())->run();

    expect(InterpretationTemplate::count())->toBe(174);
});

test('금지 표현이 없다 (005 부록1 §3)', function () {
    $hits = oyMsiForbiddenHits(oyMsiTemplateRows());

    expect($hits)->toBe([], "금지 표현 발견:\n" . implode("\n", $hits));
});

test('금지 표현 검사기는 실제로 위반을 잡아낸다', function () {
    // 검사기가 무의미해지지 않도록, 일부러 위반 문자열을 넣어 걸리는지 확인한다.
    $violating = [
        ['result.YOUTH.DEP.RED.meaning', '너는 게으르다. 의지가 부족하다.'],
        ['result.GUARDIAN.DEP.RED.meaning', '이 아이는 문제가 심각하다고 볼 수 있습니다.'],
        ['strength.TRY_NEW.text', '자살성향이 있다'],
        ['disclaimer.YOUTH', '치료가 반드시 필요하다'],
        ['result.YOUTH.ANX.RED.meaning', '비정상이다'],
    ];

    expect(oyMsiForbiddenHits($violating))->toBe([
        'result.YOUTH.DEP.RED.meaning: 의지가 부족하다',
        'result.YOUTH.DEP.RED.meaning: 게으르다',
        'result.GUARDIAN.DEP.RED.meaning: 문제가 심각하다',
        'strength.TRY_NEW.text: 자살성향이 있다',
        'disclaimer.YOUTH: 치료가 반드시 필요하다',
        'result.YOUTH.ANX.RED.meaning: 비정상이다',
    ]);

    // 금지어를 포함하지 않는 정상 문안은 통과해야 한다(과잉검출 방지).
    expect(oyMsiForbiddenHits([
        ['result.YOUTH.DEP.RED.meaning', '지금의 상태는 의지로만 이겨내야 하는 문제가 아니야.'],
    ]))->toBe([]);
});

test('청소년용 문안 65건은 수신자가 맞으므로 손대지 않는다', function () {
    // 2026-07-28 보호자 문안 제거 라운드(Task 18b)의 경계 고정.
    // 003 Ⅶ·006 의 수신자 혼합 문제는 result.GUARDIAN.* 만의 문제다.
    // result.YOUTH.* 는 005(청소년용)에서 왔고 읽는 사람이 청소년 본인이므로
    // 같은 이유로 문장을 빼서는 안 된다. 여기서 전체를 통째로 고정한다.
    //
    // ★ 이 테스트가 깨졌다면: result.YOUTH.* 문안이 바뀐 것이다. 의도한 변경이라면
    //   아래 해시를 새 값으로 갱신하되, 왜 바꿨는지 커밋 메시지에 남겨야 한다.
    //   새 해시는 아래 계산식을 그대로 돌려 얻는다.
    $rows = InterpretationTemplate::where('template_key', 'like', 'result.YOUTH.%')
        ->orderBy('template_key')->pluck('text', 'template_key')->all();

    expect(count($rows))->toBe(65);   // 요인 54 + SAF 4 + ENV 4 + OVERALL 3
    expect(hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE)))
        ->toBe('f2991e713599e8d0b7a454437e3464fd4bcee4566847ceb39c44698ec7047d86');
});

test('보호자 문안에 남은 문장은 비어 있지 않다 — 제거로 빈 값이 생기지 않았다', function () {
    // 제거는 "줄만 뺀다". 키가 사라지거나(ReportComposer::text() 예외 계약)
    // 값이 빈 문자열이 되면(조용한 폴백 금지) 안 된다.
    $blank = InterpretationTemplate::where('template_key', 'like', 'result.GUARDIAN.%')
        ->get()
        ->filter(fn ($t) => trim((string) $t->text) === '')
        ->pluck('template_key')
        ->all();

    expect($blank)->toBe([]);
});

test('보호자 문안에 담당자용 위험 문장이 남아 있지 않다', function () {
    // 기준 (a) 보호자·가족을 평가/경계 대상으로 지목 · (b) 보호자 모르게 진행되는 절차 예고.
    // 화면 렌더 부재는 ShareTest 가 실제 HTTP 로 따로 단언한다(여기는 시드 원본 단언).
    $offenders = [];
    foreach (InterpretationTemplate::where('template_key', 'like', 'result.GUARDIAN.%')->get() as $t) {
        foreach ([
            '가해 가능성이 있는 보호자',
            '제3의 전문기관을 우선',
            '결과공유를 제한',
            '보호자 통보가 위험을 높일 가능성',
            '가족이 안전한 보호자원인지',
            '신고·보호절차를 시행',
            '증거를 보존하고',
        ] as $phrase) {
            if (str_contains($t->text, $phrase)) $offenders[] = "{$t->template_key}: {$phrase}";
        }
    }

    expect($offenders)->toBe([], "담당자용 문장 잔존:\n" . implode("\n", $offenders));
});

test('청소년용 문안은 반말체다', function () {
    // 005 문안은 "~해", "~야", "~어" 로 끝난다. 존댓말(습니다/세요)이 섞이면 톤이 깨진다.
    $violations = [];
    foreach (InterpretationTemplate::where('template_key', 'like', 'result.YOUTH.%')->get() as $t) {
        if (preg_match('/(습니다|하세요|십시오)/u', $t->text)) $violations[] = $t->template_key;
    }
    expect($violations)->toBe([]);
});

test('반말체 검사기는 실제로 존댓말을 잡아낸다', function () {
    InterpretationTemplate::create([
        'template_key' => 'result.YOUTH.DEP.GREEN.meaning',
        'locale' => 'ko-KR',
        'version' => '9.9.9-tone-probe',
        'text' => '현재 일상생활이 크게 흔들리는 상태는 아닙니다. 상담자에게 이야기하세요.',
        'active' => true,
    ]);

    $violations = [];
    foreach (InterpretationTemplate::where('template_key', 'like', 'result.YOUTH.%')->get() as $t) {
        if (preg_match('/(습니다|하세요|십시오)/u', $t->text)) $violations[] = $t->template_key;
    }

    expect($violations)->toBe(['result.YOUTH.DEP.GREEN.meaning']);
});
