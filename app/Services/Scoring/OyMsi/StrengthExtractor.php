<?php
namespace App\Services\Scoring\OyMsi;

class StrengthExtractor
{
    private const FALLBACK = 'HONEST_RESPONSE';

    public function __construct(private ConditionMatcher $matcher = new ConditionMatcher()) {}

    /**
     * 강점은 역채점 전 원점수(raw) 기준이다 — FUT04~06 은 "높을수록 긍정" 문항.
     * @return list<string>
     */
    public function extract(array $rawByItemCode, array $rules): array
    {
        $found = [];
        foreach ($rules['strengths'] as $code => $rule) {
            if ($rule['always'] ?? false) continue; // fallback 은 마지막에
            if ($this->matcher->matches($rule, $rawByItemCode)) $found[] = $code;
        }

        // 005 §9.3 — 강점 최소 1개 보장
        return $found ?: [self::FALLBACK];
    }
}
