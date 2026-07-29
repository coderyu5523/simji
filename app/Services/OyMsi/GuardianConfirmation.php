<?php
namespace App\Services\OyMsi;

use App\Models\User;
use App\Models\Voucher;

/**
 * 담당자의 법정대리인 동의 확인 규칙의 단일 출처.
 *
 * 판정이 두 곳에서 필요하다 — 뷰는 버튼을 보일지 정하고, 컨트롤러는 요청을 거부한다.
 * 규칙을 양쪽에 복사하면 한쪽만 고쳐졌을 때 화면에는 안 보이는데 POST 는 통과하는
 * 상태가 된다. AgeGate·ConsentGate 가 같은 이유로 서비스다.
 */
class GuardianConfirmation
{
    /** 확인·해제가 모두 열려 있는 조건. */
    public function canConfirm(Voucher $voucher, ?User $user): bool
    {
        if ($user === null) return false;

        return $voucher->user_id === $user->id           // 발급자 본인
            && $voucher->access_token !== null            // 링크로 발급된 것
            && $voucher->test?->requiresAgeVerification() // 연령확인이 필요한 검사
            && ! $this->hasStarted($voucher);             // 아직 응시 전
    }

    /** 뷰에서 해제 버튼을 보일지 정하는 용도. 컨트롤러 인가에는 쓰지 않는다(스펙 §7). */
    public function canRelease(Voucher $voucher, ?User $user): bool
    {
        return $this->canConfirm($voucher, $user)
            && $voucher->guardian_consent_confirmed_at !== null;
    }

    /**
     * 응시가 시작되었는가.
     *
     * attempts() 가 실질 판정이고 status 는 안전벨트다 — 둘 중 하나라도 참이면 잠근다(fail closed).
     * 기존 attempt() 는 used_attempt_id belongsTo 라 제출이 끝난 응시만 잡아 여기 쓸 수 없다.
     */
    public function hasStarted(Voucher $voucher): bool
    {
        // 명부는 검사권마다 이 판정을 부르므로 withCount('attempts') 로 미리 세어둔 값이 있으면 쓴다.
        // 없으면(단건 조회 등) 그때 질의한다.
        $started = $voucher->attempts_count !== null
            ? $voucher->attempts_count > 0
            : $voucher->attempts()->exists();

        return $started || $voucher->status === 'used';
    }
}
