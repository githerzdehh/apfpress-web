<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::redirect('/shop-print-books', '/books?format=print_book', 301);
Route::redirect('/shop-ebooks', '/books?format=ebook', 301);
Route::get('/product/{slug}', fn (string $slug) => redirect()->route('catalog.show', $slug, 301));
Route::get('/books', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/books/{catalogItem:slug}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/about', fn () => app(ContentController::class)->page('about'))->name('about');
Route::get('/publish-with-us', fn () => app(ContentController::class)->page('publish-with-us'))->name('publish');
Route::post('/publish-with-us', [SubmissionController::class, 'store'])->middleware('throttle:5,60')->name('submissions.store');
Route::view('/contact', 'contact')->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,60')->name('contact.store');
Route::get('/editorial-board', [ContentController::class, 'board'])->name('board');
Route::get('/policies/{slug}', [ContentController::class, 'page'])->whereIn('slug', ['privacy', 'terms', 'refund-policy'])->name('policy');

require __DIR__.'/auth.php';
require __DIR__.'/commerce.php';
require __DIR__.'/admin.php';
