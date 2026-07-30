<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 관리자 전용 화면 가드.
 *
 * 비로그인은 auth 미들웨어가 로그인 화면으로 보내고, 여기서는 "로그인은 했지만
 * 관리자가 아닌" 경우만 403 으로 막는다. 404 로 숨기지 않는 이유는 관리자 화면의
 * 존재 자체가 비밀이 아니고, 403 이 운영 중 오진단을 줄이기 때문이다.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return $next($request);
    }
}
