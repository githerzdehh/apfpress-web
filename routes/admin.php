<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\CommerceSettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'verified', 'role:owner,editor,fulfillment'])->group(function (): void {
    Route::get('/{path?}', AdminController::class)->where('path', '^(?!api).*$')->name('admin.index');

    Route::prefix('api')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('admin.dashboard');
        Route::middleware('role:owner,editor')->group(function (): void {
            Route::get('/catalog', [AdminCatalogController::class, 'index']);
            Route::post('/catalog', [AdminCatalogController::class, 'store']);
            Route::get('/catalog/{catalogItem}', [AdminCatalogController::class, 'show']);
            Route::put('/catalog/{catalogItem}', [AdminCatalogController::class, 'update']);
            Route::post('/imports/preview', [ImportController::class, 'preview']);
            Route::post('/imports/{batch}/commit', [ImportController::class, 'commit']);
            Route::post('/catalog/{catalogItem}/cover', [MediaController::class, 'cover']);
            Route::post('/offerings/{offering}/digital-asset', [MediaController::class, 'digital']);
        });
        Route::middleware('role:owner,fulfillment')->group(function (): void {
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::patch('/orders/{order}', [OrderController::class, 'update']);
        });
        Route::middleware('role:owner')->group(function (): void {
            Route::get('/integrations', [IntegrationController::class, 'index']);
            Route::put('/integrations/{provider}', [IntegrationController::class, 'update']);
            Route::get('/commerce-settings', [CommerceSettingsController::class, 'index']);
            Route::post('/commerce-settings/shipping', [CommerceSettingsController::class, 'shipping']);
            Route::post('/commerce-settings/tax', [CommerceSettingsController::class, 'tax']);
        });
    });
});
