<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\IdeaController;
use App\Http\Controllers\LandingPageController;

// home page
Route::get('/', function () {
    return redirect()->route('ideas.index');
});

// get
Route::get(
    '/ideas/{idea}/landing-pages/create',
    [LandingPageController::class, 'create']
)->name('landing-pages.create');

// post
Route::post(
    '/ideas/{idea}/landing-pages',
    [LandingPageController::class, 'store']
)->name('landing-pages.store');

// resource
Route::resource('ideas', IdeaController::class)
    ->only([
        'index',
        'create',
        'store',
        'show',
    ]);
