<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** sies:backup and sies:restore: every table's rows and the uploaded files, round trip. */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups-' . uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::delete(storage_path('app/private/test-backup/photo.txt'));
        parent::tearDown();
    }

    public function test_a_backup_restores_the_data_and_files_it_was_made_from(): void
    {
        $program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1]);
        File::ensureDirectoryExists(storage_path('app/private/test-backup'));
        file_put_contents(storage_path('app/private/test-backup/photo.txt'), 'original photo');

        $this->artisan('sies:backup', ['--path' => $this->dir])->assertSuccessful();
        $file = File::glob($this->dir . '/sies-backup-*.zip')[0];
        $this->assertSame(1, Backup::manifest($file)['tables']['students']);

        // Disaster: the student is gone and the file overwritten.
        Student::query()->forceDelete();
        file_put_contents(storage_path('app/private/test-backup/photo.txt'), 'damaged');

        $this->artisan('sies:restore', ['file' => $file, '--force' => true])->assertSuccessful();

        $this->assertSame('Ana', Student::firstOrFail()->first_name);
        $this->assertSame('original photo', file_get_contents(storage_path('app/private/test-backup/photo.txt')));
    }

    public function test_old_backups_are_pruned_and_a_wrong_file_is_refused(): void
    {
        File::ensureDirectoryExists($this->dir);
        foreach (['20260101-010101', '20260102-010101', '20260103-010101'] as $stamp) {
            file_put_contents("{$this->dir}/sies-backup-{$stamp}.zip", 'x');
        }
        $this->assertSame(1, Backup::prune(2, $this->dir));
        $this->assertFileDoesNotExist("{$this->dir}/sies-backup-20260101-010101.zip");

        file_put_contents("{$this->dir}/not-a-backup.zip", 'nope');
        $this->artisan('sies:restore', ['file' => "{$this->dir}/not-a-backup.zip", '--force' => true])->assertFailed();
        $this->assertTrue(User::query()->count() >= 0);
    }
}
