<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * Backups of the whole system in one .zip: every table's rows (JSON, one file per table) and every
 * uploaded file (student photos, excuse letters, school logo, staff photos).
 *
 * Restoring puts the rows back into a database that is already migrated to the current version,
 * keeping only columns that still exist, so an older backup restores into a newer system.
 */
class Backup
{
    /** Throw-away tables: not worth keeping, and wrong to restore. */
    private const SKIP = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations'];

    private const CHUNK = 500;

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    /** @return string[] table names in the current database */
    public static function tables(): array
    {
        $names = collect(Schema::getTables())->pluck('name')->all();

        return array_values(array_diff($names, self::SKIP));
    }

    /** Make a backup; returns the path of the .zip. */
    public static function create(?string $directory = null): string
    {
        $directory ??= self::directory();
        File::ensureDirectoryExists($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'sies-backup-' . now()->format('Ymd-His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot write {$path}.");
        }

        $counts = [];
        foreach (self::tables() as $table) {
            $rows = [];
            $query = DB::table($table);
            $key = Schema::hasColumn($table, 'id') ? 'id' : null;
            $key ? $query->orderBy($key) : $query->orderBy(Schema::getColumnListing($table)[0]);
            foreach ($query->cursor() as $row) {
                $rows[] = (array) $row;
            }
            $zip->addFromString("tables/{$table}.json", json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            $counts[$table] = count($rows);
        }

        // Uploaded files live on the private "local" disk.
        $files = 0;
        $root = storage_path('app/private');
        if (is_dir($root)) {
            foreach (File::allFiles($root) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                if (! str_starts_with($relative, 'backups/')) {
                    $zip->addFile($file->getRealPath(), 'files/' . $relative);
                    $files++;
                }
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'database' => DB::connection()->getDriverName(),
            'tables' => $counts,
            'files' => $files,
        ], JSON_PRETTY_PRINT));
        $zip->close();

        return $path;
    }

    /** Read a backup's manifest without restoring it. */
    public static function manifest(string $path): array
    {
        $zip = self::open($path);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        if (! is_array($manifest) || ! isset($manifest['tables'])) {
            throw new RuntimeException('This is not an SIES backup (no manifest).');
        }

        return $manifest;
    }

    /**
     * Replace every table's rows and the uploaded files with the backup's.
     *
     * @return array{tables: int, rows: int, files: int}
     */
    public static function restore(string $path): array
    {
        $manifest = self::manifest($path);
        $zip = self::open($path);
        $current = self::tables();
        $restored = ['tables' => 0, 'rows' => 0, 'files' => 0];

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($zip, $manifest, $current, &$restored) {
                foreach ($current as $table) {
                    DB::table($table)->delete();
                }
                foreach (array_keys($manifest['tables']) as $table) {
                    if (! in_array($table, $current, true)) {
                        continue;   // a table this version no longer has
                    }
                    $rows = json_decode((string) $zip->getFromName("tables/{$table}.json"), true) ?? [];
                    $columns = array_flip(Schema::getColumnListing($table));
                    foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                        DB::table($table)->insert(array_map(fn ($row) => array_intersect_key($row, $columns), $chunk));
                    }
                    $restored['tables']++;
                    $restored['rows'] += count($rows);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Files: put each one back under storage/app/private.
        $root = storage_path('app/private');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! str_starts_with($name, 'files/') || str_ends_with($name, '/') || str_contains($name, '..')) {
                continue;
            }
            $target = $root . '/' . substr($name, strlen('files/'));
            File::ensureDirectoryExists(dirname($target));
            file_put_contents($target, $zip->getFromIndex($i));
            $restored['files']++;
        }
        $zip->close();

        return $restored;
    }

    /** Keep only the newest $keep backups in the folder. */
    public static function prune(int $keep, ?string $directory = null): int
    {
        $old = collect(File::glob(($directory ?? self::directory()) . '/sies-backup-*.zip'))
            ->sortDesc()->slice($keep);
        $old->each(fn ($file) => File::delete($file));

        return $old->count();
    }

    /** The newest backup and when it was made, for the readiness check. */
    public static function latest(): ?array
    {
        $file = collect(File::glob(self::directory() . '/sies-backup-*.zip'))->sortDesc()->first();

        return $file ? ['path' => $file, 'at' => Carbon::createFromTimestamp(filemtime($file))] : null;
    }

    private static function open(string $path): ZipArchive
    {
        $zip = new ZipArchive();
        if (! is_file($path) || $zip->open($path) !== true) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        return $zip;
    }
}
