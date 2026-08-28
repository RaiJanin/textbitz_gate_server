<?php

use App\Events\TapRecorded;
use App\Jobs\FanOutTapPush;
use App\Models\Gate;
use App\Models\School;
use App\Models\Student;
use App\Models\TapEvent;
use App\Services\Attendance\AlertBuilder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->school = School::factory()->create(['timezone' => 'Asia/Manila', 'attendance_cutoff_time' => '07:45:00']);
    $this->gate = Gate::factory()->for($this->school)->create();
    $this->students = Student::factory()->count(2)->for($this->school)->create();
});

it('back-fills a "late" scenario as late arrivals that surface as alerts', function () {
    $this->artisan('gate:simulate', ['--scenario' => 'late', '--days' => 10, '--fresh' => true])
        ->assertSuccessful();

    expect(TapEvent::where('direction', 'in')->where('is_late', true)->exists())->toBeTrue();

    $alerts = app(AlertBuilder::class)->forStudent($this->students->first(), days: 20);
    expect(collect($alerts)->pluck('type'))->toContain('late');
});

it('back-fills an "absent" scenario as no taps that surface as absent alerts', function () {
    $this->artisan('gate:simulate', ['--scenario' => 'absent', '--days' => 10, '--fresh' => true])
        ->assertSuccessful();

    expect(TapEvent::count())->toBe(0);

    $alerts = app(AlertBuilder::class)->forStudent($this->students->first(), days: 20);
    expect(collect($alerts)->pluck('type'))->toContain('absent');
});

it('--fresh clears existing taps first', function () {
    TapEvent::factory()->create(['student_id' => $this->students->first()->id, 'gate_id' => $this->gate->id]);

    $this->artisan('gate:simulate', ['--scenario' => 'perfect', '--days' => 5, '--fresh' => true])
        ->assertSuccessful();

    expect(TapEvent::where('source', 'test')->exists())->toBeFalse();
});

it('gate:tap fires one live tap through the full pipeline', function () {
    Event::fake([TapRecorded::class]);
    Queue::fake();

    $rfid = $this->students->first()->rfid_uid;

    $this->artisan('gate:tap', ['rfid' => $rfid, '--in' => true, '--late' => true])
        ->assertSuccessful();

    $tap = TapEvent::firstOrFail();
    expect($tap->direction)->toBe('in')
        ->and($tap->is_late)->toBeTrue();

    Event::assertDispatched(TapRecorded::class);
    Queue::assertPushed(FanOutTapPush::class);
});

it('stores tap times in UTC so they read back correctly in the school timezone', function () {
    $this->artisan('gate:simulate', ['--scenario' => 'perfect', '--days' => 3, '--fresh' => true])->assertSuccessful();

    $tap = TapEvent::where('direction', 'in')->orderBy('tapped_at')->firstOrFail();
    $local = $tap->tapped_at->copy()->setTimezone('Asia/Manila');

    // "perfect" arrivals are always before the 07:45 cutoff.
    expect($local->hour)->toBe(7)
        ->and($local->minute)->toBeLessThan(45);
});

it('serves the simulator panel and its tap endpoint', function () {
    $this->get('/simulator')->assertOk()->assertSee('Turnstile Simulator');

    $this->postJson('/simulator/tap', [
        'student_id' => $this->students->first()->id,
        'gate_id' => $this->gate->id,
        'direction' => 'in',
        'timing' => 'late',
    ])->assertOk()->assertJsonStructure(['message']);

    expect(TapEvent::where('is_late', true)->exists())->toBeTrue();
});

it('backfills and resets through the panel endpoints', function () {
    $this->postJson('/simulator/backfill', [
        'school_id' => $this->school->id,
        'gate_id' => $this->gate->id,
        'scenario' => 'mixed',
        'days' => 8,
        'fresh' => true,
    ])->assertOk();

    expect(TapEvent::count())->toBeGreaterThan(0);

    $this->postJson('/simulator/reset', ['school_id' => $this->school->id])->assertOk();

    expect(TapEvent::count())->toBe(0);
});
