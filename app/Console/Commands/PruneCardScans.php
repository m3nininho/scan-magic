<?php

namespace App\Console\Commands;

use App\Enums\CardScanStatus;
use App\Models\CardScan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('scans:prune {--days= : Remove abandoned scans older than this many days}')]
#[Description('Remove retained card images and abandoned scan records')]
class PruneCardScans extends Command
{
    public function handle(): int
    {
        $retentionDays = max(1, (int) ($this->option('days') ?? config('scanner.retention_days', 7)));
        $createdBefore = now()->subDays($retentionDays);
        $removedImages = 0;

        CardScan::query()
            ->whereNotNull('original_image_path')
            ->where('created_at', '<=', $createdBefore)
            ->lazyById()
            ->each(function (CardScan $scan) use (&$removedImages): void {
                if (Storage::disk('local')->delete($scan->original_image_path)) {
                    $scan->update(['original_image_path' => null]);
                    $removedImages++;
                }
            });

        $removedScans = CardScan::query()
            ->where('status', '!=', CardScanStatus::Confirmed)
            ->whereNull('original_image_path')
            ->where('created_at', '<=', $createdBefore)
            ->delete();

        $this->info("Removed {$removedImages} retained images and {$removedScans} abandoned scans.");

        return self::SUCCESS;
    }
}
