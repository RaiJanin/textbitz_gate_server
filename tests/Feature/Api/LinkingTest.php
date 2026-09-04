<?php

use App\Events\GuardianLinkedToStudent;
use App\Models\LinkCode;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->student = Student::factory()->for($this->school)->create();
    $this->user = User::factory()->create();
});

it('links a guardian to a student with a valid code', function () {
    Event::fake([GuardianLinkedToStudent::class]);
    Sanctum::actingAs($this->user);

    $code = LinkCode::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'code' => 'GATE-ABC12',
        'default_relationship' => 'Dad',
    ]);

    $this->postJson('/api/link/request', ['code' => 'gate-abc12'])
        ->assertCreated()
        ->assertJsonPath('linked', true)
        ->assertJsonPath('student.id', $this->student->id);

    expect($this->user->fresh()->canViewStudent($this->student))->toBeTrue();

    $code->refresh();
    expect($code->consumed_at)->not->toBeNull()
        // the consumer is the authenticated caller's guardian, so the server can
        // always say who redeemed a code
        ->and($code->consumed_by_guardian_id)->toBe($this->user->guardian->id)
        ->and($code->consumedByGuardian->user->id)->toBe($this->user->id);

    Event::assertDispatched(GuardianLinkedToStudent::class);
});

it('rejects an expired code', function () {
    Sanctum::actingAs($this->user);

    LinkCode::factory()->expired()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'code' => 'GATE-EXPIRD',
    ]);

    $this->postJson('/api/link/request', ['code' => 'GATE-EXPIRD'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'invalid_code');
});

it('rejects an already consumed code', function () {
    Sanctum::actingAs($this->user);

    LinkCode::factory()->consumed()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'code' => 'GATE-USEDUP',
    ]);

    $this->postJson('/api/link/request', ['code' => 'GATE-USEDUP'])
        ->assertStatus(422);
});

it('applies the relationship from the link request and syncs the guardian default', function () {
    Sanctum::actingAs($this->user);

    LinkCode::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'code' => 'GATE-REL01',
    ]);

    $this->postJson('/api/link/request', ['code' => 'GATE-REL01', 'relationship' => 'Parent'])
        ->assertCreated()
        ->assertJsonPath('student.relationship', 'Parent');

    expect($this->user->guardian->fresh()->role)->toBe('Parent')
        ->and($this->user->guardian->students()->find($this->student->id)->pivot->relationship)->toBe('Parent');
});

it('defaults the link relationship to the guardian role', function () {
    Sanctum::actingAs($this->user);
    $this->user->guardian->update(['role' => 'Parent']);

    LinkCode::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'code' => 'GATE-REL02',
    ]);

    $this->postJson('/api/link/request', ['code' => 'GATE-REL02'])
        ->assertCreated()
        ->assertJsonPath('student.relationship', 'Parent');
});

it('rejects a relationship outside the Parent/Guardian enum', function () {
    Sanctum::actingAs($this->user);
    LinkCode::factory()->create([
        'school_id' => $this->school->id, 'student_id' => $this->student->id, 'code' => 'GATE-REL03',
    ]);

    $this->postJson('/api/link/request', ['code' => 'GATE-REL03', 'relationship' => 'Auntie'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('relationship');
});

it('updates the per-student relationship without touching the guardian default', function () {
    Sanctum::actingAs($this->user);
    $this->user->guardian->students()->attach($this->student, ['relationship' => 'Guardian']);

    $this->putJson("/api/students/{$this->student->id}/relationship", ['relationship' => 'Parent'])
        ->assertOk()
        ->assertJsonPath('student.relationship', 'Parent');

    expect($this->user->guardian->students()->find($this->student->id)->pivot->relationship)->toBe('Parent')
        ->and($this->user->guardian->fresh()->role)->toBe('Guardian'); // default unchanged
});

it('forbids changing the relationship for an unlinked student', function () {
    Sanctum::actingAs($this->user);
    $other = Student::factory()->for($this->school)->create();

    $this->putJson("/api/students/{$other->id}/relationship", ['relationship' => 'Parent'])
        ->assertForbidden();
});

it('exposes the guardian role through /api/me', function () {
    Sanctum::actingAs($this->user);
    $this->user->guardian->update(['role' => 'Parent']);

    $this->getJson('/api/me')->assertOk()->assertJsonPath('guardian.role', 'Parent');
});
