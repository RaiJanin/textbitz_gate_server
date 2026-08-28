# TextBitz Gate — Setup & Usage

School RFID-turnstile **attendance notification** system. When a student taps their
ID at a gate, the event is captured, resolved (IN/OUT + lateness), and pushed in
real time to the parent's (and optionally the student's own) mobile app.

This document covers **both** repos:

| Repo | Role | Stack |
|---|---|---|
| `textbitz_gate/server` | Central server — source of truth for attendance | Laravel 13, PHP 8.4, SQLite, Reverb |
| `textbitz_gate/client` | Parent/student mobile app | Laravel 13 + Inertia + Vue 3, **NativePHP Mobile v3** (Android), SQLite |

```
[RFID turnstile] --HTTPS--> [server]  --Reverb broadcast--> [client app]  (live, app open)
                                      --FCM push----------> [client app]  (background/closed)
                            [client]  --pull /api/*-------> [server]       (offline-first cache)
```

The mobile app **never talks to turnstile hardware** — only to the server
(`TEXTBITZ_SERVER_URL`).

---

## 1. Prerequisites

- PHP **8.4+** with `pdo_sqlite`, `openssl`, `mbstring`
- Composer 2
- Node 20+ / npm (client only — the server's `/simulator` page needs no build)
- For Android builds: Android Studio + SDK, JDK 17 (see NativePHP Mobile docs)
- macOS/Linux/Windows all fine for the server; **Android builds work on macOS + Linux + Windows** (WSL unsupported)

---

## 2. Server — setup

```bash
cd textbitz_gate/server

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # creates schema + demo data (see §4)
```

### Key `.env` values

| Var | Default | Purpose |
|---|---|---|
| `APP_URL` | `http://localhost` | Base URL |
| `DB_CONNECTION` | `sqlite` | Uses `database/database.sqlite` |
| `BROADCAST_CONNECTION` | `reverb` | Real-time driver |
| `REVERB_APP_ID` / `_KEY` / `_SECRET` | (set) | **Must match the client's values** so the app can connect |
| `REVERB_HOST` / `REVERB_PORT` | `localhost` / `8080` | Where the client connects to Reverb |
| `REVERB_SERVER_HOST` / `_SERVER_PORT` | `0.0.0.0` / `8080` | Where `reverb:start` listens |
| `ATTENDANCE_RETENTION_DAYS` | `400` | `attendance:purge-taps` deletes taps older than this |
| `GATE_OFFLINE_AFTER_MINUTES` | `5` | `attendance:sweep-gates` marks a gate offline after this silence |
| `ATTENDANCE_ABSENT_CHECK_TIME` | `09:00` | Daily time the absence job runs |
| `FCM_ENABLED` | `false` | Background push. `false` = payloads are logged, not sent |
| `FCM_PROJECT_ID` / `FCM_CREDENTIALS` | empty | Firebase project id + path to the service-account JSON (only used when `FCM_ENABLED=true`) |

---

## 3. Server — running

For a full local stack, run these **four** processes (separate terminals):

```bash
php artisan serve --port=8004        # HTTP API + /simulator   (match TEXTBITZ_SERVER_URL)
php artisan reverb:start             # WebSocket server (port 8080)
php artisan queue:work               # FCM fan-out + push jobs
php artisan schedule:work            # gate sweep / flag-absent / purge-taps
```

The API works with just `serve`. `reverb:start` is needed for live updates,
`queue:work` for push notifications, `schedule:work` for the maintenance jobs.

Health check: `GET http://127.0.0.1:8004/api/health` → `{"alive":true}`.

---

## 4. Server — seeded data & the demo account

`php artisan migrate --seed` (or `migrate:fresh --seed`) runs `DatabaseSeeder`, which
creates:

- **School** "Sampaguita National High School" (`Asia/Manila`, cutoff `07:45`) — prints its **ingest token** on run
- **Gates** "Main Gate" (id 1) + "Side Gate"
- **Students** Diana Reyes (`RFID-DIANA-01`), Marco Reyes (`RFID-MARCO-01`), Sofia Cruz, Liam Santos
- **Guardian login** `+639171234567` / `password` — linked to Diana + Marco
- **Student login** `+639170000002` / `password` — Marco's self-login
- **Link code** `GATE-SOFIA` — a pending guardian↔student link for Sofia
- ~3 weeks of weekday tap history for Diana + Marco (`seedTapHistory`, via `RecordTap::backfill`)

The seeder echoes the ingest token, logins and link code — copy the token for §5/§6.

---

## 5. Server — the turnstile / ingest endpoint

Real hardware (or a bridge) POSTs to:

```
POST /api/ingest/tap
Authorization: Bearer {schools.ingest_token}
Content-Type: application/x-www-form-urlencoded

rfid_uid=RFID-DIANA-01&gate_id=1&timestamp=2026-08-28T07:42:00+08:00   # timestamp optional
```

The server: resolves `rfid_uid` → student, toggles IN/OUT from the last tap that
school-day, computes `is_late` against the school cutoff (school timezone),
persists the `TapEvent` **in UTC**, broadcasts `TapRecorded` on
`private-student.{id}` + `private-gate.{id}`, and enqueues one push per linked
recipient (respecting their `notification_preferences`).

curl example:

```bash
curl -X POST http://127.0.0.1:8004/api/ingest/tap \
  -H "Authorization: Bearer <INGEST_TOKEN>" -H "Accept: application/json" \
  -d "rfid_uid=RFID-DIANA-01&gate_id=1"
# → 201  {"tap":{"direction":"in","is_late":true,...}}   (repeat → "out")
```

---

## 6. Server — the turnstile simulator (dev)

Simulate taps without hardware. Everything runs through the same `RecordTap` path
as real ingest.

### Commands

```bash
# Back-fill weeks of history for every student in the school
php artisan gate:simulate --scenario=mixed --days=14 --fresh
#   --scenario  mixed | on-time | late | absent | perfect
#   --days      how many weekdays back
#   --fresh     clear existing tap_events for the targeted students first
#   --student=3 --student=4   limit to specific student ids
#   --school= --gate= --seed=

# Fire ONE live tap through the full pipeline (broadcast + push)
php artisan gate:tap                       # first student, auto direction, now
php artisan gate:tap RFID-DIANA-01 --in --late
php artisan gate:tap RFID-MARCO-01 --out
#   --in / --out       force direction
#   --on-time / --late  shift the timestamp relative to today's cutoff
```

### Web panel — `http://127.0.0.1:8004/simulator`

Only mounted when `APP_DEBUG=true`. Pick a school / gate / student, then:

- **Live tap** buttons — Arrive on-time, Arrive late, Dismiss, Tap now
- **Back-fill** — scenario + number of weekdays + all-students + clear-first
- **Flag absents (today)**, **Clear taps**
- **Day records vs Derived alerts** — two tables side by side that show how the
  Alerts feed is computed (see §7)

---

## 7. How the Alerts feed works

There is **no `alerts` table**. `GET /api/students/{id}/alerts` calls
`AlertBuilder::forStudent()`, which recomputes the feed from `tap_events` on every
request:

1. `DayRecordBuilder` folds taps into one record per school day (last 90 days).
2. Each **late** day → a `late` alert. Each **absent** day (a past weekday with zero
   taps — absence is *derived*, never stored) → an `absent` alert.
3. One `weekly_summary` alert per week ("on time N of M school days").
4. Sorted newest-first, paginated 15/page (`?page=`).

Change the taps (simulator, ingest, or `gate:simulate`) and the alerts change with
them. The `/simulator` panel shows the raw day-records next to the derived alerts so
the mapping is visible.

---

## 8. Server — scheduled jobs

Driven by `php artisan schedule:work` (see `routes/console.php`):

| Command | Cadence | Effect |
|---|---|---|
| `attendance:sweep-gates` | every minute | Gate → `offline` if `last_seen_at` older than `GATE_OFFLINE_AFTER_MINUTES`; broadcasts `GateStatusChanged` |
| `attendance:flag-absent` | daily at `ATTENDANCE_ABSENT_CHECK_TIME` | Enrolled students with 0 taps today → `StudentMarkedAbsent` broadcast + push |
| `attendance:purge-taps` | daily 01:30 | Delete `tap_events` older than `ATTENDANCE_RETENTION_DAYS` |

Run any of them by hand: `php artisan attendance:flag-absent`.

---

## 9. Server — API surface

**Ingest** (school ingest-token auth):
`POST /api/ingest/tap`

**Auth** (public): `POST /api/register`, `POST /api/login` — phone-number + password,
returns a Sanctum token. `POST /api/logout` (token auth).

**Mobile** (`auth:sanctum`):

| Method | Route | |
|---|---|---|
| GET | `/api/me` | account + roles + guardian/student profiles + linked students |
| GET | `/api/students/{id}/status` | today's timeline + derived presence |
| GET | `/api/students/{id}/history?month=YYYY-MM` | one record per day for a month |
| GET | `/api/students/{id}/alerts?page=` | paginated derived alerts (§7) |
| GET / PUT | `/api/notification-preferences` | per-role toggles |
| POST | `/api/link/request` | complete a guardian↔student link with a school code |
| POST / DELETE | `/api/device-tokens` | register / drop an FCM token |

`/students/{id}/*` are guarded by `StudentPolicy` — you can only read a student you're
linked to.

**Real-time channels:** `private-student.{id}` (`TapRecorded`, `StudentMarkedAbsent`,
`GuardianLinkedToStudent`), `private-gate.{id}` (`GateStatusChanged`), `private-user.{id}`.

---

## 10. Client — setup

Dependencies are already vendored in this checkout. From scratch:

```bash
cd textbitz_gate/client

composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # schema + demo data when APP_DEMO_MODE=true (§12)
```

### Key `.env` values

| Var | Default | Purpose |
|---|---|---|
| `TEXTBITZ_SERVER_URL` | `http://127.0.0.1:8004` | Where the app reaches the server — **must match the running server** |
| `DB_CONNECTION` | `sqlite` | Local offline cache DB |
| `BROADCAST_CONNECTION` | `reverb` | Real-time |
| `REVERB_APP_ID` / `_KEY` / `_SECRET` / `_HOST` / `_PORT` | (set) | **Must match the server's Reverb app** |
| `VITE_REVERB_*` | mirror of the above | Passed to the browser bundle |
| `APP_DEMO_MODE` / `VITE_APP_DEMO_MODE` | `true` | Runs `DemoSeeder`, shows the login-hint box |
| `FCM_ENABLED` / `FCM_SENDER_ID` | `false` / empty | Device-token registration (needs the paid `nativephp/mobile-firebase` plugin) |
| `NATIVEPHP_APP_ID` | `com.rightapps.textbitzgate` | Android bundle id |

---

## 11. Client — running

### Browser / desktop dev

```bash
composer run dev        # runs: php artisan serve + queue:listen + npm run dev
```

Then open the served URL. For the offline-sync machinery also run
`php artisan schedule:work` (connectivity heartbeat + `PullTapsFromServer`).

### Android (NativePHP Mobile)

```bash
npm run build -- --mode=android      # tell the user to run these — never auto-run
php artisan native:run android       # build + launch on emulator/device
php artisan native:watch             # hot reload during development
```

Set `TEXTBITZ_SERVER_URL` to a host the emulator/device can reach (e.g. your LAN IP,
or `http://10.0.2.2:8004` for the Android emulator → host).

### Production build (assets only)

```bash
npm run build
```

---

## 12. Client — demo mode

With `APP_DEMO_MODE=true`:

- `php artisan migrate:fresh --seed` runs **`DemoSeeder`**, or run it directly:
  `php artisan db:seed --class=DemoSeeder`
- Also reachable in-app via `POST /make-demo-account` (demo-mode only)

`DemoSeeder` writes straight into the local SQLite cache (no server needed):

- Guardian login **`09171234567` / `password`** (normalises to `+639171234567`)
- Two children — **Diana Reyes** (punctual) and **Marco Reyes** (often late)
- Two gates
- **~35 weekdays** of on-time / late / absent tap history (per-child bias) so the
  History calendar colours in and the Alerts feed shows late / absent / weekly items
- Default guardian notification preferences

`LogIn.vue` shows the credentials hint when `VITE_APP_DEMO_MODE=true`.

---

## 13. Client — the four screens

| Screen | What it shows |
|---|---|
| **Home** | Greeting banner, child switcher, presence hero (At School / Left / Not arrived), today's timeline, recent activity feed |
| **History** | Month calendar (on-time / late / absent colours) + daily IN/OUT records |
| **Alerts** | Infinite-scroll feed of late arrivals, absences, weekly summaries |
| **Settings** | Personal info, Security, Linked children (+ add via link code), Preferences (dark mode + notification toggles, in a bottom modal), School contact, Sign out |

---

## 14. Client — offline behaviour

The client is a full Laravel app on the device, so it keeps working without network:

- **Reads** (`Home`, `History`) fall back to building responses from the local
  `tap_events` cache, tagged `"stale": true`. `Alerts` has no offline build — it's
  empty until reconnect.
- **Writes** (notification prefs, link requests) save locally with
  `sync_status = pending` and flush via queued jobs on reconnect.
- `ServerConnectivityService` pings `/api/health` (cached 15s); the minute heartbeat
  fires `ServerConnectionRestored` on offline→online, which runs
  `PullTapsFromServer` + flushes pending writes.
- Login is offline-tolerant: if the server is unreachable, the credential is cached
  and the Sanctum token is bridged on the next reconnect.

Requires `php artisan queue:work` + `schedule:work` running on the device.

---

## 15. Testing

```bash
# server
cd textbitz_gate/server && php artisan test        # 39 passing

# client
cd textbitz_gate/client && php artisan test
#   TextBitz Gate suites pass:
#     tests/Feature/Gate/ClientApiTest.php      (5)
#     tests/Feature/Gate/DemoSeederTest.php     (2)
#   5 pre-existing failures in the stock Breeze Auth/Profile suite are unrelated
#   (they post `email` where this app requires `phone_number`, and reference
#   route names that predate the conversion).
```

---

## 16. Common issues

| Symptom | Fix |
|---|---|
| Client shows no data / "stale" everywhere | Server not reachable at `TEXTBITZ_SERVER_URL`, or no `remote_token` yet (log in while the server is up) |
| No live updates | `php artisan reverb:start` not running, or client/server `REVERB_APP_*` mismatch |
| No push notifications | Expected when `FCM_ENABLED=false` — the payload is written to the log instead. Also needs `queue:work` |
| `/simulator` is 404 | `APP_DEBUG=false` — the routes are only mounted in debug |
| Ingest returns 401 | Wrong/blank `Authorization: Bearer <ingest_token>` — get the token from the seeder output or `schools` table |
| Ingest returns 422 `unknown_uid` | The `rfid_uid` isn't a student in that school |
| History times look shifted | Fixed — taps are stored in UTC and rendered in the school timezone. Re-run `migrate:fresh --seed` if you have old rows |
| Android emulator can't reach the server | Use `http://10.0.2.2:8004` (emulator → host) or your LAN IP, not `127.0.0.1` |
