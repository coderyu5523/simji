<?php
namespace App\Services;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Services\Scoring\OyMsi\OyMsiScoringEngine;
use App\Services\Scoring\ScoringEngine;
use App\Services\Scoring\SignalScoringEngine;
use InvalidArgumentException;

class ScoringService
{
    public const ENGINES = [
        'signal' => SignalScoringEngine::class,
        'oy_msi' => OyMsiScoringEngine::class,
    ];

    public function engineFor(Test $test): ScoringEngine
    {
        $key = $test->scoring_engine ?: 'signal';
        if (!isset(self::ENGINES[$key])) {
            throw new InvalidArgumentException("알 수 없는 채점 엔진: {$key}");
        }
        return app(self::ENGINES[$key]);
    }

    public function score(TestAttempt $attempt): TestResult
    {
        $attempt->loadMissing('test');
        return $this->engineFor($attempt->test)->score($attempt);
    }
}
