<?php

use App\Actions\IssueLinkCode;
use App\Filament\Resources\Guardians\Pages\CreateGuardian;
use App\Filament\Resources\LinkCodes\Pages\ListLinkCodes;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\AdminUser;
use App\Models\Guardian;
use App\Models\LinkCode;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function superAdmin(): AdminUser
{
    return AdminUser::factory()->create();
}

function schoolAdmin(School $school): AdminUser
{
    return AdminUser::factory()->forSchool($school)->create();
}

function actingAsAdmin($admin)
{
    test()->actingAs($admin, 'admin');
    Livewire::actingAs($admin);

    return $admin;
}

it('sends guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an admin open the dashboard, resource lists and create pages', function () {
    actingAsAdmin(superAdmin());
    School::factory()->create();

    foreach ([
        '/admin',
        '/admin/students', '/admin/students/create',
        '/admin/link-codes', '/admin/link-codes/create',
        '/admin/guardians', '/admin/guardians/create',
        '/admin/gates', '/admin/gates/create',
        '/admin/schools', '/admin/schools/create',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('applies the students and link-codes table filters without error', function () {
    actingAsAdmin(superAdmin());
    Student::factory()->count(2)->create();

    Livewire::test(ListStudents::class)
        ->filterTable('needs_guardian', true)
        ->filterTable('code_outstanding', true)
        ->assertOk();

    Livewire::test(ListLinkCodes::class)
        ->filterTable('status', 'usable')
        ->assertOk();
});

it('hides the schools resource from a school-scoped admin', function () {
    actingAsAdmin(schoolAdmin(School::factory()->create()));

    $this->get('/admin/schools')->assertForbidden();
});

describe('IssueLinkCode', function () {
    it('mints a usable code scoped to the student school', function () {
        $student = Student::factory()->create();

        $code = IssueLinkCode::run($student, 'Parent', 14);

        expect($code->student_id)->toBe($student->id)
            ->and($code->school_id)->toBe($student->school_id)
            ->and($code->default_relationship)->toBe('Parent')
            ->and($code->isUsable())->toBeTrue()
            ->and($code->status)->toBe('usable');
    });

    it('revokes an earlier still-usable code for the same student', function () {
        $student = Student::factory()->create();

        $first = IssueLinkCode::run($student);
        $second = IssueLinkCode::run($student);

        expect($first->fresh()->isUsable())->toBeFalse()
            ->and($second->fresh()->isUsable())->toBeTrue()
            ->and($student->linkCodes()->usable()->count())->toBe(1);
    });

    it('revoke kills an unused code but leaves a redeemed one alone', function () {
        $student = Student::factory()->create();

        $unused = IssueLinkCode::run($student);
        IssueLinkCode::revoke($unused);
        expect($unused->fresh()->status)->toBe('expired');

        $redeemed = LinkCode::factory()->create([
            'student_id' => $student->id,
            'school_id' => $student->school_id,
            'consumed_at' => now(),
        ]);
        IssueLinkCode::revoke($redeemed);
        expect($redeemed->fresh()->status)->toBe('consumed');
    });
});

it('issues a code from the students table row action', function () {
    actingAsAdmin(superAdmin());
    $student = Student::factory()->create();

    Livewire::test(ListStudents::class)
        ->callTableAction('issueLinkCode', $student, data: ['relationship' => 'Parent', 'valid_for_days' => 20])
        ->assertHasNoTableActionErrors();

    expect($student->linkCodes()->usable()->first()?->default_relationship)->toBe('Parent');
});

describe('link-code slips', function () {
    it('serves a printable slip to an admin', function () {
        $code = IssueLinkCode::run(Student::factory()->create());

        $this->actingAs(superAdmin(), 'admin')
            ->get(route('admin.link-codes.slip', $code))
            ->assertOk()
            ->assertSee($code->code);
    });

    it('is not reachable without an admin session', function () {
        $code = IssueLinkCode::run(Student::factory()->create());

        $this->get(route('admin.link-codes.slip', $code))->assertForbidden();
    });

    it('stops a school-scoped admin printing another school slip', function () {
        $code = IssueLinkCode::run(Student::factory()->create(['school_id' => School::factory()->create()->id]));

        $this->actingAs(schoolAdmin(School::factory()->create()), 'admin')
            ->get(route('admin.link-codes.slip', $code))
            ->assertForbidden();
    });
});

describe('make:filament-user', function () {
    it('creates an admin in x08 by username, email optional', function () {
        $this->artisan('make:filament-user', [
            '--name' => 'ops', '--password' => 'password123',
            '--phone' => '+639171112222', '--no-interaction' => true,
        ])->assertSuccessful();

        $admin = AdminUser::where('name', 'ops')->first();

        expect($admin)->not->toBeNull()
            ->and($admin->email)->toBeNull()
            ->and($admin->phone_number)->toBe('+639171112222')
            ->and($admin->school_id)->toBeNull()
            ->and(User::where('phone_number', '+639171112222')->exists())->toBeFalse();
    });

    it('scopes the admin to a school', function () {
        $school = School::factory()->create();

        $this->artisan('make:filament-user', [
            '--name' => 'frontdesk', '--password' => 'password123',
            '--school' => (string) $school->id, '--no-interaction' => true,
        ])->assertSuccessful();

        expect(AdminUser::where('name', 'frontdesk')->first()->school_id)->toBe($school->id);
    });

    it('rejects a duplicate username', function () {
        AdminUser::factory()->create(['name' => 'taken']);

        $this->artisan('make:filament-user', [
            '--name' => 'taken', '--password' => 'password123', '--no-interaction' => true,
        ])->assertFailed();

        expect(AdminUser::where('name', 'taken')->count())->toBe(1);
    });

    it('rejects a malformed phone number', function () {
        $this->artisan('make:filament-user', [
            '--name' => 'badphone', '--password' => 'password123',
            '--phone' => '09170000123', '--no-interaction' => true,
        ])->assertFailed();

        expect(AdminUser::where('name', 'badphone')->exists())->toBeFalse();
    });
});

describe('admin login page', function () {
    it('signs in with a username and password', function () {
        AdminUser::factory()->create(['name' => 'jandel', 'password' => Hash::make('s3cret-pass')]);

        Livewire::test(\App\Filament\Auth\Login::class)
            ->fillForm(['name' => 'jandel', 'password' => 's3cret-pass'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        expect(auth('admin')->check())->toBeTrue()
            ->and(auth('admin')->user()->name)->toBe('jandel');
    });

    it('rejects a wrong password', function () {
        AdminUser::factory()->create(['name' => 'jandel', 'password' => Hash::make('s3cret-pass')]);

        Livewire::test(\App\Filament\Auth\Login::class)
            ->fillForm(['name' => 'jandel', 'password' => 'nope'])
            ->call('authenticate')
            ->assertHasFormErrors(['name']);

        expect(auth('admin')->check())->toBeFalse();
    });
});

describe('guardian ↔ client-account sync', function () {
    it('gives every new client account a guardian profile and guardian preferences', function () {
        $user = User::factory()->create();

        expect($user->guardian)->not->toBeNull()
            ->and($user->guardian->user_id)->toBe($user->id)
            ->and($user->preferencesFor('guardian')->arrival)->toBeTrue();
    });

    it('provisions a mobile-app login when an admin creates a guardian', function () {
        actingAsAdmin(superAdmin());

        Livewire::test(CreateGuardian::class)
            ->fillForm([
                'name' => 'Rosa Cruz',
                'phone' => '+639172223333',
                'email' => 'rosa@example.com',
                'password' => 'guardianpass',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $guardian = Guardian::where('phone', '+639172223333')->first();

        expect($guardian->user)->not->toBeNull()
            ->and($guardian->user->phone_number)->toBe('+639172223333')
            ->and(Hash::check('guardianpass', $guardian->user->password))->toBeTrue();
    });

    it('auto-generates a password when the admin leaves it blank', function () {
        actingAsAdmin(superAdmin());

        Livewire::test(CreateGuardian::class)
            ->fillForm(['name' => 'No Pass', 'phone' => '+639172224444'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Guardian::where('phone', '+639172224444')->first()->user)->not->toBeNull();
    });

    it('rejects an admin-created guardian whose phone already has an account', function () {
        actingAsAdmin(superAdmin());
        User::factory()->create(['phone_number' => '+639172225555']); // gets its own guardian

        Livewire::test(CreateGuardian::class)
            ->fillForm(['name' => 'Dup', 'phone' => '+639172225555', 'password' => 'whatever12'])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        expect(Guardian::where('phone', '+639172225555')->count())->toBe(1); // only the original
    });

    it('lets an admin edit a guardian without tripping the phone rule', function () {
        actingAsAdmin(superAdmin());
        $guardian = User::factory()->create()->guardian;

        Livewire::test(\App\Filament\Resources\Guardians\Pages\EditGuardian::class, ['record' => $guardian->id])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($guardian->fresh()->name)->toBe('Renamed');
    });

    it('refuses a programmatic duplicate guardian for an existing account', function () {
        User::factory()->create(['phone_number' => '+639172226666']); // already has a guardian

        expect(fn () => Guardian::create(['name' => 'Reuse', 'phone' => '+639172226666']))
            ->toThrow(RuntimeException::class);

        expect(Guardian::where('phone', '+639172226666')->count())->toBe(1)
            ->and(User::where('phone_number', '+639172226666')->count())->toBe(1);
    });

    it('always stores user_id — every guardian points at a client account', function () {
        // via app sign-up
        $signUp = User::factory()->create();
        // via the admin panel
        actingAsAdmin(superAdmin());
        Livewire::test(CreateGuardian::class)
            ->fillForm(['name' => 'Panel Made', 'phone' => '+639172227777', 'password' => 'panelpass1'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Guardian::whereNull('user_id')->count())->toBe(0)
            ->and($signUp->guardian->user_id)->toBe($signUp->id)
            ->and(Guardian::where('phone', '+639172227777')->first()->user->phone_number)->toBe('+639172227777');
    });
});
