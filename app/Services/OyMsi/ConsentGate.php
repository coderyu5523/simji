<?php
namespace App\Services\OyMsi;

use App\Models\ConsentRecord;
use App\Models\TestAttempt;

class ConsentGate
{
    public const SENSITIVE = 'sensitive';
    public const GUARDIAN_OFFLINE = 'guardian_offline';

    public function record(
        TestAttempt $attempt,
        string $type,
        string $actor,
        ?int $actorUserId = null,
        array $meta = []
    ): ConsentRecord {
        return ConsentRecord::create([
            'attempt_id' => $attempt->id,
            'consent_type' => $type,
            'granted' => true,
            'granted_at' => now(),
            'actor' => $actor,
            'actor_user_id' => $actorUserId,
            'meta' => $meta ?: null,
        ]);
    }

    public function has(TestAttempt $attempt, string $type): bool
    {
        return $attempt->consents()
            ->where('consent_type', $type)->where('granted', true)->exists();
    }

    /** 이 검사가 요구하는 동의가 다 있는지. 없으면 403. */
    public function assertSatisfied(TestAttempt $attempt): void
    {
        $attempt->loadMissing('test');
        if (!$attempt->test->consent_required) return;

        abort_unless($this->has($attempt, self::SENSITIVE), 403, '검사 전 동의가 확인되지 않았습니다.');

        if ($attempt->test->needsGuardianConsentFor($attempt->age_at_test)) {
            abort_unless(
                $this->has($attempt, self::GUARDIAN_OFFLINE),
                403,
                '만 14세 미만은 법정대리인 동의 확인이 필요합니다.'
            );
        }
    }
}
