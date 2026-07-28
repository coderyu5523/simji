<?php

namespace App\Http\Controllers\OyMsi;

use App\Http\Controllers\Controller;
use App\Models\ReportShare;
use App\Models\TestAttempt;
use App\Services\OyMsi\ReportComposer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * OY_MSI 보호자용 결과 공유.
 *
 * 청소년 본인이 자기 결과를 보호자에게 보여주려고 만드는 링크다.
 * `GET /r/{token}` 은 로그인 없이 열리므로 — 미성년자의 정신건강 정보가 URL 하나로
 * 열린다 — 아래 규칙을 지킨다.
 *
 *  1. 토큰은 암호학적 난수 48자(Str::random → random_bytes). 순차 ID·짧은 문자열 금지.
 *  2. 유효기간 30일(self::EXPIRES_DAYS). 무기한 링크는 만들지 않는다.
 *  3. 만료·철회된 토큰과 없는 토큰은 전부 404 — 존재 여부를 구별해 흘리지 않는다.
 *  4. 발급은 응시자 본인만(TestAttempt::isOwnedBy). 남의 attempt 로 만들 수 없다.
 *  5. 첫 열람 시각을 viewed_at 에 남긴다.
 *  6. 로그인 없는 새 진입점이 기존 게이트를 우회하지 않는다 —
 *     · OY_MSI 전용(다른 검사 attempt 로는 진입 자체가 404)
 *     · 채점 결과가 없으면 404
 *     · 발급자가 열람을 막아둔 결과(voucher.result_visible=false)는 발급도 열람도 불가.
 *       ResultController::show 의 열람 통제와 같은 기준이고, 이미 나간 링크도 그 시점에 닫힌다.
 *       단 **철회(revoke)는 예외로 언제나 허용**한다 — revoke() 주석 참조.
 *  7. attempt 당 살아 있는 링크는 하나다. 이미 유효한 링크가 있으면 새로 만들지 않고
 *     그것을 다시 보여준다(중복 제출로 유출 대상 비밀이 늘어나지 않게).
 */
class ShareController extends Controller
{
    /** 공유 링크 유효기간(일). 무기한 금지 — 브리프 지정값. */
    private const EXPIRES_DAYS = 30;

    public function form(Request $request, TestAttempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);
        if ($blocked = $this->blockedResponse($attempt)) {
            return $blocked;
        }
        $engine = $attempt->result->engine_result;

        return view('oymsi.share-form', [
            'attempt' => $attempt,
            'needsContactFirst' => $this->needsContactFirst($engine),
            'existing' => $this->activeShare($attempt),
        ]);
    }

    public function create(Request $request, TestAttempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);
        if ($blocked = $this->blockedResponse($attempt)) {
            return $blocked;
        }

        $share = $this->activeShare($attempt) ?? ReportShare::create([
            'attempt_id' => $attempt->id,
            'audience' => 'guardian',
            'token' => Str::random(48),
            'source' => 'youth_self',
            'created_by' => auth()->id(),
            'expires_at' => now()->addDays(self::EXPIRES_DAYS),
        ]);

        return response()->view('oymsi.share-created', [
            'attempt' => $attempt,
            'url' => route('oymsi.share.view', $share->token),
            'expiresAt' => $share->expires_at,
        ]);
    }

    /**
     * ★ 철회에는 isShareable(열람 공개 여부) 가드를 걸지 않는다.
     *
     * 기관이 결과를 '대기'로 되돌린 사이에도 청소년은 자기가 만든 링크를 취소할 수 있어야
     * 한다. 막아 두면, 기관이 다시 공개하는 순간 청소년이 취소할 기회를 한 번도 갖지 못한 채
     * 옛 링크가 되살아난다. 화면이 한 약속("언제든 공유를 취소할 수 있어")과도 어긋난다.
     * 철회는 언제나 안전한 방향의 동작이므로 소유권과 대상 확인만 하고 통과시킨다.
     */
    public function revoke(Request $request, TestAttempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);
        if ($blocked = $this->blockedResponse($attempt, requireShareable: false)) {
            return $blocked;
        }
        $attempt->shares()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return redirect()->route('result.show', $attempt->id);
    }

    /** 로그인 불필요 — 토큰만으로 연다. 실패는 전부 404(fail closed). */
    public function view(string $token, ReportComposer $composer)
    {
        $share = ReportShare::where('token', $token)->first();
        abort_unless($share && $share->isUsable(), 404);

        $attempt = $share->attempt()->with('result', 'voucher', 'test.scoringRule')->first();
        abort_unless($attempt && $attempt->result && $this->isShareable($attempt), 404);

        // 첫 열람만 기록한다 — "언제 처음 열렸는가" 가 남아야 할 사실이다.
        if ($share->viewed_at === null) {
            $share->update(['viewed_at' => now()]);
        }

        // 로그인 없이 열리는 URL 이므로 검색엔진 색인과 브라우저·프록시 캐시를 막는다
        // (공용 PC 에서 뒤로가기로 다시 뜨는 것까지 포함).
        return response()
            ->view('oymsi.guardian-result', [
                'attempt' => $attempt,
                'test' => $attempt->test,
                'expiresAt' => $share->expires_at,
                'sections' => $composer->compose($attempt->result, 'GUARDIAN'),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    /**
     * 이 attempt 의 공유 화면에 들어올 자격 자체가 있는가.
     * 여기서 막히는 건 URL 을 직접 건드린 경우뿐이라 기본 오류 화면으로 둔다.
     */
    private function authorizeOwner(Request $request, TestAttempt $attempt): void
    {
        $attempt->loadMissing('result', 'voucher', 'test');

        abort_unless($attempt->isOwnedBy($request), 403);
        // 공유 대상이 아닌 검사 — 전용 문안·안전등급 구조가 없어 조립 자체가 불가능하다.
        abort_unless($attempt->test->scoring_engine === 'oy_msi', 404);
    }

    /**
     * 차단 사유가 있으면 "왜 막혔는지 보이는" 화면을 돌려준다. 통과면 null.
     *
     * 상태코드(404/403)는 그대로 유지한다 — 차단을 푸는 게 아니라, 위기 상태일 수 있는
     * 청소년이 공유를 시도했을 때 프레임워크 기본 오류 페이지를 마주하지 않게 하는 것이다.
     * (기존 ResultController::show 가 같은 조건에서 result.pending 안내를 주는 것과 같은 결.)
     *
     * $requireShareable=false — 철회 경로. revoke() 주석 참조.
     */
    private function blockedResponse(TestAttempt $attempt, bool $requireShareable = true): ?Response
    {
        if ($attempt->result === null) {
            return $this->unavailable($attempt, 'not_scored', 404);
        }

        if ($requireShareable && !$this->isShareable($attempt)) {
            return $this->unavailable($attempt, 'result_hidden', 403);
        }

        return null;
    }

    private function unavailable(TestAttempt $attempt, string $reason, int $status): Response
    {
        return response()->view('oymsi.share-unavailable', [
            'attempt' => $attempt,
            'test' => $attempt->test,
            'reason' => $reason,
        ], $status);
    }

    /**
     * 발급자(기관)가 열람을 '대기'로 막아둔 결과인가.
     * ResultController::show 와 같은 기준 — 본인도 못 보는 결과를 보호자에게 넘길 수 없다.
     */
    private function isShareable(TestAttempt $attempt): bool
    {
        return $attempt->voucher === null || (bool) $attempt->voucher->result_visible;
    }

    private function activeShare(TestAttempt $attempt): ?ReportShare
    {
        return $attempt->shares()
            ->where('audience', 'guardian')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /**
     * spec §5.3 — 자살안전 S2 이상 또는 환경위험 E2 이상이면 공유보다 연결이 먼저다.
     * 환경축을 포함하는 이유: 환경위험 발동 문항에는 가정 내 폭력·학대가 포함되고
     * (003 Ⅴ.3 은 이 경우 보호자 통보가 위험을 높일 수 있음을 담당자 지침으로 명시한다)
     * 이때 보호자 공유를 1순위로 들이미는 것이 오히려 위험을 키울 수 있다.
     * 반대로 같은 축에 학교폭력·온라인 성착취·급성중독처럼 가정 밖 출처도 있으므로
     * 공유를 일괄 차단하지는 않는다 — 도와줄 수 있는 보호자까지 끊긴다.
     */
    private function needsContactFirst(array $engine): bool
    {
        return max(
            (int) substr($engine['safety']['suicide_level'], 1),
            (int) substr($engine['safety']['environment_level'], 1)
        ) >= 2;
    }
}
