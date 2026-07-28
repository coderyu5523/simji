<?php
namespace App\Services\OyMsi;

use App\Models\Test;
use Carbon\Carbon;

/**
 * 연령 게이트 규칙의 단일 출처.
 *
 * 진입점이 4개(개인 form/submit, 링크 form/submit)에 링크 start 재검증까지 있어서
 * 규칙을 컨트롤러마다 복사하면 한 곳만 빠져도 미성년자 통제가 뚫린다.
 * 판정은 전부 이 클래스를 통한다.
 *
 * 생년월일은 만 나이 계산에만 쓰고 어디에도 저장하지 않는다(개인정보 최소수집).
 */
class AgeGate
{
    public const OUT_OF_RANGE = 'out_of_range';
    public const GUARDIAN_PERSONAL = 'guardian_personal';
    public const GUARDIAN_LINK = 'guardian_link';
    public const UNKNOWN_AGE = 'unknown_age';

    /** 만 나이. */
    public function calculateAge(string $birthdate): int
    {
        return (int) Carbon::parse($birthdate)->age;
    }

    /**
     * 차단 사유. null 이면 통과.
     *
     * 나이를 모르면(=null) 통과가 아니라 UNKNOWN_AGE 다 — fail closed.
     *
     * @return null|self::OUT_OF_RANGE|self::GUARDIAN_PERSONAL|self::GUARDIAN_LINK|self::UNKNOWN_AGE
     */
    public function blockReason(Test $test, ?int $age, bool $guardianConfirmed, bool $isLink): ?string
    {
        if ($age === null) return self::UNKNOWN_AGE;

        if ($test->min_age !== null && $age < $test->min_age) return self::OUT_OF_RANGE;
        if ($test->max_age !== null && $age > $test->max_age) return self::OUT_OF_RANGE;

        if ($test->needsGuardianConsentFor($age)) {
            // 개인 경로에는 법정대리인 동의를 확인해 줄 사람이 없다 → 무조건 차단(기관 안내).
            // 링크 경로는 발급 기관 담당자가 오프라인으로 확인한 경우에만 통과.
            if (!$isLink) return self::GUARDIAN_PERSONAL;
            return $guardianConfirmed ? null : self::GUARDIAN_LINK;
        }

        return null;
    }
}
