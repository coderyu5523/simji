<?php
namespace App\Services\OyMsi;

use App\Models\TestResult;
use Illuminate\Support\Facades\Log;

/**
 * 담당자 명부에 드러낼 안전·환경 경보 판정.
 *
 * 왜 필요한가: 명부 배지는 `overall_signal`(종합 신호등)인데 **안전(SAF) 영역은 종합
 * 점수에서 제외**된다(`included_in_overall = false`). 003 Ⅷ 이 "자해·자살 및 학대위험은
 * 총점보다 개별 응답을 우선한다"고 규정하기 때문이다. 그래서 실제 자살시도를 보고한
 * 응시가 명부에 "초록"으로 보일 수 있다. 안전등급을 따로 드러내지 않으면 담당자가
 * 카드를 하나하나 열어보기 전에는 알 수 없다.
 *
 * 003 Ⅴ-3 의 대응 시간 규정을 그대로 따른다.
 *   빨강 → 즉시 안전평가          (엔진 S3)
 *   노랑 → 가능한 한 당일 확인    (엔진 S1·S2, 안전문항 무응답 포함)
 *   초록 → 표시 없음              (엔진 S0)
 */
class SafetyAlert
{
    /** 003 빨강 — 즉시 안전평가 */
    public const URGENT = 'urgent';
    /** 003 노랑 — 가능한 한 당일 확인 + 24~72시간 내 후속 */
    public const SAMEDAY = 'sameday';

    /**
     * 안전등급 배지 단계. null 이면 표시하지 않는다.
     *
     * 모르는 값은 낮추지 않고 URGENT 로 올린다(fail closed) — 표시 누락으로 위험을
     * 놓치는 쪽보다 과하게 알리는 쪽이 안전하다. 예외를 던지지 않는 이유는, 한 줄의
     * 데이터 이상으로 명부 전체가 500 이 되면 **다른 응시자의 경보까지 가려지기**
     * 때문이다. 대신 로그로 남긴다.
     */
    public function safetyTier(?TestResult $result): ?string
    {
        $level = $result?->safety_level;

        if ($level === null || $level === 'S0') return null;
        if ($level === 'S3') return self::URGENT;
        if ($level === 'S1' || $level === 'S2') return self::SAMEDAY;

        Log::warning('알 수 없는 safety_level — 즉시 확인으로 처리했습니다.', [
            'result_id' => $result->id,
            'safety_level' => $level,
        ]);

        return self::URGENT;
    }

    /**
     * 환경위험(폭력·학대·온라인 성착취) 경보 여부.
     *
     * ⚠ E1/E2/E3 의 심각도 매핑이 003·007 어디에도 없어 **E2 이상을 경보로 보는 것은
     * 잠정 기준**이다(`docs/oy-msi-handover.md` §2-6, 저자 확인 대기). 회신이 오면
     * 이 메서드만 고치면 된다.
     */
    public function hasEnvironmentAlert(?TestResult $result): bool
    {
        return in_array($result?->environment_level, ['E2', 'E3'], true);
    }

    public function needsAttention(?TestResult $result): bool
    {
        return $this->safetyTier($result) !== null || $this->hasEnvironmentAlert($result);
    }

    /**
     * 명부 상단 배너용 집계.
     *
     * @param iterable<TestResult|null> $results
     * @return array{urgent:int, sameday:int, environment:int, total:int}
     */
    public function summarize(iterable $results): array
    {
        $urgent = $sameday = $environment = $total = 0;

        foreach ($results as $result) {
            $tier = $this->safetyTier($result);
            $env = $this->hasEnvironmentAlert($result);

            if ($tier === self::URGENT) $urgent++;
            if ($tier === self::SAMEDAY) $sameday++;
            if ($env) $environment++;
            if ($tier !== null || $env) $total++;
        }

        return compact('urgent', 'sameday', 'environment', 'total');
    }
}
