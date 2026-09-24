<?php

use App\Http\Controllers\CollectionCardController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PendingScanController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/diagnostico-hash', function () {
    $rounds = config('hashing.bcrypt.rounds');

    $resultado = [
        'driver' => config('hashing.driver'),
        'bcrypt_rounds' => $rounds,
        'bcrypt_rounds_tipo' => get_debug_type($rounds),
    ];

    foreach ([
        'php_com_rounds_12' => 12,
        'php_com_rounds_configurados' => $rounds,
    ] as $teste => $custo) {
        try {
            $hash = password_hash(
                'SenhaFicticiaParaDiagnostico!',
                PASSWORD_BCRYPT,
                ['cost' => $custo]
            );

            $resultado[$teste] = $hash !== false;
        } catch (\Throwable $e) {
            $resultado[$teste] = false;
            $resultado[$teste . '_erro'] = $e->getMessage();
        }
    }

    return response()->json($resultado);
});

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
