<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CoachingController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\Auth\KakaoController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\MyTestController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/tests', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/tests/room/{code}', [CatalogController::class, 'room'])->name('catalog.room');
Route::get('/tests/{code}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/my', [MyTestController::class, 'index'])->name('my.index');
Route::get('/coaching', [CoachingController::class, 'index'])->name('coaching');

// 목업 페이지 (백엔드 미연결, 화면·목데이터만) — Phase 2에서 실제 구현 예정
Route::get('/institution',  fn () => view('institution'))->name('institution');   // 기관·단체 도입
Route::get('/report-sample',fn () => view('report-sample'))->name('report.sample'); // 리포트 샘플
Route::get('/experts',      fn () => view('experts'))->name('experts');            // 전문가·파트너
Route::get('/about',        fn () => view('about'))->name('about');                // 심지 소개
Route::get('/support',      fn () => view('support'))->name('support');            // 고객센터

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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/checkout/{product}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{product}', [CheckoutController::class, 'start'])->name('checkout.start');
});

// payment.return: PG가 호출하므로 auth 그룹 밖(세션 없을 수 있음)
Route::match(['get','post'], '/payment/return', [PaymentController::class, 'return'])->name('payment.return');
Route::middleware('auth')->group(function () {
    Route::get('/payment/complete/{order}', [PaymentController::class, 'complete'])->name('payment.complete');
    Route::get('/payment/fail', [PaymentController::class, 'fail'])->name('payment.fail');
});

// 관리자 (임시 — 인증 가드 없음, 직접 URL 접근. ⚠️ 실서비스 전 auth 미들웨어 필수)
Route::prefix('admin')->name('admin.')->controller(AdminController::class)->group(function () {
    Route::get('/', 'dashboard')->name('dashboard');
    Route::get('/members', 'members')->name('members');
    Route::get('/orders', 'orders')->name('orders');
    Route::get('/tests', 'tests')->name('tests');
});

require __DIR__.'/auth.php';
