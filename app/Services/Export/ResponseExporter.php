<?php
namespace App\Services\Export;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Rules\AnswerValue;
use App\Services\OyMsi\SafetyAlert;
use InvalidArgumentException;

/**
 * 검사 응답을 CSV 행으로 조립한다.
 *
 * 이 클래스는 검사 종류를 모른다 — 문항은 test->items, 제외 대상은 채점 룰의
 * safety_items 키와 factors[].included_in_overall 의 합집합에서 읽는다(excludedItemCodes()
 * 참조). 검사가 늘어도 여기를 고치지 않는다.
 *
 * 두 프로필의 차이는 설계 문서(2026-07-30-response-export-design.md) 참조:
 *   연구용   — 비식별, SAF 포함, 영역 점수를 engine_result.factors 에서 읽음
 *   기관용   — 이름 포함, SAF 문항·영역 제외, 영역 점수를 area_scores 에서 읽음
 *
 * ⚠ 기관용에서 SAF 를 빼는 경로가 두 개이고, 각각 **다른** 설정 키를 읽는다
 * (`OyMsiScoringEngine.php:84-88` 의 reportable 필터와 같은 방식). 하나만 보고
 * "SAF 는 다 걸렀다"고 오해하면 안 된다.
 *   - 문항(열) 제외  → excludedItemCodes()/itemColumns() → rules['safety_items'] **∪**
 *     rules['factors'][area]['included_in_overall']===false 인 요인의 문항. 두 조건의
 *     합집합이라 fail-closed 다 — safety_items 리터럴이 개정으로 낡거나 키째 사라져도,
 *     요인이 종합 점수에서 빠진다는 사실만으로 그 요인의 문항이 계속 막힌다.
 *   - 영역(팩터) 제외 → factorColumns() → rules['factors'][code]['included_in_overall']
 *     "이 요인을 종합 점수에 넣을지"라는 개념이고, area_scores 컬럼 자체가
 *     SAF 를 원래 안 갖고 있어서(OyMsiScoringEngine.php:84-88) 함께 빠지는 것뿐이다.
 * safety_items 가 비거나 삭제돼도 문항 제외는 included_in_overall 조건으로 계속
 * 작동한다 — 두 조건이 각각 독립적으로 SAF 를 막는다.
 */
class ResponseExporter
{
    public const PROFILE_RESEARCH = 'research';
    public const PROFILE_INSTITUTION = 'institution';

    public function __construct(private SafetyAlert $safety = new SafetyAlert()) {}

    /** @return array<int, string> */
    public function headers(Test $test, string $profile): array
    {
        $this->assertProfile($profile);

        $itemCodes = $this->itemColumns($test, $profile);

        if ($profile === self::PROFILE_RESEARCH) {
            return array_merge(
                ['attempt_id', 'test_code', 'assessment_version', 'scoring_version',
                 'submitted_at', 'age_at_test', 'gender'],
                $itemCodes,
                $this->factorColumns($test, $profile),
                ['overall_signal', 'safety_level', 'environment_level',
                 'general_case_code', 'final_case_code', 'score_status', 'refused_items'],
            );
        }

        return array_merge(
            ['응시자', '발급일', '제출일', '연령', '성별'],
            $itemCodes,
            $this->factorColumns($test, $profile),
            ['종합신호등', '안전확인', '환경위험'],
        );
    }

    /** @return array<int, string|int|float|null> */
    public function row(TestAttempt $attempt, Test $test, string $profile): array
    {
        $this->assertProfile($profile);

        $attempt->loadMissing('answers', 'result', 'voucher');
        $test->loadMissing('items');

        $itemsById = $test->items->keyBy('id');
        $answersByCode = [];
        $refusedByCode = [];
        foreach ($attempt->answers as $answer) {
            $item = $itemsById[$answer->test_item_id] ?? null;
            if (!$item) continue;
            $column = $this->columnFor($item);
            // 역채점 전 원점수를 그대로 쓴다. 채점용으로 뒤집힌 값을 내보내면 원자료가 아니다.
            $answersByCode[$column] = $answer->value === null ? null : (int) $answer->value;
            // "안 봄"(미응답)과 "보고 거부함"(missing_code=PREFER_NOT)은 결측 메커니즘이
            // 다르다 — 아래에서 refused_items 로 별도 기록한다(연구용에만, headers() 참조).
            if ($answer->missing_code === AnswerValue::PREFER_NOT) {
                $refusedByCode[$column] = true;
            }
        }

        // 미응답은 빈칸으로 남긴다 — 0 으로 채우면 "전혀 아니다(0점)"와 구분이 사라진다.
        $itemValues = [];
        foreach ($this->itemColumns($test, $profile) as $column) {
            $itemValues[] = $answersByCode[$column] ?? null;
        }

        // 문항 번호(no) 순서로 고정한다 — attempt->answers 의 저장 순서에 기대지 않는다.
        // 연구용은 SAF 를 포함한 전체 문항이 대상이라 항상 PROFILE_RESEARCH 컬럼 순서로 본다.
        $refusedItems = array_values(array_filter(
            $this->itemColumns($test, self::PROFILE_RESEARCH),
            fn ($column) => $refusedByCode[$column] ?? false
        ));

        $result = $attempt->result;

        if ($profile === self::PROFILE_RESEARCH) {
            return $this->sanitizeCells(array_merge(
                [
                    $attempt->id,
                    $test->code,
                    $attempt->assessment_version,
                    $attempt->scoring_version,
                    optional($attempt->submitted_at)->format('Y-m-d H:i:s'),
                    $attempt->age_at_test,
                    $attempt->gender,
                ],
                $itemValues,
                $this->factorValues($test, $profile, $result),
                [
                    $result?->overall_signal,
                    $result?->safety_level,
                    $result?->environment_level,
                    $result?->general_case_code,
                    $result?->final_case_code,
                    $result?->score_status,
                    implode(';', $refusedItems),
                ],
            ));
        }

        $tier = $this->safety->safetyTier($result);

        return $this->sanitizeCells(array_merge(
            [
                $attempt->voucher?->recipient_name ?: $attempt->nickname,
                optional($attempt->voucher?->assigned_at)->format('Y-m-d'),
                optional($attempt->submitted_at)->format('Y-m-d'),
                $attempt->age_at_test,
                $attempt->gender,
            ],
            $itemValues,
            $this->factorValues($test, $profile, $result),
            [
                $result?->overall_signal,
                match ($tier) {
                    SafetyAlert::URGENT => '즉시',
                    SafetyAlert::SAMEDAY => '당일',
                    default => null,
                },
                $this->safety->hasEnvironmentAlert($result) ? '확인' : null,
            ],
        ));
    }

    /**
     * CSV 수식 인젝션 방지. 기관용 "응시자" 칸은 링크 응시자가 자유 입력한
     * nickname/recipient_name 이 살균 없이 그대로 들어오고, 이 파일은 설계상 엑셀에서
     * 열도록(BOM) 만들어졌다. 셀 값이 **문자열**이고 =, +, -, @, 탭, 캐리지리턴 중
     * 하나로 시작하면 앞에 작은따옴표를 붙여 엑셀이 수식으로 실행하지 못하게 한다.
     *
     * is_string() 으로 반드시 좁힌다 — 문항 원점수·연령 같은 숫자 값에 적용하면
     * 그 컬럼이 문자열이 되어 통계 분석이 깨진다.
     *
     * @param array<int, mixed> $row
     * @return array<int, mixed>
     */
    private function sanitizeCells(array $row): array
    {
        $dangerousPrefixes = ['=', '+', '-', '@', "\t", "\r"];

        return array_map(function ($value) use ($dangerousPrefixes) {
            if (!is_string($value) || $value === '') return $value;

            foreach ($dangerousPrefixes as $prefix) {
                if (str_starts_with($value, $prefix)) {
                    return "'".$value;
                }
            }

            return $value;
        }, $row);
    }

    public function filename(Test $test, string $profile): string
    {
        $this->assertProfile($profile);
        $label = $profile === self::PROFILE_RESEARCH ? 'research' : 'roster';

        return sprintf('%s_%s_%s.csv', $test->code, $label, now()->format('Ymd'));
    }

    /**
     * 기관용에서 뺄 문항 코드. 채점 룰에서 읽는다 — 하드코딩하지 않는다.
     *
     * **두 조건의 합집합**이다. 어느 한쪽만 있어도 SAF 는 막힌다(fail-closed):
     *   ① rules['safety_items'] — "이 검사의 자해·자살 문항이 무엇인가"라는 검사별 리터럴.
     *      개정판에서 문항이 늘거나 이 키가 통째로 빠지면 낡을 수 있다.
     *   ② rules['factors'][area]['included_in_overall'] === false 인 요인에 속한 문항 —
     *      factorColumns() 가 영역(팩터) 제외에 쓰는 것과 같은 근거다. ①이 비어도 이 조건이
     *      독립적으로 SAF 를 막는다. rules['factors'] 자체가 없는 검사(다른 채점 엔진)에서는
     *      이 조건이 아무것도 제외하지 않는다 — 그런 검사엔 안전요인 개념이 없다.
     * 한쪽 키가 비거나 삭제돼도 다른 쪽은 영향받지 않는다. 클래스 docblock 참조.
     *
     * @return array<int, string>
     */
    private function excludedItemCodes(Test $test, string $profile): array
    {
        if ($profile === self::PROFILE_RESEARCH) return [];

        $test->loadMissing('scoringRule', 'items');
        $rules = $test->scoringRule?->rules ?? [];

        $bySafetyList = $rules['safety_items'] ?? [];

        $excludedFactors = array_keys(array_filter(
            $rules['factors'] ?? [],
            fn ($def) => ($def['included_in_overall'] ?? false) === false
        ));

        $byExcludedFactor = $test->items
            ->filter(fn ($item) => in_array($item->area, $excludedFactors, true))
            ->map(fn ($item) => $item->item_code)
            ->filter()
            ->values()->all();

        return array_values(array_unique(array_merge($bySafetyList, $byExcludedFactor)));
    }

    /** @return array<int, string> */
    private function itemColumns(Test $test, string $profile): array
    {
        $test->loadMissing('items');
        $excluded = $this->excludedItemCodes($test, $profile);

        return $test->items
            ->reject(fn ($item) => in_array($item->item_code, $excluded, true))
            ->map(fn ($item) => $this->columnFor($item))
            ->values()->all();
    }

    /** item_code 가 없는 검사(레거시 샘플)도 열이 무너지지 않게 문항 번호로 대체한다. */
    private function columnFor($item): string
    {
        return $item->item_code ?: 'Q'.$item->no;
    }

    /**
     * 영역 점수 컬럼. 연구용은 engine_result.factors(SAF 포함), 기관용은 area_scores(SAF 제외).
     *
     * 여기서 SAF 를 빼는 근거는 rules['factors'][code]['included_in_overall'] 이지,
     * excludedItemCodes() 가 읽는 rules['safety_items'] 가 아니다 — 하나는 "종합
     * 점수에 넣을 요인인가", 하나는 "이 검사의 자해·자살 문항이 무엇인가"로 별개
     * 개념이다. safety_items 를 지워도 이 필터는 그대로 작동해 SAF_raw/SAF_band
     * 는 계속 숨는다. 클래스 docblock 참조.
     *
     * @return array<int, string>
     */
    private function factorColumns(Test $test, string $profile): array
    {
        $columns = [];
        foreach ($this->factorCodes($test, $profile) as $code) {
            $columns[] = $code.'_raw';
            $columns[] = $code.'_band';
        }

        return $columns;
    }

    /**
     * 요인/영역 코드 목록. rules['factors'] 가 있으면 그걸 쓰고(요인별 included_in_overall
     * 이 있는 OyMsiScoringEngine 류), 없으면 rules['areas'] 로 폴백한다 — SignalScoringEngine
     * 등 다른 채점 엔진은 factors 개념이 없고 area 문자열 키(rules['areas'])만 갖고 있어서,
     * factors 만 보면 영역 컬럼이 통째로 0개가 되어 area_scores/area_signals 데이터가 크래시도
     * 없이 조용히 CSV 에서 사라진다. areas 폴백에는 included_in_overall 개념이 없으므로
     * 두 프로필 모두 전부 포함으로 취급한다.
     *
     * @return array<int, string>
     */
    private function factorCodes(Test $test, string $profile): array
    {
        $test->loadMissing('scoringRule');
        $rules = $test->scoringRule?->rules ?? [];

        if (!empty($rules['factors'])) {
            $codes = array_keys($rules['factors']);

            if ($profile === self::PROFILE_INSTITUTION) {
                $codes = array_values(array_filter(
                    $codes,
                    fn ($code) => ($rules['factors'][$code]['included_in_overall'] ?? false) === true
                ));
            }

            return $codes;
        }

        return array_keys($rules['areas'] ?? []);
    }

    /** @return array<int, string|float|null> */
    private function factorValues(Test $test, string $profile, $result): array
    {
        $test->loadMissing('scoringRule');
        $engineFactors = $result?->engine_result['factors'] ?? [];
        $areaScores = $result?->area_scores ?? [];
        $areaSignals = $result?->area_signals ?? [];

        $values = [];
        foreach ($this->factorColumns($test, $profile) as $column) {
            [$code, $kind] = explode('_', $column, 2);

            if ($profile === self::PROFILE_RESEARCH) {
                // engine_result.factors 에 이 요인 값이 아예 없으면(SignalScoringEngine 처럼
                // engine_result 를 쓰지 않는 채점 엔진) area_scores/area_signals 로 폴백한다 —
                // 안 그러면 크래시 없이 영역 점수가 조용히 공란으로 나간다.
                if (array_key_exists($code, $engineFactors)) {
                    $values[] = $kind === 'raw'
                        ? ($engineFactors[$code]['raw'] ?? null)
                        : ($engineFactors[$code]['band'] ?? null);
                } else {
                    $values[] = $kind === 'raw'
                        ? ($areaScores[$code] ?? null)
                        : ($areaSignals[$code] ?? null);
                }
                continue;
            }

            $values[] = $kind === 'raw'
                ? ($areaScores[$code] ?? null)
                : ($areaSignals[$code] ?? null);
        }

        return $values;
    }

    private function assertProfile(string $profile): void
    {
        if (!in_array($profile, [self::PROFILE_RESEARCH, self::PROFILE_INSTITUTION], true)) {
            throw new InvalidArgumentException("알 수 없는 추출 프로필: {$profile}");
        }
    }
}
