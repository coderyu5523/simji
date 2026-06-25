<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class KakaoController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('kakao')->redirect();
    }

    public function callback()
    {
        $k = Socialite::driver('kakao')->user();
        $user = User::firstOrCreate(
            ['email' => $k->getEmail() ?: "kakao_{$k->getId()}@simji.local"],
            ['name' => $k->getName() ?: '심지회원', 'password' => bcrypt(Str::random(32))]
        );
        if ($k->getName() && $user->name !== $k->getName()) { $user->update(['name' => $k->getName()]); }
        Auth::login($user, true);
        return redirect()->route('catalog.index');
    }
}
