<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ExportController;
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
use App\Http\Controllers\LinkController;
use App\Http\Controllers\OyMsi\AgeGateController;
use App\Http\Controllers\OyMsi\GuardianConfirmController;
use App\Http\Controllers\OyMsi\ProfileStepController;
use App\Http\Controllers\OyMsi\ShareController;
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

// 연령 게이트 — 동의(consent)보다 앞선 단계. 만 나이만 세션에 담는다(생년월일 미저장)
Route::middleware('auth')->controller(AgeGateController::class)
    ->prefix('assessment/{code}')->name('oymsi.age.')->group(function () {
        Route::get('age', 'form')->name('form');
        Route::post('age', 'submit')->name('submit');
    });

// 기본정보(닉네임·성별) — 동의 이후, 응시(take) 이전 단계. 세션 attempt 가 없으면 403(fail closed).
Route::middleware('auth')->controller(ProfileStepController::class)
    ->prefix('assessment/{code}')->name('oymsi.profile.')->group(function () {
        Route::get('profile', 'form')->name('form');
        Route::post('profile', 'submit')->name('submit');
    });

// 검사 진행은 무료·유료 구분 없이 로그인 필수 (비회원 응시 불가)
Route::middleware('auth')->controller(AssessmentController::class)->prefix('assessment/{code}')->name('assessment.')->group(function () {
    Route::get('consent', 'consent')->name('consent');
    Route::post('agree', 'agree')->name('agree');
    Route::get('intro', 'intro')->name('intro');
    Route::post('start', 'start')->name('start');
    Route::get('take/{attempt}', 'take')->name('take');
    Route::post('take/{attempt}', 'submit')->name('submit');
});

Route::get('/result/{attempt}', [ResultController::class, 'show'])->name('result.show');

// 보호자용 결과 공유 — 발급·철회는 응시자 본인(로그인 필수)
Route::middleware('auth')->controller(ShareController::class)
    ->prefix('result/{attempt}/share')->name('oymsi.share.')->group(function () {
        Route::get('/', 'form')->name('form');
        Route::post('/', 'create')->name('create');
        Route::post('revoke', 'revoke')->name('revoke');
    });

// 공유 링크 열람 — 로그인 불필요. 만료·철회·미존재 토큰은 전부 404(ShareController::view)
Route::get('/r/{token}', [ShareController::class, 'view'])->name('oymsi.share.view');

// 링크 수신자용 연령 게이트 (로그인 불필요 — landing 보다 앞선 단계)
Route::controller(AgeGateController::class)->prefix('t/{token}')->name('link.age.')->group(function () {
    Route::get('age', 'linkForm')->name('form');
    Route::post('age', 'linkSubmit')->name('submit');
});

// 검사권 링크 응시 (로그인 불필요 — 발급자가 전달한 링크로 대상자가 직접 응시)
Route::controller(LinkController::class)->prefix('t/{token}')->name('link.')->group(function () {
    Route::get('/', 'landing')->name('landing');
    Route::post('start', 'start')->name('start');
    Route::get('take/{attempt}', 'take')->name('take');
    Route::post('take/{attempt}', 'submit')->name('submit');
});

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
    Route::post('/my/issue', [MyTestController::class, 'issue'])->name('my.issue');
    Route::post('/my/vouchers/{voucher}/visibility', [MyTestController::class, 'toggleVisibility'])->name('my.voucher.visibility');
    Route::post('/my/vouchers/{voucher}/guardian-consent', [GuardianConfirmController::class, 'confirm'])->name('my.voucher.guardian.confirm');
    Route::post('/my/vouchers/{voucher}/guardian-consent/release', [GuardianConfirmController::class, 'release'])->name('my.voucher.guardian.release');
    Route::get('/checkout/{product}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{product}', [CheckoutController::class, 'start'])->name('checkout.start');
});

// payment.return: PG가 호출하므로 auth 그룹 밖(세션 없을 수 있음)
Route::match(['get','post'], '/payment/return', [PaymentController::class, 'return'])->name('payment.return');
Route::middleware('auth')->group(function () {
    Route::get('/payment/complete/{order}', [PaymentController::class, 'complete'])->name('payment.complete');
    Route::get('/payment/fail', [PaymentController::class, 'fail'])->name('payment.fail');
});

// 관리자 — 로그인 + is_admin 필요. 관리자 임명은 SQL/커맨드로 한다(임명 UI 는 아직 없음).
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')
    ->controller(AdminController::class)->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/members', 'members')->name('members');
        Route::get('/orders', 'orders')->name('orders');
        Route::get('/tests', 'tests')->name('tests');
    });

// 연구용 응답 추출 — 관리자 전용. 자살·자해 문항이 포함되므로 가드가 전제다.
Route::middleware(['auth', 'admin'])
    ->get('/admin/exports/{test:code}/research', [ExportController::class, 'research'])
    ->name('admin.exports.research');

require __DIR__.'/auth.php';
