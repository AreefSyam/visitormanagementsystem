<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitorController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

// Simple auto-login route for testing (REMOVE IN PRODUCTION!)
Route::get('/auto-login', function () {
    $user = User::where('email', 'admin@example.com')->first();
    if ($user) {
        Auth::login($user);
        return redirect()->route('dashboard')->with('success', 'Logged in as ' . $user->name);
    }
    return 'No admin user found. Run: php artisan db:seed --class=AdminUserSeeder';
})->name('auto.login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('visitors', VisitorController::class);

    Route::resource('hosts', HostController::class)->except(['show']);

    Route::resource('visits', VisitController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('visits/{visit}/checkout', [VisitController::class, 'checkout'])->name('visits.checkout');
    Route::post('visits/{visit}/cancel', [VisitController::class, 'cancel'])->name('visits.cancel');

    // Analytics routes (TEMPORARILY without admin middleware - for testing)
    // TODO: Restore admin middleware after testing
    Route::get('/dashboard/analytics', [AnalyticsController::class, 'index'])
        ->name('analytics.index');

    Route::post('/dashboard/analytics/export-pdf', [AnalyticsController::class, 'exportPdf'])
        ->name('analytics.export')
        ->middleware('throttle:10,1');

    // Logout route
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');
});
