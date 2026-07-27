<?php
namespace App\Services\Scoring\OyMsi;

class SafetyEvaluator
{
    /** 높은 등급부터 검사한다 */
    private const LEVELS = ['S3', 'S2', 'S1'];

    public function __construct(private ConditionMatcher $matcher = new ConditionMatcher()) {}

    /** @param array<string, int|null> $rawByItemCode */
    public function evaluate(array $rawByItemCode, array $rules): string
    {
        foreach (self::LEVELS as $level) {
            $conditions = $rules['safety'][$level] ?? [];
            if ($this->matcher->anyMatches($conditions, $rawByItemCode)) {
                return $level;
            }
        }

        // 007 §5.3 / 003 — 안전문항 응답거부·무응답은 0점 처리 금지, 최소 S1
        if ($this->hasMissingSafetyItem($rawByItemCode, $rules)) {
            return $rules['safety_missing_min_level'] ?? 'S1';
        }

        return 'S0';
    }

    private function hasMissingSafetyItem(array $rawByItemCode, array $rules): bool
    {
        foreach ($rules['safety_items'] ?? [] as $code) {
            if (!array_key_exists($code, $rawByItemCode) || $rawByItemCode[$code] === null) {
                return true;
            }
        }
        return false;
    }
}
