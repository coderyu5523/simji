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

        // (a) fail closed — 연령에 따라 법정대리인 동의 여부가 갈리는 검사인데 나이를 모르면 막는다.
        // needsGuardianConsentFor(null) 이 false 라서 "동의 불필요"로 오판하고 통과시키던 fail-open 구멍
        // (Task 12 리뷰 Critical). 정상 플로우는 연령 게이트가 항상 나이를 채우므로 여기 걸리지 않는다 —
        // 이 검사는 정상 플로우 밖(직접 DB 조작·미래의 다른 진입점)에 대한 방어선이다.
        // consent_required 판정보다 먼저 둔다: 동의 수집 여부와 무관하게 성립하는 법적 제약이다.
        if ($attempt->test->requiresAgeVerification() && $attempt->age_at_test === null) {
            abort(403, '연령 확인이 되지 않아 검사를 진행할 수 없습니다.');
        }

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
