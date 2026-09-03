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
