
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PublicLandingPageController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\Admin\LeadManagementController;

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

// پنل مدیریت
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.leads.index');
    });

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

    Route::get('/admin/leads', [
        LeadManagementController::class,
        'index',
    ])->name('admin.leads.index');

    Route::patch('/admin/leads/{lead}', [
        LeadManagementController::class,
        'update',
    ])->name('admin.leads.update');
});
