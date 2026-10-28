<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\IdeaSettingsController;
use App\Http\Controllers\RefundPolicyController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PublicLandingPageController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\Admin\LeadManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\LandingPageEventController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\Admin\PaymentReportController;
use App\Http\Controllers\Admin\PreorderManagementController;
use App\Http\Controllers\Admin\UserController;
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
)->middleware('throttle:30,1')
    ->name('preorders.checkout');

Route::post(
    '/preorder/{token}/pay',
    [ZarinpalPaymentController::class, 'pay']
)
    ->middleware('throttle:5,1')
    ->name('preorders.pay');

Route::get(
    '/preorder/{token}/receipt',
    [ZarinpalPaymentController::class, 'receipt']
)->middleware('throttle:30,1')
    ->name('preorders.receipt');

Route::get(
    '/payments/zarinpal/callback',
    [ZarinpalPaymentController::class, 'callback']
)->middleware('throttle:30,1')
    ->name('payments.zarinpal.callback');

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
        Route::resource('ideas', IdeaController::class);

        Route::get('/ideas/{idea}/settings', [
            IdeaSettingsController::class,
            'edit',
        ])->name('ideas.settings');

        Route::put('/ideas/{idea}/settings', [
            IdeaSettingsController::class,
            'update',
        ])->name('ideas.settings.update');

        Route::get('/ideas/{idea}/refund-policy', [
            RefundPolicyController::class,
            'index',
        ])->name('ideas.refund-policy');

        Route::post('/ideas/{idea}/refund-policy', [
            RefundPolicyController::class,
            'store',
        ])->name('ideas.refund-policy.store');

        Route::patch('/ideas/{idea}/status', [
            IdeaController::class,
            'changeStatus',
        ])->name('ideas.status');

        Route::post('/ideas/{idea}/archive', [
            IdeaController::class,
            'archive',
        ])->name('ideas.archive');

        Route::post('/ideas/{idea}/restore', [
            IdeaController::class,
            'restore',
        ])->name('ideas.restore');

        Route::get('/ideas/{idea}/landing-pages/create', [
            LandingPageController::class,
            'create',
        ])->name('landing-pages.create');

        Route::post('/ideas/{idea}/landing-pages', [
            LandingPageController::class,
            'store',
        ])->name('landing-pages.store');

        Route::get('/landing-pages/{landingPage}/edit', [
            LandingPageController::class,
            'edit',
        ])->name('landing-pages.edit');

        Route::put('/landing-pages/{landingPage}', [
            LandingPageController::class,
            'update',
        ])->name('landing-pages.update');

        Route::patch('/landing-pages/{landingPage}/toggle', [
            LandingPageController::class,
            'toggle',
        ])->name('landing-pages.toggle');
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

    Route::middleware('can:users.manage')->group(function () {
        Route::resource('/admin/users', UserController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->names('admin.users');
    });

    Route::middleware('can:preorders.manage')->group(function () {
        Route::get(
            '/admin/preorders',
            [PreorderManagementController::class, 'index']
        )->name('admin.preorders.index');

        Route::get(
            '/admin/preorders/{preorder}',
            [PreorderManagementController::class, 'show']
        )->name('admin.preorders.show');

        Route::patch(
            '/admin/preorders/{preorder}',
            [PreorderManagementController::class, 'update']
        )->name('admin.preorders.update');

        Route::post(
            '/admin/payments/{payment}/verify',
            [PreorderManagementController::class, 'verifyPayment']
        )->middleware('throttle:10,1')
            ->name('admin.payments.verify');
    });

    Route::middleware('can:finance.manage')->group(function () {
        Route::get(
            '/admin/payments',
            [PaymentReportController::class, 'index']
        )->name('admin.payments.index');
    });
});
