<?php

namespace App\Console\Commands;

use App\Services\Backup;
use App\Support\AuditLogger;
use Illuminate\Console\Command;

class SiesBackup extends Command
{
    protected $signature = 'sies:backup {--keep=14 : How many backups to keep (older ones are deleted)} {--path= : Folder to write to (default storage/app/backups)}';

    protected $description = 'Back up the database and every uploaded file into one dated .zip';

    public function handle(): int
    {
        $path = Backup::create($this->option('path') ?: null);
        $manifest = Backup::manifest($path);
        $removed = Backup::prune(max(1, (int) $this->option('keep')), $this->option('path') ?: null);

        AuditLogger::log('system.backup', null, 'Backup made: ' . basename($path));

        $this->info('Backup made: ' . $path);
        $this->line(sprintf('  %d tables, %d rows, %d files. %s',
            count($manifest['tables']), array_sum($manifest['tables']), $manifest['files'],
            $removed ? "Removed {$removed} older backup(s)." : ''));
        $this->line('  Copy it somewhere off this computer too (a USB drive or the school\'s cloud storage).');

        return self::SUCCESS;
    }
}
