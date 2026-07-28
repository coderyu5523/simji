<?php
namespace App\Services\Scoring\OyMsi;

class EnvironmentEvaluator
{
    private const LEVELS = ['E3', 'E2', 'E1'];

    /**
     * 007 §9.5 — alert_bonus 는 "해당 요인에 HIGH/CRITICAL 경보가 있으면" 붙는다.
     * E2=HIGH, E3=CRITICAL 로 본다(003/007 의 S2=HIGH·S3=CRITICAL 관례와 동일하게
     * 맞춤 — 기존 OyMsiScoringEngine::alertFactors() 의 안전요인 분기도
     * safetyLevel rank>=2 를 기준으로 삼는다). E1 은 WARN 수준으로 간주해 제외한다.
     */
    private const ALERT_BONUS_LEVELS = ['E3', 'E2'];

    public function __construct(private ConditionMatcher $matcher = new ConditionMatcher()) {}

    /** @param array<string, int|null> $rawByItemCode */
    public function evaluate(array $rawByItemCode, array $rules): string
    {
        foreach (self::LEVELS as $level) {
            if ($this->matcher->anyMatches($rules['environment'][$level] ?? [], $rawByItemCode)) {
                return $level;
            }
        }
        return 'E0';
    }

    /**
     * 007 §7.3(환경 문항→요인 1:1 매핑) + §9.5(alert_bonus 는 "해당 요인"에만) —
     * E2/E3 조건 중 실제로 만족된 것만, 그 조건에 데이터로 명시된 factor 에 귀속시킨다.
     * environment_level(E0~E3, 사례코드 격상용)과 달리 "가장 높은 등급 하나"가 아니라
     * 만족된 조건 전부를 훑어 해당되는 요인을 전부 모은다 — 문항마다 요인이 다를 수
     * 있기 때문이다(예: RSK05 단독 경보는 RSK 만, TRM06 단독 경보는 TRM 만).
     *
     * @param  array<string, int|null>  $rawByItemCode
     * @return list<string>  경보가 걸린 요인 코드(중복 제거, 순서 무관)
     */
    public function alertedFactors(array $rawByItemCode, array $rules): array
    {
        $factors = [];
        foreach (self::ALERT_BONUS_LEVELS as $level) {
            foreach ($rules['environment'][$level] ?? [] as $condition) {
                if (!$this->matcher->matches($condition, $rawByItemCode)) continue;
                $factor = $condition['factor'] ?? throw new \InvalidArgumentException(
                    "scoring_rules.environment.{$level} 조건에 factor 가 없습니다 — "
                    . "alert_bonus 를 어느 요인에 줄지 정의되지 않았습니다: " . json_encode($condition)
                );
                $factors[$factor] = true;
            }
        }
        return array_keys($factors);
    }
}
