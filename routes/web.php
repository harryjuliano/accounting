<?php

use App\Http\Controllers\Apps\CompanyController;
use App\Http\Controllers\Apps\ChartOfAccountController;
use App\Http\Controllers\Apps\DashboardController;
use App\Http\Controllers\Apps\DimensionController;
use App\Http\Controllers\Apps\FiscalPeriodController;
use App\Http\Controllers\Apps\PermissionController;
use App\Http\Controllers\Apps\RoleController;
use App\Http\Controllers\Apps\TaxCodeController;
use App\Http\Controllers\Apps\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::group(['prefix' => 'apps', 'as' => 'apps.' , 'middleware' => ['auth']], function(){
    // dashboard route
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // permissions route
    Route::get('/permissions', PermissionController::class)->name('permissions.index');
    // roles route
    Route::resource('/roles', RoleController::class)->except(['create', 'edit', 'show']);
    // users route
    Route::resource('/users', UserController::class)->except('show');
    // companies route
    Route::resource('/companies', CompanyController::class)->except(['create', 'edit', 'show']);
    // fiscal periods route
    Route::resource('/fiscal-periods', FiscalPeriodController::class)->except(['create', 'edit', 'show'])->parameters(['fiscal-periods' => 'fiscal_period']);
    // chart of accounts route
    Route::resource('/chart-of-accounts', ChartOfAccountController::class)->except(['create', 'edit', 'show'])->parameters(['chart-of-accounts' => 'chart_of_account']);
    // dimensions route
    Route::resource('/dimensions', DimensionController::class)->except(['create', 'edit', 'show']);
    // tax codes route
    Route::resource('/tax-codes', TaxCodeController::class)->except(['create', 'edit', 'show'])->parameters(['tax-codes' => 'tax_code']);
});

require __DIR__.'/auth.php';
