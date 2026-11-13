<?php

namespace App\Console\Commands;

use App\Services\Backup;
use App\Support\AuditLogger;
use Illuminate\Console\Command;

class SiesRestore extends Command
{
    protected $signature = 'sies:restore {file : The backup .zip} {--force : Restore without asking}';

    protected $description = 'Replace all data and uploaded files with a backup (run "php artisan migrate" first)';

    public function handle(): int
    {
        $file = $this->argument('file');

        try {
            $manifest = Backup::manifest($file);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Backup from {$manifest['created_at']}: " . count($manifest['tables']) . ' tables, ' . array_sum($manifest['tables']) . " rows, {$manifest['files']} files.");
        $this->warn('Every record now in the system will be REPLACED by this backup.');

        if (! $this->option('force') && ! $this->confirm('Restore it?')) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $result = Backup::restore($file);
        AuditLogger::log('system.restore', null, 'Restored backup ' . basename($file));
        $this->info("Restored {$result['rows']} rows in {$result['tables']} tables and {$result['files']} files.");
        $this->line('Everyone must sign in again.');

        return self::SUCCESS;
    }
}
