<?php

use App\Http\Controllers\Api\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/catalog', [CatalogController::class, 'index'])->name('api.catalog.index');
Route::get('/v1/catalog/{catalogItem:slug}', [CatalogController::class, 'show'])->name('api.catalog.show');
