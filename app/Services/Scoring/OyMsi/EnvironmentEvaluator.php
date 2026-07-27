<?php
namespace App\Services\Scoring\OyMsi;

class EnvironmentEvaluator
{
    private const LEVELS = ['E3', 'E2', 'E1'];

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
}
