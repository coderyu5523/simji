<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * `?next=` 를 받으면 로그인 후 그 주소로 돌려보낸다.
     *
     * auth 미들웨어가 막아서 로그인으로 보낸 경우는 프레임워크가 url.intended 를 알아서
     * 저장하지만, 공개 페이지의 "로그인하고 …" 버튼처럼 로그인 화면으로 **직접** 링크한
     * 경우에는 아무것도 저장되지 않아 매번 기본 목적지로 떨어진다. 그 빈틈을 메운다.
     */
    public function create(Request $request): View
    {
        if ($next = $this->safeIntendedPath($request, $request->query('next'))) {
            $request->session()->put('url.intended', $next);
        }

        return view('auth.login');
    }

    /**
     * next 는 주소창에서 조작할 수 있는 값이다. 같은 사이트 안의 경로만 허용해
     * 오픈 리다이렉트(피싱 사이트로 튕기기)를 막는다.
     */
    private function safeIntendedPath(Request $request, mixed $next): ?string
    {
        if (!is_string($next) || $next === '') return null;

        // 프로토콜 상대 주소(//evil.com)와 역슬래시 변형(/\evil.com)은 외부로 나간다
        if (str_starts_with($next, '//') || str_starts_with($next, '/\\')) return null;

        if (str_starts_with($next, '/')) return $next;

        $parts = parse_url($next);
        if (!is_array($parts) || ($parts['host'] ?? null) !== $request->getHost()) return null;

        return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('my.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
