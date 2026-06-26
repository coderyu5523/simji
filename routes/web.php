<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CoachingController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\Auth\KakaoController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\MyTestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/tests', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/tests/room/{code}', [CatalogController::class, 'room'])->name('catalog.room');
Route::get('/tests/{code}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/my', [MyTestController::class, 'index'])->name('my.index');
Route::get('/coaching', [CoachingController::class, 'index'])->name('coaching');

// 준비중(coming soon) 정적 페이지 — Phase 2에서 실제 구현 예정
foreach ([
    'about'         => ['/about',         '심지 소개'],
    'institution'   => ['/institution',   '기관·단체'],
    'report.sample' => ['/report-sample', '리포트 샘플'],
    'support'       => ['/support',       '고객센터'],
] as $name => [$path, $heading]) {
    Route::get($path, fn () => view('coming-soon', ['heading' => $heading]))->name($name);
}

Route::controller(AssessmentController::class)->prefix('assessment/{code}')->name('assessment.')->group(function () {
    Route::get('consent', 'consent')->name('consent');
    Route::post('agree', 'agree')->name('agree');
    Route::get('intro', 'intro')->name('intro');
    Route::post('start', 'start')->name('start');
    Route::get('take/{attempt}', 'take')->name('take');
    Route::post('take/{attempt}', 'submit')->name('submit');
});

Route::get('/result/{attempt}', [ResultController::class, 'show'])->name('result.show');

Route::get('/guest/start', function () {
    if (!session('guest_token')) session(['guest_token' => (string) \Illuminate\Support\Str::uuid()]);
    return redirect()->route('catalog.index');
})->name('guest.start');

Route::get('/auth/kakao', [KakaoController::class, 'redirect'])->name('kakao.redirect');
Route::get('/auth/kakao/callback', [KakaoController::class, 'callback'])->name('kakao.callback');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// TODO Task 5: replace with real PaymentController@return
Route::match(['get','post'], '/payment/return', fn () => abort(404))->name('payment.return');

require __DIR__.'/auth.php';
