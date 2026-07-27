<?php
namespace App\Services\Scoring;

use App\Models\TestAttempt;
use App\Models\TestResult;

interface ScoringEngine
{
    public function score(TestAttempt $attempt): TestResult;
}
