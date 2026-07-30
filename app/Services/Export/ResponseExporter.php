<?php
namespace App\Services\Export;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\OyMsi\SafetyAlert;
use InvalidArgumentException;

/**
 * 검사 응답을 CSV 행으로 조립한다.
 *
 * 이 클래스는 검사 종류를 모른다 — 문항은 test->items, 제외 대상은 채점 룰의
 * safety_items 키에서 읽는다. 검사가 늘어도 여기를 고치지 않는다.
 *
 * 두 프로필의 차이는 설계 문서(2026-07-30-response-export-design.md) 참조:
 *   연구용   — 비식별, SAF 포함, 영역 점수를 engine_result.factors 에서 읽음
 *   기관용   — 이름 포함, SAF 문항·영역 제외, 영역 점수를 area_scores 에서 읽음
 *
 * ⚠ 기관용에서 SAF 를 빼는 경로가 두 개이고, 각각 **다른** 설정 키를 읽는다
 * (`OyMsiScoringEngine.php:84-88` 의 reportable 필터와 같은 방식). 하나만 보고
 * "SAF 는 다 걸렀다"고 오해하면 안 된다.
 *   - 문항(열) 제외  → excludedItemCodes()/itemColumns() → rules['safety_items']
 *     "이 검사에서 자해·자살을 직접 묻는 문항이 무엇인가"라는 검사별 목록이다.
 *   - 영역(팩터) 제외 → factorColumns() → rules['factors'][code]['included_in_overall']
 *     "이 요인을 종합 점수에 넣을지"라는 별개의 개념이고, area_scores 컬럼 자체가
 *     SAF 를 원래 안 갖고 있어서(OyMsiScoringEngine.php:84-88) 함께 빠지는 것뿐이다.
 * 한쪽 키(safety_items)가 비거나 삭제돼도 다른 쪽(included_in_overall)은 영향받지
 * 않는다 — 두 메커니즘은 독립적이다.
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
                 'general_case_code', 'final_case_code', 'score_status'],
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
        foreach ($attempt->answers as $answer) {
            $item = $itemsById[$answer->test_item_id] ?? null;
            if (!$item) continue;
            // 역채점 전 원점수를 그대로 쓴다. 채점용으로 뒤집힌 값을 내보내면 원자료가 아니다.
            $answersByCode[$this->columnFor($item)] = $answer->value === null ? null : (int) $answer->value;
        }

        // 미응답은 빈칸으로 남긴다 — 0 으로 채우면 "전혀 아니다(0점)"와 구분이 사라진다.
        $itemValues = [];
        foreach ($this->itemColumns($test, $profile) as $column) {
            $itemValues[] = $answersByCode[$column] ?? null;
        }

        $result = $attempt->result;

        if ($profile === self::PROFILE_RESEARCH) {
            return array_merge(
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
                ],
            );
        }

        $tier = $this->safety->safetyTier($result);

        return array_merge(
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
        );
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
     * 이 목록은 rules['safety_items'] 하나만 본다. 영역 단위 제외(factorColumns() 의
     * included_in_overall)와는 다른 키이고 서로 무관하다 — 이 키가 비어도 SAF_raw/
     * SAF_band 컬럼은 factorColumns() 가 별도로 걸러낸다. 클래스 docblock 참조.
     *
     * @return array<int, string>
     */
    private function excludedItemCodes(Test $test, string $profile): array
    {
        if ($profile === self::PROFILE_RESEARCH) return [];

        $test->loadMissing('scoringRule');

        return $test->scoringRule?->rules['safety_items'] ?? [];
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
        $test->loadMissing('scoringRule');
        $factors = array_keys($test->scoringRule?->rules['factors'] ?? []);

        if ($profile === self::PROFILE_INSTITUTION) {
            $factors = array_values(array_filter(
                $factors,
                fn ($code) => $test->scoringRule?->rules['factors'][$code]['included_in_overall'] ?? false
            ));
        }

        $columns = [];
        foreach ($factors as $code) {
            $columns[] = $code.'_raw';
            $columns[] = $code.'_band';
        }

        return $columns;
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
                $values[] = $kind === 'raw'
                    ? ($engineFactors[$code]['raw'] ?? null)
                    : ($engineFactors[$code]['band'] ?? null);
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
