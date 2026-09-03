<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Turnstile Simulator — {{ config('app.name') }}</title>
<style>
    :root { --bd:#e2e8f0; --mut:#64748b; --bg:#f8fafc; --card:#fff; --accent:#2563eb; }
    * { box-sizing:border-box; }
    body { margin:0; font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:#0f172a; }
    .wrap { max-width:1100px; margin:0 auto; padding:24px 20px 80px; }
    h1 { font-size:20px; margin:0 0 4px; }
    h2 { font-size:14px; text-transform:uppercase; letter-spacing:.04em; color:var(--mut); margin:0 0 12px; }
    p.sub { color:var(--mut); margin:0 0 20px; }
    .card { background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:16px; margin-bottom:16px; }
    .grid { display:grid; gap:16px; grid-template-columns:1fr 1fr; }
    @media (max-width:820px){ .grid { grid-template-columns:1fr; } }
    .row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    label { font-size:12px; color:var(--mut); display:block; margin-bottom:3px; }
    select, input[type=number] { padding:7px 9px; border:1px solid var(--bd); border-radius:8px; background:#fff; font:inherit; }
    button { padding:8px 12px; border:1px solid var(--bd); border-radius:8px; background:#fff; font:inherit; cursor:pointer; }
    button:hover { background:#f1f5f9; }
    button.primary { background:var(--accent); border-color:var(--accent); color:#fff; }
    button.primary:hover { filter:brightness(1.05); }
    button.danger { border-color:#fecaca; color:#b91c1c; }
    button.danger:hover { background:#fef2f2; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th, td { text-align:left; padding:6px 8px; border-bottom:1px solid var(--bd); }
    th { color:var(--mut); font-weight:600; }
    .badge { display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; font-weight:600; }
    .b-on_time,.b-on-time { background:#dcfce7; color:#15803d; }
    .b-late { background:#fef3c7; color:#b45309; }
    .b-absent { background:#fee2e2; color:#b91c1c; }
    .b-none { background:#f1f5f9; color:#64748b; }
    .b-weekly_summary { background:#e0e7ff; color:#4338ca; }
    .callout { background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:14px 16px; margin-bottom:20px; font-size:13px; }
    .callout code { background:#dbeafe; padding:1px 5px; border-radius:4px; }
    #toast { position:fixed; left:50%; bottom:24px; transform:translateX(-50%); background:#0f172a; color:#fff; padding:10px 16px; border-radius:10px; opacity:0; transition:opacity .2s; pointer-events:none; max-width:90vw; }
    #toast.show { opacity:1; }
    .muted { color:var(--mut); }
    .scroll { max-height:340px; overflow:auto; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Turnstile Simulator</h1>
    <p class="sub">{{ $school->name }} · {{ $school->timezone }} · cutoff {{ \Illuminate\Support\Str::of($school->attendance_cutoff_time)->before('.') }}</p>

    <div class="callout">
        <strong>How the alerts feed works.</strong> Alerts are <em>not stored</em>. On every request
        <code>AlertBuilder</code> walks the last 90 days of day-records (derived from the taps below):
        every <span class="badge b-late">late</span> day → a late alert,
        every <span class="badge b-absent">absent</span> day (a past weekday with zero taps) → an absent alert,
        plus one <span class="badge b-weekly_summary">weekly&nbsp;summary</span> per week.
        Add or clear taps on the left and the alerts on the right change with them.
    </div>

    {{-- Context selector --}}
    <form class="card row" method="get">
        <div>
            <label>School</label>
            <select name="school" onchange="this.form.submit()">
                @foreach ($schools as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $school->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Gate</label>
            <select name="gate" onchange="this.form.submit()">
                @foreach ($school->gates as $g)
                    <option value="{{ $g->id }}" @selected($gate && $g->id === $gate->id)>{{ $g->name }} ({{ $g->status }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Student ({{ $school->students->count() }}) — newest first</label>
            <select name="student" onchange="this.form.submit()">
                @foreach ($school->students as $st)
                    <option value="{{ $st->id }}" @selected($student && $st->id === $student->id)>
                        {{ $st->full_name }} — {{ $st->rfid_uid }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="grid">
        {{-- Live taps --}}
        <div class="card">
            <h2>Live tap · {{ $student?->full_name ?? '—' }}</h2>
            <p class="muted" style="margin-top:-4px">Fires through the real pipeline — broadcast + push fan-out.</p>
            <div class="row">
                <button class="primary" data-tap='{"direction":"in","timing":"on-time"}'>Arrive · on time</button>
                <button class="primary" data-tap='{"direction":"in","timing":"late"}'>Arrive · late</button>
                <button data-tap='{"direction":"out","timing":"now"}'>Dismiss (out)</button>
                <button data-tap='{"timing":"now"}'>Tap now (auto)</button>
            </div>
        </div>

        {{-- Backfill --}}
        <div class="card">
            <h2>Back-fill history</h2>
            <div class="row" style="align-items:flex-end">
                <div>
                    <label>Scenario</label>
                    <select id="scenario">
                        @foreach ($scenarios as $sc)
                            <option value="{{ $sc }}">{{ $sc }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Weekdays</label>
                    <input type="number" id="days" value="14" min="1" max="120" style="width:80px">
                </div>
                <div>
                    <label><input type="checkbox" id="allStudents" checked> all students</label>
                    <label><input type="checkbox" id="fresh" checked> clear first</label>
                </div>
                <button class="primary" id="runBackfill">Run</button>
            </div>
        </div>
    </div>

    {{-- Utilities --}}
    <div class="card row">
        <h2 style="margin:0 12px 0 0">Utilities</h2>
        <button id="flagAbsent">Flag absents (today)</button>
        <button class="danger" data-reset="student">Clear taps · this student</button>
        <button class="danger" data-reset="school">Clear taps · whole school</button>
    </div>

    {{-- Derived data --}}
    <div class="grid">
        <div class="card">
            <h2>Day records — last 21 days ({{ $student?->full_name ?? '—' }})</h2>
            <div class="scroll">
                <table>
                    <thead><tr><th>Date</th><th>In</th><th>Out</th><th>State</th><th>#</th></tr></thead>
                    <tbody>
                    @forelse ($dayRows as $d)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($d['date'])->format('D M j') }}</td>
                            <td>{{ $d['first_in'] ?? '—' }}</td>
                            <td>{{ $d['last_out'] ?? '—' }}</td>
                            <td><span class="badge b-{{ $d['state'] }}">{{ $d['state'] }}</span></td>
                            <td>{{ count($d['taps']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No student selected.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Derived alerts ({{ count($alertRows) }})</h2>
            <div class="scroll">
                <table>
                    <thead><tr><th>Date</th><th>Type</th><th>Detail</th></tr></thead>
                    <tbody>
                    @forelse ($alertRows as $a)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($a['date'])->format('M j') }}</td>
                            <td><span class="badge b-{{ $a['type'] }}">{{ $a['type'] }}</span></td>
                            <td><strong>{{ $a['title'] }}</strong><br><span class="muted">{{ $a['body'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">No alerts — every school day is on time.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Recent taps --}}
    <div class="card">
        <h2>Recent taps (all students at this school)</h2>
        <div class="scroll">
            <table>
                <thead><tr><th>When</th><th>Student</th><th>Dir</th><th>Gate</th><th>Late</th><th>Source</th></tr></thead>
                <tbody>
                @forelse ($recentTaps as $t)
                    <tr>
                        <td>{{ $t->tapped_at->setTimezone($school->timezone)->format('D M j, g:i A') }}</td>
                        <td>{{ $t->student?->full_name }}</td>
                        <td>{{ strtoupper($t->direction) }}</td>
                        <td>{{ $t->gate?->name }}</td>
                        <td>{{ $t->is_late ? 'yes' : '' }}</td>
                        <td class="muted">{{ $t->source }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No taps yet — run a back-fill above.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const ctx = {
    school: {{ $school->id }},
    gate: {{ $gate?->id ?? 'null' }},
    student: {{ $student?->id ?? 'null' }},
};

function toast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2600);
}

async function post(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
    return data;
}

function run(promise) {
    promise
        .then((d) => { toast(d.message || 'Done'); setTimeout(() => location.reload(), 700); })
        .catch((e) => toast('Error: ' + e.message));
}

document.querySelectorAll('[data-tap]').forEach((btn) => {
    btn.addEventListener('click', () => {
        if (!ctx.student || !ctx.gate) return toast('Pick a student and gate first.');
        run(post('{{ route('simulator.tap') }}', {
            student_id: ctx.student,
            gate_id: ctx.gate,
            ...JSON.parse(btn.dataset.tap),
        }));
    });
});

document.getElementById('runBackfill').addEventListener('click', () => {
    run(post('{{ route('simulator.backfill') }}', {
        school_id: ctx.school,
        gate_id: ctx.gate,
        student_id: document.getElementById('allStudents').checked ? null : ctx.student,
        scenario: document.getElementById('scenario').value,
        days: Number(document.getElementById('days').value),
        fresh: document.getElementById('fresh').checked,
    }));
});

document.getElementById('flagAbsent').addEventListener('click', () => {
    run(post('{{ route('simulator.flag-absent') }}', {}));
});

document.querySelectorAll('[data-reset]').forEach((btn) => {
    btn.addEventListener('click', () => {
        if (!confirm('Delete tap events?')) return;
        run(post('{{ route('simulator.reset') }}', {
            school_id: ctx.school,
            student_id: btn.dataset.reset === 'student' ? ctx.student : null,
        }));
    });
});
</script>
</body>
</html>
