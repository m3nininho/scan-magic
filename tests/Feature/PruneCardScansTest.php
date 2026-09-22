<?php

use App\Enums\CardScanStatus;
use App\Models\CardScan;
use Illuminate\Support\Facades\Storage;

test('removes expired images and only deletes abandoned scan records', function () {
    Storage::fake('local');
    $expiredAt = now()->subDays(8);
    $confirmedScan = CardScan::create(['original_image_path' => 'scans/confirmed.jpg', 'status' => CardScanStatus::Confirmed]);
    $abandonedScan = CardScan::create(['original_image_path' => 'scans/abandoned.jpg', 'status' => CardScanStatus::Failed]);
    $recentScan = CardScan::create(['original_image_path' => 'scans/recent.jpg', 'status' => CardScanStatus::Failed]);
    $confirmedScan->forceFill(['created_at' => $expiredAt, 'updated_at' => $expiredAt])->save();
    $abandonedScan->forceFill(['created_at' => $expiredAt, 'updated_at' => $expiredAt])->save();
    Storage::disk('local')->put('scans/confirmed.jpg', 'image');
    Storage::disk('local')->put('scans/abandoned.jpg', 'image');
    Storage::disk('local')->put('scans/recent.jpg', 'image');

    $this->artisan('scans:prune')
        ->expectsOutput('Removed 2 retained images and 1 abandoned scans.')
        ->assertSuccessful();

    $this->assertModelExists($confirmedScan);
    $this->assertModelMissing($abandonedScan);
    $this->assertModelExists($recentScan);
    Storage::disk('local')->assertMissing('scans/confirmed.jpg');
    Storage::disk('local')->assertMissing('scans/abandoned.jpg');
    Storage::disk('local')->assertExists('scans/recent.jpg');
    expect($confirmedScan->fresh()->original_image_path)->toBeNull();
});
