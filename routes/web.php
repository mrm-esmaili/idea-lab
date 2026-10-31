<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AuthController as AccountAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\IdeaSettingsController;
use App\Http\Controllers\RefundPolicyController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PublicLandingPageController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\Admin\LeadManagementController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CampaignComparisonController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\ExperimentController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ContactGroupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\LandingPageEventController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FinanceReportController;
use App\Http\Controllers\Admin\PaymentReportController;
use App\Http\Controllers\Admin\RefundRequestController;
use App\Http\Controllers\Admin\PreorderManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SmsBroadcastController;
use App\Http\Controllers\Admin\SmsMessageController;
use App\Http\Controllers\Admin\SmsTemplateController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ZarinpalPaymentController;

// نصب اولیه بدون SSH؛ بدون نشست/کوکی/CSRF چون پیش از migration جدول نشست وجود ندارد
Route::withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
])->group(function () {
    Route::get('/install', [InstallController::class, 'show'])->name('install');
    Route::post('/install', [InstallController::class, 'store'])->name('install.store');
});

Route::get('/login', [
    AuthController::class,
    'showLogin',
])->middleware('guest')->name('login');

Route::post('/login', [
    AuthController::class,
    'login',
])->middleware(['guest', 'throttle:5,1,login.submit'])
    ->name('login.submit');

Route::post('/logout', [
    AuthController::class,
    'logout',
])->middleware('auth:web')->name('logout');

// صفحات عمومی
Route::get('/l/{slug}', [
    PublicLandingPageController::class,
    'show',
])->name('landing-pages.show');

Route::post('/l/{slug}/leads', [
    LeadController::class,
    'store',
])->middleware(['throttle:5,1,leads.store', 'public.form'])->name('leads.store');

Route::post(
    '/l/{slug}/events',
    [LandingPageEventController::class, 'store']
)
    ->middleware('throttle:30,1,landing-pages.events.store')
    ->name('landing-pages.events.store');

Route::post(
    '/l/{slug}/preorders',
    [PreorderController::class, 'store']
)
    ->middleware(['throttle:5,1,preorders.store', 'public.form'])
    ->name('preorders.store');

Route::get(
    '/preorder/{token}/checkout',
    [ZarinpalPaymentController::class, 'checkout']
)->middleware('throttle:30,1,preorders.checkout')
    ->name('preorders.checkout');

Route::post(
    '/preorder/{token}/pay',
    [ZarinpalPaymentController::class, 'pay']
)
    ->middleware('throttle:5,1,preorders.pay')
    ->name('preorders.pay');

Route::get(
    '/preorder/{token}/receipt',
    [ZarinpalPaymentController::class, 'receipt']
)->middleware('throttle:30,1,preorders.receipt')
    ->name('preorders.receipt');

Route::get(
    '/payments/zarinpal/callback',
    [ZarinpalPaymentController::class, 'callback']
)->middleware('throttle:30,1,payments.zarinpal.callback')
    ->name('payments.zarinpal.callback');

Route::get(
    '/payments/result',
    [ZarinpalPaymentController::class, 'result']
)->name('payments.result');

// حساب مخاطب (جدا از پنل مدیریت؛ guard: contact)
Route::prefix('account')->name('account.')->group(function () {
    Route::middleware('guest:contact')->group(function () {
        Route::get('/login', [AccountAuthController::class, 'showLogin'])->name('login');

        Route::post('/login', [AccountAuthController::class, 'sendCode'])
            ->middleware('throttle:10,1,login.send')
            ->name('login.send');

        Route::get('/verify', [AccountAuthController::class, 'showVerify'])->name('verify');

        Route::post('/verify', [AccountAuthController::class, 'verify'])
            ->middleware('throttle:10,1,verify.submit')
            ->name('verify.submit');
    });

    Route::middleware(['auth:contact', 'contact.fresh'])->group(function () {
        Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile');
        Route::put('/consent', [AccountController::class, 'updateConsent'])->name('consent');
        Route::post('/preorders/{preorder}/refund', [AccountController::class, 'requestRefund'])
            ->middleware('throttle:5,1,refund')
            ->whereNumber('preorder')
            ->name('refund');
        Route::post('/logout', [AccountAuthController::class, 'logout'])->name('logout');
    });
});

// پنل مدیریت
Route::middleware(['auth:web', 'admin'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });

    Route::get('/admin', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')
        ->name('admin.dashboard');

    Route::get('/admin/search', SearchController::class)
        ->middleware(['can:dashboard.view', 'throttle:60,1,admin.search'])
        ->name('admin.search');

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

        Route::get('/admin/campaigns/compare', [CampaignComparisonController::class, 'index'])
            ->name('admin.campaigns.compare');

        Route::get('/admin/experiments', [ExperimentController::class, 'index'])->name('admin.experiments.index');
        Route::get('/admin/experiments/create', [ExperimentController::class, 'create'])->name('admin.experiments.create');
        Route::post('/admin/experiments', [ExperimentController::class, 'store'])->name('admin.experiments.store');
        Route::get('/admin/experiments/{experiment}', [ExperimentController::class, 'show'])->whereNumber('experiment')->name('admin.experiments.show');
        Route::get('/admin/experiments/{experiment}/edit', [ExperimentController::class, 'edit'])->whereNumber('experiment')->name('admin.experiments.edit');
        Route::put('/admin/experiments/{experiment}', [ExperimentController::class, 'update'])->whereNumber('experiment')->name('admin.experiments.update');
        Route::post('/admin/experiments/{experiment}/cancel', [ExperimentController::class, 'cancel'])->whereNumber('experiment')->name('admin.experiments.cancel');
        Route::post('/admin/experiments/{experiment}/decisions', [ExperimentController::class, 'decide'])->whereNumber('experiment')->name('admin.experiments.decide');
        Route::post('/admin/experiment-decisions/{decision}/review', [ExperimentController::class, 'review'])->whereNumber('decision')->name('admin.experiments.review');

        Route::resource('/admin/campaigns', CampaignController::class)
            ->parameters(['campaigns' => 'campaign'])
            ->names('admin.campaigns');

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

        Route::get('/landing-pages/{landingPage}/preview', [
            LandingPageController::class,
            'preview',
        ])->name('landing-pages.preview');
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

    Route::middleware('can:contacts.manage')->group(function () {
        Route::get('/admin/contacts', [ContactController::class, 'index'])->name('admin.contacts.index');

        Route::get('/admin/contacts/export', [ContactController::class, 'export'])
            ->middleware(['can:contacts.export', 'throttle:10,1,admin.contacts.export'])
            ->name('admin.contacts.export');

        Route::get('/admin/contacts/{contact}', [ContactController::class, 'show'])->name('admin.contacts.show');
        Route::post('/admin/contacts/{contact}/interests', [ContactController::class, 'addInterest'])->name('admin.contacts.interests.store');
        Route::delete('/admin/contacts/{contact}/interests/{idea}', [ContactController::class, 'removeInterest'])->name('admin.contacts.interests.destroy');
        Route::post('/admin/contacts/{contact}/groups', [ContactController::class, 'addToGroup'])->name('admin.contacts.groups.store');
        Route::delete('/admin/contacts/{contact}/groups/{group}', [ContactController::class, 'removeFromGroup'])->name('admin.contacts.groups.destroy');

        Route::resource('/admin/groups', ContactGroupController::class)
            ->parameters(['groups' => 'group'])
            ->names('admin.groups');

        Route::get('/admin/groups/{group}/export', [ContactGroupController::class, 'export'])
            ->middleware(['can:contacts.export', 'throttle:10,1,admin.groups.export'])
            ->name('admin.groups.export');
    });

    Route::middleware('can:reports.view')->group(function () {
        Route::get('/admin/reports/funnel', [ReportController::class, 'funnel'])->name('admin.reports.funnel');
        Route::get('/admin/reports/ideas', [ReportController::class, 'ideas'])->name('admin.reports.ideas');
        Route::get('/admin/reports/sources', [ReportController::class, 'sources'])->name('admin.reports.sources');
        Route::get('/admin/reports/contacts', [ReportController::class, 'contacts'])->name('admin.reports.contacts');
        Route::get('/admin/reports/sms', [ReportController::class, 'sms'])->name('admin.reports.sms');
    });

    Route::middleware('can:sms.broadcast')->group(function () {
        Route::get('/admin/sms/broadcasts', [SmsBroadcastController::class, 'index'])->name('admin.sms.broadcasts.index');
        Route::get('/admin/sms/broadcasts/create', [SmsBroadcastController::class, 'create'])->name('admin.sms.broadcasts.create');
        Route::post('/admin/sms/broadcasts', [SmsBroadcastController::class, 'store'])->name('admin.sms.broadcasts.store');
        Route::get('/admin/sms/broadcasts/{broadcast}', [SmsBroadcastController::class, 'show'])->whereNumber('broadcast')->name('admin.sms.broadcasts.show');
        Route::post('/admin/sms/broadcasts/{broadcast}/confirm', [SmsBroadcastController::class, 'confirm'])->whereNumber('broadcast')->middleware('throttle:10,1,admin.sms.broadcasts.confirm')->name('admin.sms.broadcasts.confirm');
        Route::post('/admin/sms/broadcasts/{broadcast}/cancel', [SmsBroadcastController::class, 'cancel'])->whereNumber('broadcast')->name('admin.sms.broadcasts.cancel');
        Route::post('/admin/sms/broadcasts/{broadcast}/dispatch', [SmsBroadcastController::class, 'dispatchBatch'])->whereNumber('broadcast')->middleware('throttle:30,1,admin.sms.broadcasts.dispatch')->name('admin.sms.broadcasts.dispatch');
        Route::delete('/admin/sms/broadcasts/{broadcast}', [SmsBroadcastController::class, 'destroy'])->whereNumber('broadcast')->name('admin.sms.broadcasts.destroy');
    });

    Route::middleware('can:sms.send')->group(function () {
        Route::get('/admin/sms/messages', [SmsMessageController::class, 'index'])->name('admin.sms.messages.index');
        Route::post('/admin/sms/messages/{message}/retry', [SmsMessageController::class, 'retry'])->whereNumber('message')->name('admin.sms.messages.retry');
        Route::post('/admin/contacts/{contact}/sms', [SmsMessageController::class, 'sendToContact'])
            ->whereNumber('contact')
            ->middleware('throttle:10,1,admin.contacts.sms')
            ->name('admin.contacts.sms');
    });

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/admin/system', SystemStatusController::class)->name('admin.system');
        Route::post('/admin/system/migrate', [SystemStatusController::class, 'migrate'])->middleware('throttle:3,1,admin.system.migrate')->name('admin.system.migrate');
        Route::post('/admin/system/optimize', [SystemStatusController::class, 'optimize'])->middleware('throttle:5,1,admin.system.optimize')->name('admin.system.optimize');

        Route::get('/admin/settings/site', [SiteSettingsController::class, 'edit'])->name('admin.settings.site');
        Route::put('/admin/settings/site', [SiteSettingsController::class, 'update'])->name('admin.settings.site.update');

        // به‌روزرسانی از پنل؛ در حالت تعمیر هم در دسترس (bootstrap/app.php). نام مسیرها ثابت بماند.
        Route::get('/admin/updates', [UpdateController::class, 'index'])->name('admin.updates.index');
        Route::post('/admin/updates/packages', [UpdateController::class, 'upload'])->middleware('throttle:10,10,admin.updates.upload')->name('admin.updates.upload');
        Route::get('/admin/updates/packages/{package}', [UpdateController::class, 'review'])->where('package', '[0-9A-Za-z][0-9A-Za-z._-]*\.zip')->name('admin.updates.review');
        Route::post('/admin/updates/packages/{package}/apply', [UpdateController::class, 'apply'])->where('package', '[0-9A-Za-z][0-9A-Za-z._-]*\.zip')->middleware('throttle:5,10,admin.updates.apply')->name('admin.updates.apply');
        Route::delete('/admin/updates/packages/{package}', [UpdateController::class, 'destroyPackage'])->where('package', '[0-9A-Za-z][0-9A-Za-z._-]*\.zip')->name('admin.updates.packages.destroy');
        Route::get('/admin/updates/{update}', [UpdateController::class, 'show'])->whereNumber('update')->name('admin.updates.show');
        Route::post('/admin/updates/{update}/finish', [UpdateController::class, 'finish'])->whereNumber('update')->middleware('throttle:10,10,admin.updates.finish')->name('admin.updates.finish');
        Route::post('/admin/updates/{update}/rollback', [UpdateController::class, 'rollback'])->whereNumber('update')->middleware('throttle:5,10,admin.updates.rollback')->name('admin.updates.rollback');

        Route::get('/admin/backups', [BackupController::class, 'index'])->name('admin.backups.index');
        Route::post('/admin/backups', [BackupController::class, 'store'])->middleware('throttle:3,10,admin.backups.store')->name('admin.backups.store');
        Route::get('/admin/backups/{name}', [BackupController::class, 'download'])->where('name', 'idealab-\d{8}-\d{6}\.sql\.gz')->name('admin.backups.download');

        Route::get('/admin/sms', [SmsTemplateController::class, 'index'])
            ->name('admin.sms.index');

        Route::get('/admin/sms/templates/{template}/edit', [SmsTemplateController::class, 'edit'])
            ->name('admin.sms.templates.edit');

        Route::put('/admin/sms/templates/{template}', [SmsTemplateController::class, 'update'])
            ->name('admin.sms.templates.update');
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
        )->middleware('throttle:10,1,admin.payments.verify')
            ->name('admin.payments.verify');
    });

    Route::middleware('can:finance.manage')->group(function () {
        Route::get(
            '/admin/payments',
            [PaymentReportController::class, 'index']
        )->name('admin.payments.index');

        Route::get('/admin/refunds', [RefundRequestController::class, 'index'])->name('admin.refunds.index');
        Route::get('/admin/refunds/{refundRequest}', [RefundRequestController::class, 'show'])->name('admin.refunds.show');
        Route::post('/admin/preorders/{preorder}/refunds', [RefundRequestController::class, 'store'])->name('admin.refunds.store');
        Route::post('/admin/refunds/{refundRequest}/approve', [RefundRequestController::class, 'approve'])->name('admin.refunds.approve');
        Route::post('/admin/refunds/{refundRequest}/reject', [RefundRequestController::class, 'reject'])->name('admin.refunds.reject');
        Route::post('/admin/refunds/{refundRequest}/execute', [RefundRequestController::class, 'execute'])->name('admin.refunds.execute');

        Route::resource('/admin/expenses', ExpenseController::class)
            ->except(['show'])
            ->parameters(['expenses' => 'expense'])
            ->names('admin.expenses');

        Route::get('/admin/finance', [FinanceReportController::class, 'index'])->name('admin.finance.index');
    });
});
