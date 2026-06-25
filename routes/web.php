<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\Auth\KakaoController;
use App\Http\Controllers\ResultController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/tests', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/tests/room/{code}', [CatalogController::class, 'room'])->name('catalog.room');
Route::get('/tests/{code}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/my', fn() => '준비중')->name('my.index');

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

require __DIR__.'/auth.php';
