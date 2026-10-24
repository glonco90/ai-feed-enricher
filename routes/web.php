<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedController::class, 'index'])->name('feeds.index');
Route::post('/feeds', [FeedController::class, 'store'])->name('feeds.store');
Route::get('/feeds/{feed}', [FeedController::class, 'show'])->name('feeds.show');
Route::post('/feeds/{feed}/process', [FeedController::class, 'process'])->name('feeds.process');
Route::post('/feeds/{feed}/approve-all', [FeedController::class, 'approveAll'])->name('feeds.approve-all');
Route::delete('/feeds/{feed}', [FeedController::class, 'destroy'])->name('feeds.destroy');

Route::get('/feeds/{feed}/export.csv', [ExportController::class, 'csv'])->name('feeds.export.csv');
Route::post('/feeds/{feed}/export/woocommerce', [ExportController::class, 'push'])->name('feeds.export.woo');

Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
Route::post('/products/{product}/approve', [ProductController::class, 'approve'])->name('products.approve');
Route::post('/products/{product}/reject', [ProductController::class, 'reject'])->name('products.reject');
Route::post('/products/{product}/retry', [ProductController::class, 'retry'])->name('products.retry');
