<?php

namespace App\Services\OyMsi;

use App\Models\InterpretationTemplate;
use App\Models\TestResult;
use RuntimeException;

/**
 * OY_MSI 결과 보고서 조립기.
 *
 * 채점 엔진 산출물(test_results.engine_result)과 결과 문안(interpretation_templates)을
 * 붙여 화면이 순서대로 찍기만 하면 되는 섹션 배열을 만든다.
 *
 * 섹션 순서는 005 부록1 §2 를 따른다 — 안전 → 종합 → 영역별 → 상위3 → 강점 →
 * 실천 → 재검 → 고지문. 순서 자체가 임상적 의미를 갖는다(안전이 언제나 맨 앞).
 *
 * ★ SAF(자해·자살 안전) 요인의 원점수는 어떤 audience 에도 싣지 않는다.
 *   - 영역별(FACTORS)은 included_in_overall=true 인 9개 요인만 담는다(SAF 는 false).
 *   - 상위3(PRIORITY)은 PriorityRanker 가 이미 SAF 를 제외한 결과다.
 *   - SAF 는 등급(S0~S3)에 대응하는 안전 안내 문안으로만 드러난다.
 *
 * ★ 문안 키가 없으면 예외를 던진다. 빈 문자열로 넘어가면 결과지에서 문단이 소리
 *   없이 사라진다 — 174건 전수 시딩을 전제로 동작하므로 누락은 데이터 사고다.
 */
class ReportComposer
{
    /** Task 15 시더가 쓰는 로케일 */
    private const LOCALE = 'ko-KR';

    /**
     * audience 별로 **의도적으로 제외**하는 실천 솔루션. [audience => [코드 => 제외 이유]]
     *
     * ★ 이것은 "문안을 못 찾아 빠진 것"이 아니라 "수신자가 달라서 뺀 것"이다.
     *   조회 실패는 solutionsSection()/text() 가 예외로 드러낸다.
     *
     * SOL_FAM_PROTECT (2026-07-28 결정) — 이 카드는 통째로 보호자 화면에서 뺀다.
     *   · 제목이 "보호자 안전성 평가·중재"(scoring_rules.solutions)다. 읽는 보호자를
     *     안전성 평가의 대상으로 지목한다.
     *   · 실천항목(solution.SOL_FAM_PROTECT.steps)도 "상담자나 믿을 수 있는 어른에게
     *     가족상황 말하기" 처럼 **청소년이 할 행동**이다. 제목만 가려도 보호자에게는
     *     "아이가 외부에 가족 상황을 알리도록 안내받고 있다"로 읽혀 문제가 남는다.
     *   · 두 문안 모두 보호자용이 아니므로, 문장을 지어내지 않고 해결하는 유일한 방법이
     *     카드 하나를 빼는 것이다. **청소년 화면에는 그대로 나온다**(수신자가 맞다).
     *   · 엔진·scoring_rules 는 건드리지 않는다 — 채점 결과(engine_result.solutions)에는
     *     이 코드가 그대로 남고, 제외는 이 조립 계층에서만 일어난다.
     *   · 보호자용 제목·실천항목이 저자에게서 오면 이 제외를 풀 수 있다(리포트 저자 확인 목록).
     */
    private const SOLUTIONS_EXCLUDED_BY_AUDIENCE = [
        'GUARDIAN' => [
            'SOL_FAM_PROTECT' => '제목·실천항목이 각각 전문가용·청소년용이라 보호자 수신자에 맞지 않는다',
        ],
    ];

    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(private TemplateLineParser $lines = new TemplateLineParser()) {}

    /**
     * @param  'YOUTH'|'GUARDIAN'  $audience
     * @return list<array<string, mixed>>
     */
    public function compose(TestResult $result, string $audience): array
    {
        $engine = $result->engine_result;
        $rules = $result->attempt->test->scoringRule->rules;

        return array_values(array_filter([
            $this->safetySection($engine, $audience),
            $this->overallSection($engine, $audience, $this->hasSafetyAlert($engine)),
            $this->factorsSection($engine, $rules),
            $this->prioritySection($engine, $rules, $audience),
            $this->strengthSection($engine),
            $this->solutionsSection($engine, $rules, $audience),
            $this->recheckSection($engine),
            $this->disclaimerSection($audience),
        ]));
    }

    // 1. 안전 — 자살안전(S) 또는 환경위험(E) 이 1등급 이상일 때만, 언제나 최상단.
    //    S0·E0 이면 섹션 자체를 넣지 않는다(위험이 없는데 경고문을 띄우지 않는다).
    //    환경위험 문안은 이 섹션 안에서 자살안전 문안 바로 다음에 놓인다.
    private function safetySection(array $engine, string $audience): ?array
    {
        if (!$this->hasSafetyAlert($engine)) {
            return null;
        }

        $safety = $engine['safety']['suicide_level'];
        $environment = $engine['safety']['environment_level'];

        return [
            'type' => 'SAFETY_NOTICE',
            'safety_level' => $safety,
            'environment_level' => $environment,
            'safety_lines' => $this->mixedLines("result.{$audience}.SAF.{$safety}.safety_notice"),
            'environment_lines' => $this->mixedLines("result.{$audience}.ENV.{$environment}.safety_notice"),
        ];
    }

    /**
     * 2. 종합
     *
     * `has_safety_alert` — 안전(S) 또는 환경(E) 경보가 걸려 있는가.
     *
     * 왜 필요한가: 전체 위험지수는 SAF 를 뺀 9요인 합/162 다(SAF 는 included_in_overall
     * =false). 그래서 한 요인이 만점(18/18)이고 안전등급이 S2 여도 종합은 "초록"으로
     * 나올 수 있다 — 007 §246 이 말한 "전체 지수가 특정 요인의 고위험을 상쇄할 수
     * 있다" 는 그 상황이다. 007 §68 은 "안전경보가 최종판정보다 우선한다" 고 못박는다.
     * 신호등 헤드라인 구조(설계 §5.1)는 그대로 두되, 화면이 이 플래그로 "이 점수에는
     * 안전 항목이 빠져 있다" 는 한 줄을 덧붙여 상쇄 축만 크게 남지 않게 한다.
     *
     * 문구 자체는 audience 별 어투가 다르므로 뷰가 갖는다(YOUTH 는 반말).
     * GUARDIAN 화면(Task 18)도 같은 보정을 해야 하며 이 플래그를 그대로 쓰면 된다.
     */
    private function overallSection(array $engine, string $audience, bool $hasSafetyAlert): array
    {
        $band = $engine['overall']['band'];

        return [
            'type' => 'OVERALL',
            'band' => $band,
            'risk_index' => $engine['overall']['risk_index'],
            'score_status' => $engine['score_status'],
            'final_case_code' => $engine['profile']['final_case_code'],
            'has_safety_alert' => $hasSafetyAlert,
            'text' => $this->text("result.{$audience}.OVERALL.{$band}.meaning"),
        ];
    }

    /** 안전(S) 또는 환경(E) 경보가 1등급 이상인가 */
    private function hasSafetyAlert(array $engine): bool
    {
        return $this->level($engine['safety']['suicide_level']) >= 1
            || $this->level($engine['safety']['environment_level']) >= 1;
    }

    // 3. 영역별 — SAF 제외 (included_in_overall 로 데이터에서 걸러낸다)
    private function factorsSection(array $engine, array $rules): array
    {
        $items = [];
        foreach ($engine['factors'] as $code => $factor) {
            if (!($rules['factors'][$code]['included_in_overall'] ?? false)) {
                continue;
            }
            $items[] = [
                'factor' => $code,
                'name' => $rules['factors'][$code]['name'],
                'raw' => $factor['raw'],
                'max' => $factor['max'],
                'risk_index' => $factor['risk_index'],
                'band' => $factor['band'],
                'score_status' => $factor['score_status'],
            ];
        }

        return ['type' => 'FACTORS', 'items' => $items];
    }

    // 4. 상위 3영역
    private function prioritySection(array $engine, array $rules, string $audience): array
    {
        $items = [];
        foreach ($engine['priority'] as $row) {
            $factor = $row['factor'];
            $band = $row['band'];
            $item = [
                'factor' => $factor,
                'name' => $rules['factors'][$factor]['name'],
                'band' => $band,
                'rank' => $row['rank'],
                'meaning' => $this->text("result.{$audience}.{$factor}.{$band}.meaning"),
                'actions' => $this->bulletLines("result.{$audience}.{$factor}.{$band}.actions"),
            ];
            if ($audience === 'GUARDIAN') {
                $item['avoid'] = $this->bulletLines("result.GUARDIAN.{$factor}.{$band}.avoid");
            }
            $items[] = $item;
        }

        return ['type' => 'PRIORITY', 'items' => $items];
    }

    // 5. 강점 (엔진이 최소 1개를 보장한다)
    private function strengthSection(array $engine): array
    {
        return [
            'type' => 'STRENGTH',
            'items' => array_map(fn ($code) => $this->text("strength.{$code}.text"), $engine['strengths']),
        ];
    }

    // 6. 실천 솔루션
    //
    // ★ audience 별 제외는 **의도된 것**이며 조회 실패와 구분된다.
    //   - 제외: SOLUTIONS_EXCLUDED_BY_AUDIENCE 에 코드와 이유가 적혀 있고, 결과 섹션의
    //     'excluded_for_audience' 에 그 코드가 남는다(무엇이 왜 빠졌는지 추적 가능).
    //   - 조회 실패: scoring_rules 에 없는 코드는 예외를 던진다. 엔진이 낸 코드가 규칙에
    //     없다는 것은 데이터 사고이므로 조용히 건너뛰지 않는다.
    private function solutionsSection(array $engine, array $rules, string $audience): ?array
    {
        $excluded = self::SOLUTIONS_EXCLUDED_BY_AUDIENCE[$audience] ?? [];
        $items = [];
        $dropped = [];

        foreach ($engine['solutions'] as $code) {
            if (isset($excluded[$code])) {
                $dropped[] = $code;           // 수신자 불일치로 뺀 것 — 상수에 이유가 있다
                continue;
            }
            if (!isset($rules['solutions'][$code])) {
                throw new RuntimeException(
                    "scoring_rules.solutions 에 솔루션 코드 {$code} 가 없습니다 — 엔진이 낸 코드를 조립할 수 없습니다."
                );
            }
            $items[] = [
                'code' => $code,
                'title' => $rules['solutions'][$code]['title'],
                'steps' => $this->bulletLines("solution.{$code}.steps"),
            ];
        }

        // 남는 카드가 하나도 없으면 섹션 자체를 넣지 않는다 — 제목만 있고 내용이 없는
        // 빈 블록을 화면에 남기지 않기 위해서다(safetySection 이 S0·E0 에서 null 을
        // 돌려주는 것과 같은 처리). 대체 문구를 지어내지 않는다.
        // 실제 도달 가능성은 task-18b 리포트 §14 참조(FAM 이 유일한 채점가능 요인일 때).
        if ($items === []) {
            return null;
        }

        return [
            'type' => 'SOLUTIONS',
            'items' => $items,
            'excluded_for_audience' => $dropped,
        ];
    }

    // 7. 재검
    private function recheckSection(array $engine): array
    {
        return ['type' => 'RECHECK'] + $engine['recheck'];
    }

    // 8. 고지문
    private function disclaimerSection(string $audience): array
    {
        $text = $this->text("disclaimer.{$audience}");
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));

        return ['type' => 'DISCLAIMER', 'lines' => $lines];
    }

    /** 'S2' → 2, 'E0' → 0 */
    private function level(string $code): int
    {
        return (int) substr($code, 1);
    }

    /** actions / avoid / steps — 목록 필드 */
    private function bulletLines(string $key): array
    {
        return $this->lines->parse($this->text($key), TemplateLineParser::MODE_LIST);
    }

    /** safety_notice — 서술 문단 + 소제목 + 항목이 섞인 필드 */
    private function mixedLines(string $key): array
    {
        return $this->lines->parse($this->text($key), TemplateLineParser::MODE_MIXED);
    }

    /**
     * 문안 한 건을 가져온다. 없거나 비활성이면 예외 — 조용히 빈칸으로 넘기지 않는다.
     */
    private function text(string $key): string
    {
        if (!array_key_exists($key, $this->cache)) {
            $text = InterpretationTemplate::query()
                ->where('template_key', $key)
                ->where('locale', self::LOCALE)
                ->where('active', true)
                ->orderByDesc('version')
                ->value('text');

            if ($text === null || trim($text) === '') {
                throw new RuntimeException(
                    "OY_MSI 결과 문안이 없습니다: {$key} (locale=" . self::LOCALE . ", active=1). "
                    . 'interpretation_templates 시딩(TemplateSeeder 174건)을 확인하십시오.'
                );
            }

            $this->cache[$key] = $text;
        }

        return $this->cache[$key];
    }
}
