<?php

use App\Models\Gate;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TapEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->school = School::factory()->create(['timezone' => 'Asia/Manila', 'attendance_cutoff_time' => '07:45:00']);
    $this->gate = Gate::factory()->for($this->school)->create();
    $this->student = Student::factory()->for($this->school)->create();
    $this->otherStudent = Student::factory()->for($this->school)->create();

    $this->user = User::factory()->create();
    $guardian = Guardian::factory()->for($this->user)->create();
    $guardian->students()->attach($this->student, ['relationship' => 'Dad']);
});

it('returns today status with a derived presence state', function () {
    Sanctum::actingAs($this->user);

    TapEvent::factory()->create([
        'student_id' => $this->student->id,
        'gate_id' => $this->gate->id,
        'direction' => 'in',
        'tapped_at' => Carbon::now('Asia/Manila')->setTime(7, 30),
        'is_late' => false,
    ]);

    $this->getJson("/api/students/{$this->student->id}/status")
        ->assertOk()
        ->assertJsonPath('presence', 'at_school')
        ->assertJsonPath('on_time', true);
});

it('returns a month of day records for history', function () {
    Sanctum::actingAs($this->user);

    $this->getJson("/api/students/{$this->student->id}/history?month=2026-05")
        ->assertOk()
        ->assertJsonPath('month', '2026-05')
        ->assertJsonCount(31, 'days');
});

it('returns an alerts feed', function () {
    Sanctum::actingAs($this->user);

    $this->getJson("/api/students/{$this->student->id}/alerts")
        ->assertOk()
        ->assertJsonStructure(['alerts']);
});

it('forbids reading a student the account is not linked to', function () {
    Sanctum::actingAs($this->user);

    $this->getJson("/api/students/{$this->otherStudent->id}/status")->assertForbidden();
    $this->getJson("/api/students/{$this->otherStudent->id}/history")->assertForbidden();
    $this->getJson("/api/students/{$this->otherStudent->id}/alerts")->assertForbidden();
});

it('requires authentication', function () {
    $this->getJson("/api/students/{$this->student->id}/status")->assertUnauthorized();
});

it('exposes both profiles through /api/me when the account holds two roles', function () {
    $studentAccountUser = $this->user;
    $studentAccountUser->studentAccount()->create(['student_id' => $this->student->id]);

    Sanctum::actingAs($studentAccountUser);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('roles', ['guardian', 'student']);
});
