<?php

namespace App\Console\Commands;

use App\Services\RequestWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Empties an environment and gives it a clean start: the structure, the base data and the first
 * admin, with no demo data (spec 23). Production asks for a typed confirmation.
 */
class ResetApp extends Command
{
    public const PRODUCTION_PHRASE = 'reset production';

    /** Folders of uploaded files on the receipts disk, which is foldered per environment. */
    private const UPLOAD_FOLDERS = ['payment-proofs', 'identity-documents', 'request-documents'];

    protected $signature = 'app:reset';

    protected $description = 'Delete every account, request and uploaded file, then reload the base data and the first admin';

    public function handle(): int
    {
        // The first admin is recreated after the wipe, so check its settings before deleting anything.
        if (blank(config('uphsl.initial_admin.email')) || blank(config('uphsl.initial_admin.password'))) {
            $this->error('Set INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD in .env first. Nothing was changed.');

            return self::FAILURE;
        }

        if ($this->laravel->environment('production') && ! $this->confirmedForProduction()) {
            $this->error('Not confirmed. Nothing was changed.');

            return self::FAILURE;
        }

        foreach (self::UPLOAD_FOLDERS as $folder) {
            Storage::disk(RequestWorkflow::proofDisk())->deleteDirectory($folder);
        }

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $this->info('Reset done: base data and the first admin are loaded.');

        return self::SUCCESS;
    }

    private function confirmedForProduction(): bool
    {
        $this->warn('This deletes EVERY account, request and uploaded file in PRODUCTION. It cannot be undone.');

        return $this->ask('Type "'.self::PRODUCTION_PHRASE.'" to continue') === self::PRODUCTION_PHRASE;
    }
}
