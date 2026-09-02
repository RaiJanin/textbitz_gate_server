<?php

use App\Actions\IssueLinkCode;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\LinkCode;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function superAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

function schoolAdmin(School $school): User
{
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

it('keeps non-admins out of the panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('lets an admin open the dashboard, resource lists and create pages', function () {
    $admin = superAdmin();
    School::factory()->create();

    foreach ([
        '/admin',
        '/admin/students', '/admin/students/create',
        '/admin/link-codes', '/admin/link-codes/create',
        '/admin/guardians',
        '/admin/gates', '/admin/gates/create',
        '/admin/schools', '/admin/schools/create',
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});

it('applies the students and link-codes table filters without error', function () {
    $admin = superAdmin();
    Student::factory()->count(2)->create();

    Livewire::actingAs($admin)->test(\App\Filament\Resources\Students\Pages\ListStudents::class)
        ->filterTable('needs_guardian', true)
        ->filterTable('code_outstanding', true)
        ->assertOk();

    Livewire::actingAs($admin)->test(\App\Filament\Resources\LinkCodes\Pages\ListLinkCodes::class)
        ->filterTable('status', 'usable')
        ->assertOk();
});

it('hides the schools resource from a school-scoped admin', function () {
    $school = School::factory()->create();

    $this->actingAs(schoolAdmin($school))->get('/admin/schools')->assertForbidden();
});

describe('IssueLinkCode', function () {
    it('mints a usable code scoped to the student school', function () {
        $student = Student::factory()->create();

        $code = IssueLinkCode::run($student, 'Mom', 14);

        expect($code->student_id)->toBe($student->id)
            ->and($code->school_id)->toBe($student->school_id)
            ->and($code->default_relationship)->toBe('Mom')
            ->and($code->isUsable())->toBeTrue()
            ->and($code->expires_at->isFuture())->toBeTrue()
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

it('serves a printable slip to an admin and 403s everyone else', function () {
    $student = Student::factory()->create();
    $code = IssueLinkCode::run($student);

    $this->actingAs(superAdmin())
        ->get(route('admin.link-codes.slip', $code))
        ->assertOk()
        ->assertSee($code->code);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.link-codes.slip', $code))
        ->assertForbidden();
});

it('issues a code from the students table row action', function () {
    $this->actingAs(superAdmin());
    $student = Student::factory()->create();

    Livewire::test(ListStudents::class)
        ->callTableAction('issueLinkCode', $student, data: [
            'relationship' => 'Dad',
            'valid_for_days' => 20,
        ])
        ->assertHasNoTableActionErrors();

    $code = $student->linkCodes()->usable()->first();

    expect($code)->not->toBeNull()
        ->and($code->default_relationship)->toBe('Dad');
});

it('stops a school-scoped admin printing another school slip', function () {
    $mine = School::factory()->create();
    $other = School::factory()->create();
    $code = IssueLinkCode::run(Student::factory()->create(['school_id' => $other->id]));

    $this->actingAs(schoolAdmin($mine))
        ->get(route('admin.link-codes.slip', $code))
        ->assertForbidden();
});

describe('make:filament-user', function () {
    it('captures the phone number and flags the account as admin', function () {
        $this->artisan('make:filament-user', [
            '--name' => 'Ops',
            '--email' => 'ops@example.com',
            '--password' => 'password123',
            '--phone' => '+639171112222',
        ])->assertSuccessful();

        $user = User::where('email', 'ops@example.com')->first();

        expect($user->phone_number)->toBe('+639171112222')
            ->and($user->is_admin)->toBeTrue()
            ->and($user->school_id)->toBeNull();
    });

    it('can scope the admin to a school and opt out of admin access', function () {
        $school = School::factory()->create();

        $this->artisan('make:filament-user', [
            '--name' => 'FrontDesk',
            '--email' => 'desk@example.com',
            '--password' => 'password123',
            '--phone' => '+639171113333',
            '--school' => (string) $school->id,
        ])->assertSuccessful();

        $this->artisan('make:filament-user', [
            '--name' => 'NoPanel',
            '--email' => 'nopanel@example.com',
            '--password' => 'password123',
            '--phone' => '+639171114444',
            '--no-admin' => true,
        ])->assertSuccessful();

        expect(User::where('email', 'desk@example.com')->first())
            ->school_id->toBe($school->id)
            ->is_admin->toBeTrue();

        expect(User::where('email', 'nopanel@example.com')->first()->is_admin)->toBeFalse();
    });

    it('rejects a malformed or duplicate phone number', function () {
        User::factory()->create(['phone_number' => '+639170000123']);

        $this->artisan('make:filament-user', [
            '--name' => 'X', '--email' => 'x@example.com', '--password' => 'password123',
            '--phone' => '09170000123',
        ])->assertFailed();

        $this->artisan('make:filament-user', [
            '--name' => 'Y', '--email' => 'y@example.com', '--password' => 'password123',
            '--phone' => '+639170000123',
        ])->assertFailed();

        expect(User::whereIn('email', ['x@example.com', 'y@example.com'])->count())->toBe(0);
    });
});
