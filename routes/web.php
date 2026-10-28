<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PublicLandingPageController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\Admin\LeadManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\LandingPageEventController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\Admin\PreorderManagementController;
use App\Http\Controllers\ZarinpalPaymentController;

Route::get('/login', [
    AuthController::class,
    'showLogin',
])->middleware('guest')->name('login');

Route::post('/login', [
    AuthController::class,
    'login',
])->middleware(['guest', 'throttle:5,1'])
    ->name('login.submit');

Route::post('/logout', [
    AuthController::class,
    'logout',
])->middleware('auth')->name('logout');

// صفحات عمومی
Route::get('/l/{slug}', [
    PublicLandingPageController::class,
    'show',
])->name('landing-pages.show');

Route::post('/l/{slug}/leads', [
    LeadController::class,
    'store',
])->middleware('throttle:5,1')->name('leads.store');

Route::post(
    '/l/{slug}/events',
    [LandingPageEventController::class, 'store']
)
    ->middleware('throttle:30,1')
    ->name('landing-pages.events.store');

Route::post(
    '/l/{slug}/preorders',
    [PreorderController::class, 'store']
)
    ->middleware('throttle:5,1')
    ->name('preorders.store');

Route::get(
    '/preorder/{token}/checkout',
    [ZarinpalPaymentController::class, 'checkout']
)->name('preorders.checkout');

Route::post(
    '/preorder/{token}/pay',
    [ZarinpalPaymentController::class, 'pay']
)
    ->middleware('throttle:5,1')
    ->name('preorders.pay');

Route::get(
    '/payments/zarinpal/callback',
    [ZarinpalPaymentController::class, 'callback']
)->name('payments.zarinpal.callback');

Route::get(
    '/payments/result',
    [ZarinpalPaymentController::class, 'result']
)->name('payments.result');

// پنل مدیریت
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });

    Route::get('/admin', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')
        ->name('admin.dashboard');

    Route::middleware('can:ideas.manage')->group(function () {
        Route::resource('ideas', IdeaController::class)
            ->only(['index', 'create', 'store', 'show']);

        Route::get('/ideas/{idea}/landing-pages/create', [
            LandingPageController::class,
            'create',
        ])->name('landing-pages.create');

        Route::post('/ideas/{idea}/landing-pages', [
            LandingPageController::class,
            'store',
        ])->name('landing-pages.store');
    });

    Route::middleware('can:leads.manage')->group(function () {
        Route::get('/admin/leads', [
            LeadManagementController::class,
            'index',
        ])->name('admin.leads.index');

        Route::patch('/admin/leads/{lead}', [
            LeadManagementController::class,
            'update',
        ])->name('admin.leads.update');
    });

    Route::middleware('can:preorders.manage')->group(function () {
        Route::get(
            '/admin/preorders',
            [PreorderManagementController::class, 'index']
        )->name('admin.preorders.index');

        Route::patch(
            '/admin/preorders/{preorder}',
            [PreorderManagementController::class, 'update']
        )->name('admin.preorders.update');
    });
});
