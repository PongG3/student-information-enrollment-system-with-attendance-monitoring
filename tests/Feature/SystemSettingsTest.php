<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\ReadinessCheck;
use App\Support\SchoolBranding;
use App\Support\SessionTimeout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Ana Admin']);
        $this->admin->forceFill(['role' => 'admin', 'is_active' => true, 'must_change_password' => false])->save();

        foreach ([
            'school_name' => 'School Name', 'attendance_edit_window_days' => '7', 'absentee_threshold_percent' => '20',
            'max_units_per_term' => '24', 'late_counts_as_attended' => '1', 'excused_in_percentage' => 'excluded',
        ] as $name => $value) {
            Setting::put($name, $value);
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'school_name' => 'Rizal College', 'school_address' => '', 'registrar_contact' => '',
            'attendance_edit_window_days' => 7, 'absentee_threshold_percent' => 20, 'max_units_per_term' => 24,
            'excused_in_percentage' => 'excluded', 'late_counts_as_attended' => 1, 'session_lifetime_minutes' => 120,
        ], $overrides);
    }

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->admin)->put('/settings', $this->payload($overrides));
    }

    /**
     * A genuine PNG built byte by byte, without the GD extension
     * (GD can crash PHP on some Windows installs; see ReportExport).
     */
    private function png(string $name = 'logo.png', int $width = 120, int $height = 120, bool $pixels = true)
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        // White rows, no filter. Without $pixels only the header is real: enough for size checks, light on memory.
        $rows = $pixels ? str_repeat("\0" . str_repeat("\xff\xff\xff", $width), $height) : '';

        $bytes = "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress($rows))
            . $chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    // ---------- Page ----------

    public function test_the_page_shows_all_sections_and_the_preview(): void
    {
        $this->actingAs($this->admin)->get('/settings')->assertOk()
            ->assertSee('School')->assertSee('Enrollment')->assertSee('Attendance')->assertSee('Sign-in security')
            ->assertSee('Attendance preview')->assertSee('What this means today')
            ->assertSee('units')->assertSee('days')->assertSee('minutes')
            ->assertSee('data-settings-form', false);
    }

    public function test_a_placeholder_school_name_is_called_out_until_it_is_changed(): void
    {
        $this->actingAs($this->admin)->get('/settings')->assertSee('Set the real school name');

        $this->save();

        $this->actingAs($this->admin)->get('/settings')->assertDontSee('Set the real school name');
    }

    public function test_the_preview_starts_with_the_saved_rules(): void
    {
        // Sample: 14 present, 3 late, 2 absent, 1 excused (left out) -> 17 / 19 = 89.5%
        $this->actingAs($this->admin)->get('/settings')->assertSee('89.5%')->assertSee('Not flagged');

        // Late no longer counts -> 14 / 19 = 73.7%; a 5% alert flags 2 / 19 = 10.5%
        $this->save(['late_counts_as_attended' => 0, 'absentee_threshold_percent' => 5]);
        $this->actingAs($this->admin)->get('/settings')->assertSee('73.7%')->assertSee('Flagged for frequent absence');
    }

    public function test_the_last_change_is_shown_with_who_made_it(): void
    {
        $this->actingAs($this->admin)->get('/settings')->assertSee('No changes recorded yet');

        $this->save(['max_units_per_term' => 21]);

        $this->actingAs($this->admin)->get('/settings')
            ->assertSee('Ana Admin')->assertSee('Maximum units')->assertSee('School name');
    }

    public function test_saving_without_changes_says_so_and_logs_nothing(): void
    {
        $this->save();
        $this->save()->assertSessionHas('info', 'Nothing changed.');

        $this->assertSame(1, \App\Models\AuditLog::where('action', 'settings.updated')->count());
    }

    public function test_only_the_administrator_can_open_settings(): void
    {
        $registrar = User::factory()->create();
        $registrar->forceFill(['role' => 'registrar', 'is_active' => true, 'must_change_password' => false])->save();

        $this->actingAs($registrar)->get('/settings')->assertForbidden();
        $this->actingAs($registrar)->put('/settings', $this->payload())->assertForbidden();
    }

    // ---------- Session timeout ----------

    public function test_the_session_timeout_is_saved_and_applied_to_requests(): void
    {
        $this->save(['session_lifetime_minutes' => 30]);
        $this->assertSame('30', Setting::get(SessionTimeout::SETTING));

        $this->get('/login');
        $this->assertSame(30, config('session.lifetime'));
        $this->assertSame(30, SessionTimeout::minutes());
    }

    public function test_the_session_timeout_must_be_sensible(): void
    {
        foreach ([0, 4, 481, 'abc'] as $bad) {
            $this->save(['session_lifetime_minutes' => $bad])->assertSessionHasErrors(SessionTimeout::SETTING);
        }

        $this->assertNull(Setting::get(SessionTimeout::SETTING));
    }

    public function test_without_a_saved_timeout_the_env_value_is_used(): void
    {
        config(['session.lifetime' => 95]);

        $this->assertSame(95, SessionTimeout::minutes());
    }

    public function test_the_readiness_check_reads_the_admin_timeout(): void
    {
        $this->save(['session_lifetime_minutes' => 300]);
        $check = collect(ReadinessCheck::run())->firstWhere('key', 'session_lifetime');

        $this->assertSame('warn', $check['level']);
        $this->assertStringContainsString('300', $check['detail']);
    }

    // ---------- Logo and address ----------

    public function test_a_logo_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('local');

        $this->save(['logo' => $this->png()])->assertSessionHasNoErrors();
        $first = Setting::get(SchoolBranding::LOGO_SETTING);
        Storage::disk('local')->assertExists($first);

        // The logo is public: the sign-in page shows it to people who are not signed in.
        auth()->logout();
        $this->get(route('school.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/login')->assertSee(SchoolBranding::logoUrl(), false);

        // Replacing deletes the old file.
        $this->save(['logo' => $this->png('new.png')]);
        $second = Setting::get(SchoolBranding::LOGO_SETTING);
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);

        // Removing deletes the file and the address stops working.
        $this->save(['remove_logo' => 1]);
        Storage::disk('local')->assertMissing($second);
        $this->assertSame('', Setting::get(SchoolBranding::LOGO_SETTING));
        $this->get(route('school.logo'))->assertNotFound();
    }

    public function test_only_safe_small_images_are_accepted_as_a_logo(): void
    {
        Storage::fake('local');

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->save(['logo' => $svg])->assertSessionHasErrors('logo');

        $big = $this->png('big.png', 2500, 2500, pixels: false);
        $this->save(['logo' => $big])->assertSessionHasErrors('logo');

        $heavy = $this->png('heavy.png', 100, 100)->size(2048);
        $this->save(['logo' => $heavy])->assertSessionHasErrors('logo');

        $this->assertNull(Setting::get(SchoolBranding::LOGO_SETTING));
    }

    public function test_the_address_and_logo_appear_on_printed_reports(): void
    {
        Storage::fake('local');
        $this->save(['school_address' => '123 Rizal Street, Quezon City', 'logo' => $this->png()]);

        $this->actingAs($this->admin)->get('/reports/student-master-list/print')->assertOk()
            ->assertSee('Rizal College')->assertSee('123 Rizal Street, Quezon City')
            ->assertSee(SchoolBranding::logoUrl(), false);

        $this->actingAs($this->admin)->get('/reports/student-master-list/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ---------- Caching ----------

    public function test_settings_are_read_with_one_query_and_refreshed_on_save(): void
    {
        Setting::get('school_name'); // warm the cache

        DB::enableQueryLog();
        foreach (['school_name', 'max_units_per_term', 'late_counts_as_attended', 'excused_in_percentage'] as $name) {
            Setting::get($name);
        }
        $this->assertCount(0, DB::getQueryLog(), 'Already cached: no database query.');
        DB::disableQueryLog();

        Setting::put('max_units_per_term', '18');
        $this->assertSame('18', Setting::get('max_units_per_term'));

        Setting::where('name', 'max_units_per_term')->first()->delete();
        $this->assertNull(Setting::get('max_units_per_term'));
    }
}
