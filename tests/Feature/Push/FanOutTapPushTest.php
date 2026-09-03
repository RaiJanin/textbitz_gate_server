<?php

use App\Jobs\FanOutTapPush;
use App\Jobs\SendPushNotification;
use App\Models\Gate;
use App\Models\NotificationPreference;
use App\Models\School;
use App\Models\Student;
use App\Models\TapEvent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->school = School::factory()->create(['attendance_cutoff_time' => '07:45:00']);
    $this->gate = Gate::factory()->for($this->school)->create();
    $this->student = Student::factory()->for($this->school)->create();

    $this->guardianUser = User::factory()->create(); // UserObserver adds the guardian profile
    $this->guardianUser->guardian->students()->attach($this->student, ['relationship' => 'Mom']);
});

function makeTap(string $direction = 'in', bool $late = false): TapEvent
{
    return TapEvent::factory()->create([
        'student_id' => test()->student->id,
        'gate_id' => test()->gate->id,
        'direction' => $direction,
        'is_late' => $late,
    ]);
}

it('queues an arrival push when the guardian has arrivals enabled', function () {
    Queue::fake();
    $this->guardianUser->preferencesFor(NotificationPreference::ROLE_GUARDIAN)->update(['arrival' => true]);

    (new FanOutTapPush(makeTap('in')))->handle();

    Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->guardianUser->id);
});

it('skips the arrival push when the guardian has arrivals disabled', function () {
    Queue::fake();
    $this->guardianUser->preferencesFor(NotificationPreference::ROLE_GUARDIAN)
        ->update(['arrival' => false, 'late_alert' => false]);

    (new FanOutTapPush(makeTap('in')))->handle();

    Queue::assertNotPushed(SendPushNotification::class);
});

it('skips the departure push when departures are disabled', function () {
    Queue::fake();
    $this->guardianUser->preferencesFor(NotificationPreference::ROLE_GUARDIAN)->update(['departure' => false]);

    (new FanOutTapPush(makeTap('out')))->handle();

    Queue::assertNotPushed(SendPushNotification::class);
});

it('still notifies about a late arrival when only arrivals are enabled', function () {
    Queue::fake();
    $this->guardianUser->preferencesFor(NotificationPreference::ROLE_GUARDIAN)
        ->update(['arrival' => true, 'late_alert' => false]);

    (new FanOutTapPush(makeTap('in', late: true)))->handle();

    Queue::assertPushed(SendPushNotification::class);
});
