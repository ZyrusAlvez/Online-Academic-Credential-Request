<?php

namespace App\Console\Commands;

use App\Services\RequestWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes, reads back and deletes a small file on the receipts disk to check its settings.
 */
class TestStorage extends Command
{
    protected $signature = 'app:test-storage';

    protected $description = 'Check that payment receipts can be stored on the configured disk (local or R2)';

    public function handle(): int
    {
        $diskName = RequestWorkflow::proofDisk();
        $this->line("Environment: {$this->laravel->environment()} | receipts disk: {$diskName}"
            .($diskName === 'r2' ? ' | bucket: '.config('filesystems.disks.r2.bucket').' | folder: '.config('filesystems.disks.r2.root') : ''));

        $path = 'healthcheck/'.Str::uuid().'.txt';
        $content = 'UPHSL storage check '.now()->toIso8601String();

        try {
            $disk = Storage::disk($diskName);
            $disk->put($path, $content);
            $readBack = $disk->get($path);
            $disk->delete($path);
        } catch (Throwable $e) {
            $this->error('Storage check failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($readBack !== $content) {
            $this->error('The file was written but read back differently.');

            return self::FAILURE;
        }

        $this->info('Write, read and delete all worked.');

        return self::SUCCESS;
    }
}
