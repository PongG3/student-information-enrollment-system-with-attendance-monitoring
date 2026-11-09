<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\ReadinessCheck;
use App\Support\Like;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    private const ATTACKS = [
        "' OR '1'='1",
        "' OR '1'='1' -- ",
        '1 OR 1=1',
        '1; DROP TABLE students; --',
        "' UNION SELECT email, password FROM users -- ",
    ];

    private User $registrar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = User::factory()->create(['email' => 'registrar@school.test', 'password' => Hash::make('Correct-Pass-2026')]);
        $this->registrar->forceFill(['role' => 'registrar', 'is_active' => true, 'must_change_password' => false])->save();

        $program = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        foreach (['2026-0001' => 'Rivera', '2026-0002' => 'Santos'] as $number => $last) {
            Student::create([
                'student_number' => $number, 'first_name' => 'Sam', 'last_name' => $last, 'gender' => 'Female',
                'birth_date' => '2006-05-05', 'program_id' => $program->id, 'year_level' => 1,
            ]);
        }
    }

    public function test_the_like_helper_makes_wildcards_literal(): void
    {
        $this->assertSame('%rivera%', Like::contains('  rivera '));
        $this->assertSame('%50\%%', Like::contains('50%'));
        $this->assertSame('%a\_b%', Like::contains('a_b'));
        $this->assertSame('%a\\\\b%', Like::contains('a\\b'));
        $this->assertSame('%%', Like::contains(null));
    }

    public function test_login_cannot_be_bypassed(): void
    {
        foreach (self::ATTACKS as $attack) {
            $this->post('/login', ['email' => $attack, 'password' => $attack]);
            $this->post('/login', ['email' => 'registrar@school.test', 'password' => $attack]);
            $this->assertGuest();
        }
    }

    public function test_search_boxes_treat_attacks_as_plain_text(): void
    {
        $pages = [
            fn ($q) => route('students.index', ['search' => $q]),
            fn ($q) => route('search', ['q' => $q]),
            fn ($q) => route('enrollments.index', ['search' => $q]),
            fn ($q) => route('subjects.index', ['search' => $q]),
            fn ($q) => route('classes.index', ['search' => $q]),
        ];

        foreach (self::ATTACKS as $attack) {
            foreach ($pages as $page) {
                $this->actingAs($this->registrar)->get($page($attack))->assertOk()
                    ->assertDontSee('2026-0001')->assertDontSee('2026-0002')
                    ->assertDontSee('SQLSTATE');
            }
        }

        $this->assertSame(2, Student::count(), 'The students table is untouched.');
    }

    public function test_a_wildcard_search_does_not_list_everyone(): void
    {
        foreach (['%', '_', '%%'] as $wildcard) {
            $this->actingAs($this->registrar)->get(route('students.index', ['search' => $wildcard]))
                ->assertOk()->assertDontSee('2026-0001')->assertDontSee('2026-0002');
        }

        // Normal searches still work.
        $this->actingAs($this->registrar)->get(route('students.index', ['search' => 'riv']))
            ->assertSee('2026-0001')->assertDontSee('2026-0002');
    }

    public function test_number_filters_ignore_injected_sql(): void
    {
        $this->actingAs($this->registrar)->get(route('students.index', ['year_level' => '2 OR 1=1']))
            ->assertOk()->assertDontSee('2026-0001')->assertDontSee('2026-0002');
    }

    public function test_the_readiness_check_wants_a_separate_admin_database_account(): void
    {
        config([
            'database.connections.mysql.username' => 'sies_app',
            'database.connections.mysql.password' => 'Long-Random-Pass-1234',
            'database.connections.mysql_admin.username' => 'sies_app',
        ]);
        $level = fn () => collect(ReadinessCheck::database('mysql'))->firstWhere('key', 'db_accounts')['level'];

        $this->assertSame('warn', $level());

        config(['database.connections.mysql_admin.username' => 'sies_admin']);
        $this->assertSame('ok', $level());
    }
}
