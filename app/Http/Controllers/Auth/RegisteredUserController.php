<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * 가입 방식 선택 화면 (카카오 / 개인 / 기관).
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * 개인 회원가입 폼.
     */
    public function createPersonal(): View
    {
        return view('auth.register-personal');
    }

    /**
     * 기관 회원가입 폼.
     */
    public function createInstitution(): View
    {
        return view('auth.register-institution');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_type' => ['required', 'in:personal,institution'],
            'name' => ['required', 'string', 'max:255'],
            'organization' => [Rule::requiredIf($request->user_type === 'institution'), 'nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => '이용약관 및 개인정보 수집에 동의해 주세요.',
            'organization.required' => '기관명을 입력해 주세요.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'user_type' => $request->user_type,
            'organization' => $request->user_type === 'institution' ? $request->organization : null,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'marketing_agree' => $request->boolean('marketing'),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('my.index', absolute: false));
    }
}
