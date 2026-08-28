<?php

use App\Events\TapRecorded;
use App\Jobs\FanOutTapPush;
use App\Models\Gate;
use App\Models\School;
use App\Models\Student;
use App\Models\TapEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->school = School::factory()->create([
        'timezone' => 'Asia/Manila',
        'attendance_cutoff_time' => '07:45:00',
    ]);
    $this->gate = Gate::factory()->for($this->school)->create();
    $this->student = Student::factory()->for($this->school)->create(['rfid_uid' => 'RFID-TEST-1']);
});

function tapPayload(array $overrides = []): array
{
    return array_merge([
        'rfid_uid' => 'RFID-TEST-1',
        'gate_id' => test()->gate->id,
    ], $overrides);
}

it('rejects an invalid ingest token', function () {
    $this->withHeader('Authorization', 'Bearer wrong-token')
        ->postJson('/api/ingest/tap', tapPayload())
        ->assertStatus(401);
});

it('rejects an unknown rfid uid', function () {
    $this->withHeader('Authorization', "Bearer {$this->school->ingest_token}")
        ->postJson('/api/ingest/tap', tapPayload(['rfid_uid' => 'NOPE']))
        ->assertStatus(422)
        ->assertJson(['error' => 'unknown_uid']);
});

it('records the first tap of the day as an arrival', function () {
    $this->withHeader('Authorization', "Bearer {$this->school->ingest_token}")
        ->postJson('/api/ingest/tap', tapPayload())
        ->assertCreated()
        ->assertJsonPath('tap.direction', 'in');

    expect(TapEvent::count())->toBe(1);
});

it('alternates direction on subsequent taps', function () {
    $headers = ['Authorization' => "Bearer {$this->school->ingest_token}"];

    $this->withHeaders($headers)->postJson('/api/ingest/tap', tapPayload())->assertJsonPath('tap.direction', 'in');
    $this->withHeaders($headers)->postJson('/api/ingest/tap', tapPayload())->assertJsonPath('tap.direction', 'out');
    $this->withHeaders($headers)->postJson('/api/ingest/tap', tapPayload())->assertJsonPath('tap.direction', 'in');
});

it('flags an arrival after the cutoff as late, in the school timezone', function () {
    // 08:30 Manila = 00:30 UTC — after the 07:45 cutoff.
    $late = Carbon::parse('2026-03-02 08:30', 'Asia/Manila');

    $this->withHeader('Authorization', "Bearer {$this->school->ingest_token}")
        ->postJson('/api/ingest/tap', tapPayload(['timestamp' => $late->toIso8601String()]))
        ->assertJsonPath('tap.is_late', true);
});

it('does not flag an arrival before the cutoff as late', function () {
    $early = Carbon::parse('2026-03-02 07:10', 'Asia/Manila');

    $this->withHeader('Authorization', "Bearer {$this->school->ingest_token}")
        ->postJson('/api/ingest/tap', tapPayload(['timestamp' => $early->toIso8601String()]))
        ->assertJsonPath('tap.is_late', false);
});

it('broadcasts TapRecorded and queues the push fan-out', function () {
    Event::fake([TapRecorded::class]);
    Queue::fake();

    $this->withHeader('Authorization', "Bearer {$this->school->ingest_token}")
        ->postJson('/api/ingest/tap', tapPayload())
        ->assertCreated();

    Event::assertDispatched(TapRecorded::class);
    Queue::assertPushed(FanOutTapPush::class);
});

it('marks the gate online on tap', function () {
    $this->gate->update(['status' => Gate::STATUS_OFFLINE, 'last_seen_at' => null]);

    $this->withHeader('Authorization', "Bearer {$this->school->ingest_token}")
        ->postJson('/api/ingest/tap', tapPayload());

    expect($this->gate->fresh()->status)->toBe(Gate::STATUS_ONLINE);
});
