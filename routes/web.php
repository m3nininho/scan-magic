<?php

use App\Http\Controllers\CollectionCardController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PendingScanController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('scanner', [ScannerController::class, 'index'])->name('scanner');
    Route::get('collection', [CollectionController::class, 'index'])->name('collection');
    Route::get('collection/cards/{card}', [CollectionCardController::class, 'show'])->name('collection.cards.show');
    Route::patch('collection/cards/{card}', [CollectionCardController::class, 'update'])->name('collection.cards.update');
    Route::get('pending-scans', [PendingScanController::class, 'index'])->name('pending-scans.index');
    Route::get('pending-scans/{scan}/image', [PendingScanController::class, 'image'])->name('pending-scans.image');
    Route::post('pending-scans/{scan}/search', [PendingScanController::class, 'search'])->middleware('throttle:scanner')->name('pending-scans.search');
    Route::delete('pending-scans/{scan}', [PendingScanController::class, 'destroy'])->name('pending-scans.destroy');
    Route::post('scanner/scan', [ScannerController::class, 'scan'])->middleware('throttle:scanner')->name('scanner.scan');
    Route::post('scanner/search', [ScannerController::class, 'search'])->middleware('throttle:scanner')->name('scanner.search');
    Route::post('scanner/{scan}/confirm', [ScannerController::class, 'confirm'])->middleware('throttle:scanner-confirmation')->name('scanner.confirm');
});

require __DIR__.'/settings.php';
