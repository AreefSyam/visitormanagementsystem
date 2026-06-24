<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('visitors', VisitorController::class);

Route::resource('hosts', HostController::class)->except(['show']);

Route::resource('visits', VisitController::class)->only(['index', 'create', 'store', 'show']);
Route::post('visits/{visit}/checkout', [VisitController::class, 'checkout'])->name('visits.checkout');
Route::post('visits/{visit}/cancel', [VisitController::class, 'cancel'])->name('visits.cancel');
