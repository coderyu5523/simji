<?php
namespace App\Services\Scoring\OyMsi;

class StrengthExtractor
{
    public function __construct(private ConditionMatcher $matcher = new ConditionMatcher()) {}

    /**
     * 강점은 역채점 전 원점수(raw) 기준이다 — FUT04~06 은 "높을수록 긍정" 문항.
     * 기본 강점(always=>true) 코드도 rules 에서 가져온다 — 리터럴로 하드코딩하지 않는다.
     * @return list<string>
     */
    public function extract(array $rawByItemCode, array $rules): array
    {
        $found = [];
        $fallbackCode = null;
        foreach ($rules['strengths'] as $code => $rule) {
            if ($rule['always'] ?? false) { $fallbackCode = $code; continue; } // fallback 은 마지막에
            if ($this->matcher->matches($rule, $rawByItemCode)) $found[] = $code;
        }

        if ($found) return $found;

        // 005 §9.3 — 강점 최소 1개 보장
        if ($fallbackCode === null) {
            throw new \InvalidArgumentException(
                'scoring_rules.strengths 에 always=true 인 기본 강점이 없습니다 — 강점 최소 1개 보장(005 §9.3)을 지킬 수 없습니다.'
            );
        }

        return [$fallbackCode];
    }
}
