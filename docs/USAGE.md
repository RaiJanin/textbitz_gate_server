# TextBitz Gate — Usage Guide

How to **run the system day to day**: the school admin panel and the parent/student
mobile app. For installation, environment variables, the turnstile simulator and
the ingest API, see [`SETUP.md`](SETUP.md).

---

## The three roles

| Role | Uses | What they do |
|---|---|---|
| **School staff** (admin) | Admin panel — `https://<server>/admin` | Enrol students, issue link codes, watch gates, manage guardians |
| **Guardian** | Mobile app | Link to their child with a code, receive arrival / departure / late / absence alerts |
| **Student** (optional) | Mobile app | Same app, sees only their own attendance |

The flow, once set up:

```
Student taps RFID at a gate
   → server records IN/OUT + lateness
   → live push to the app (open) / FCM push (backgrounded)
   → guardian sees the tap on Home and gets a notification
```

The mobile app never talks to turnstile hardware — only to the server.

---

# Part 1 — The admin panel (school staff)

## 1.1 Signing in

Open **`/admin`** on the server (e.g. `http://127.0.0.1:8004/admin`). Log in with
**username and password** (the username is the account's `name`).

Admin accounts are stored in their own table (`x08`) — completely separate from
the client-app accounts in `users`. A staff member and a guardian are never the
same login.

### Creating an admin

```bash
php artisan make:filament-user \
  --name="frontoffice" --password="secret123" \
  --phone="+639171234567" --school=1
```

| Flag | Effect |
|---|---|
| `--name` | Required — the **username** (must be unique) |
| `--password` | Required |
| `--email` | Optional — for records only, not used to log in |
| `--phone` | Optional contact number (`+639XXXXXXXXX`) |
| `--school=<id>` | Scopes the admin to that one school (omit ⇒ super-admin) |

Run it with no flags to be prompted for each field, including a school picker.
Create the first super-admin this way right after deploying.

### Super-admin vs school-scoped

A **school-scoped** admin only ever sees their own school's students, link codes,
gates and guardians, and cannot open **Schools**. A **super-admin** (no school)
sees and manages everything, and picks the school when creating records.

## 1.2 Dashboard

The landing page shows live tiles:

- **Students** enrolled
- **On campus today** (and how many haven't tapped in)
- **Late arrivals today**
- **Gates online** (e.g. `2 / 2`)
- **Codes outstanding** — issued but not yet redeemed by a guardian
- **Latest turnstile activity** — a live-updating table of the most recent taps

Tiles refresh on their own every 30–60 s.

## 1.3 Students  ·  *People → Students*

The roster. Each row shows the photo, name, grade/section, RFID tag, guardian
count, and the **Link code** column:

| Link code column | Meaning |
|---|---|
| `A1B2C3D4` | A code is live for this student, not yet redeemed |
| **Linked** | A guardian is already connected; no open code |
| **None** | No guardian and no open code — needs attention |

**Add a student:** *New student* → name, grade, section, **RFID tag UID**
(scan the card straight into the field), optional photo. A school-scoped admin's
school is filled in automatically.

**Filters:** by grade, "No guardian linked yet", "Has an unredeemed code", and
(super-admin) by school.

### Issuing a link code — the core task

**One student:** click **Issue code** on the row →

- **Relationship shown to the guardian** — default `Guardian` (use `Mom`, `Dad`, …)
- **Valid for (days)** — default `30`

→ a code is minted. A notification shows the code and a **Print slip** button.

> Issuing a new code for a student **revokes any earlier unredeemed code** for
> that student, so only one is ever live.

**A whole class:** tick the students → **Issue link codes** in the toolbar → set
the relationship and validity **once** → one code per student → a single
**printable sheet** opens for all of them.

### The printed slip

A ready-to-hand-out A5 card per code, containing:

- School name and the child's name
- The **code** in large type **and** a **QR code** of it
- Step-by-step instructions for the guardian (install app → sign up →
  Settings → Link a student → enter the code)
- The expiry date

The slip page triggers the print dialog automatically. Slip up in the enrolment
packet, or send it home with the student.

## 1.4 Link codes  ·  *Access → Link codes*

Every code ever issued, newest first. Columns: code, student, status, who
redeemed it, expiry, when issued. The nav badge counts codes still outstanding.

| Status | Meaning |
|---|---|
| **Usable** | Live and unredeemed |
| **Redeemed** | A guardian has linked with it (shows who) |
| **Expired** | Past its expiry, or revoked, without being redeemed |

**Row actions:**

- **Print slip** — reprint a hand-out
- **Revoke** — kill an unredeemed code immediately (a redeemed code is left
  alone; to undo a real link, unlink the guardian instead)

**New link code** (header) — issue one by picking a student, relationship and
validity, without going through the roster.

## 1.5 Guardians  ·  *People → Guardians*

Everyone who has (or will) receive alerts. Columns: name, phone, linked children,
and whether they've installed the app yet.

**Guardian ⇄ app account are always paired:**

- When a guardian **signs up in the app**, a Guardian row appears here
  automatically (with default notification preferences).
- When **you create a guardian here** (*New guardian*), the app **login is
  created for them** — their mobile number is the username. Set an *App password*
  in the form, or leave it blank to auto-generate one (shown in a toast after
  saving, so you can pass it to the guardian).
- If the mobile number already belongs to an app account, the form blocks it —
  that person already has a guardian profile.

Other actions:

- **Edit** — fix a guardian's name / phone / email (support use)
- **Linked children** panel on the edit page — **Link a student** (attach an
  existing student with a relationship label) or **Unlink**. Use this to correct
  a mistaken link or to connect a guardian without issuing a code.
- **Filters:** "No children linked", "Has not signed up in the app"

## 1.6 Gates  ·  *Setup → Gates*

One row per turnstile. The **Gate ID** column (copyable) is what the turnstile /
bridge is configured to send as `gate_id` — together with the school's ingest
token it's all the hardware needs. Creating a gate shows its ID in a toast; it's
also on the gate's edit page.

**Online / Offline** is set automatically — a gate reads *Online* only while it's
posting taps, and `attendance:sweep-gates` flips it *Offline* after a few minutes
of silence (`GATE_OFFLINE_AFTER_MINUTES` in SETUP.md). You can rename or add a
gate; you can't set the status by hand. The table auto-refreshes every 30 s.

## 1.7 Schools  ·  *Setup → Schools*  *(super-admin only)*

One row per school: name, timezone, **attendance cutoff** (arrivals after this
local time count as late), contact details.

- **Copy ingest token** — the bearer token the gate hardware uses to post taps
- **Regenerate token** — if it leaks. **Every gate device must then be updated
  with the new token** or it can't post taps.

---

# Part 2 — The mobile app (guardians & students)

Android app. It keeps a local offline copy of your children, their taps and your
preferences, so it opens instantly and works with no signal; it syncs when a
connection returns.

## 2.1 First run

1. **Install** TextBitz Gate and open it.
2. Sign in:
   - **The school already created your account** → tap **Log in** with your
     mobile number and the password the school gave you. The app fetches your
     account from the server on that first login and works offline afterwards.
   - **Otherwise** → **Create an account** (name, mobile number `+639XXXXXXXXX`,
     password).
3. A card appears explaining attendance alerts — tap **Enable notifications** and
   allow the phone's permission prompt, or you won't get alerts. Tapped **Not
   now**? You can turn them on later from **Settings → Preferences**.

You land on **Home**, which is empty until you link a child.

## 2.2 Linking to your child

1. Get a **link code** from the school (a printed slip, or sent to you).
2. In the app go to **Settings → Linked children**.
3. Type the code into the field and tap **Link**.
4. Within a few seconds your child appears, and Home fills in.

If you're offline when you submit, the request is **queued and retried
automatically** when you reconnect — you don't need to do anything.

One account can link **several children** (a child switcher appears on every
screen). If the school also gave you a **student login** for your own child, the
same account can hold both roles.

## 2.3 Home  ·  *"Attendance at a glance"*

- **Banner** — a short greeting / what-to-do hint
- **Child switcher** (when you have more than one child)
- **Status** — has the child arrived, are they still in school, did they leave,
  were they on time
- **Timeline** — today's taps in order (IN / OUT, gate, time, late flag)
- **Recent activity** — the last several days at a glance, updating live as taps
  come in

## 2.4 History  ·  *"Monthly attendance"*

- **Month calendar** — each day colour-coded on time / late / absent / no record
- **Daily records** — first-in and last-out per day for the selected month
- Switch child and month at the top

## 2.5 Alerts  ·  *"Late arrivals, absences, summaries"*

A merged feed per child:

- **Late arrivals** — tapped IN after the school's cutoff
- **Absence flags** — no tap on a school day (raised by the server's daily check)
- **Weekly summaries** — a roll-up of the week

## 2.6 Settings

| Card | What it does |
|---|---|
| **Personal info** | Your name / contact details |
| **Security** | Change password |
| **Linked children** | The link-code field (2.2) and your current children |
| **Preferences** | Opens the preferences sheet (below) |
| **School contact** | The school's phone / email, once a child is linked |
| **Logout** | Sign out |

### Notification preferences

Tap **Preferences** to open the bottom sheet:

| Toggle | Notifies you when… |
|---|---|
| **Arrival** | Your child taps IN |
| **Departure** | Your child taps OUT |
| **Late alert** | Your child taps IN after the cutoff |
| **Weekly summary** | The weekly roll-up is ready |

Also here: **Dark mode**. Changes are saved to the server; if you're offline they
apply locally now and sync later.

## 2.7 Offline behaviour

- **Reads** (Home / History / Alerts) come from the on-device cache first, then
  refresh from the server when possible.
- **Writes** — only two things go back to the server: **preference changes** and
  **link-code requests**. Both are queued while offline and flushed on reconnect.
- Live taps arrive over the realtime channel while the app is open; when it's
  backgrounded or closed they arrive as a push notification (once FCM is
  configured — see SETUP.md).

## 2.8 Demo mode

If the app was built with `APP_DEMO_MODE=true` (off by default), the first launch
loads a self-contained demo dataset — a guardian with two children and a few
weeks of history — so every screen has something to show without a server. The
demo credentials are printed by the client seeder; see the client repo.

Logging into a **real** account clears the demo data automatically. Leave
`APP_DEMO_MODE` off for any real build.

---

# Part 3 — Onboarding a class, end to end

1. **Enrol the students** — *People → Students → New student* for each child, or
   already imported. Make sure every child's **RFID UID** matches their card.
2. **Issue codes in bulk** — select the class → **Issue link codes** → relationship
   `Guardian`, validity `30` days → the printable sheet opens.
3. **Print & distribute** — one slip per child, sent home or in the enrolment pack.
4. **Parents link** — each guardian installs the app, signs up, and enters their
   code under *Settings → Linked children*.
5. **Watch it land** — as parents redeem, the **Link codes** screen flips rows to
   **Redeemed** and the dashboard's "Codes outstanding" drops.
6. **First taps** — once students start tapping, guardians get live notifications
   and their Home / History / Alerts fill in.

To check a single family later: *People → Guardians*, open the guardian, and use
the **Linked children** panel to see or fix their links.

---

# Troubleshooting

| Symptom | Fix |
|---|---|
| **Can't log into `/admin`** | Admin accounts live in the `x08` table, not `users`, and sign in by **username** (the `name`). Create one with `php artisan make:filament-user`; on a fresh deploy there are none until you do. |
| **A staff member sees no students / can't open Schools** | They're school-scoped and that's expected. A super-admin has no `school_id`. |
| **"That link code is invalid or has expired"** in the app | The code was already redeemed, revoked, or past its expiry. Issue a fresh one from *People → Students → Issue code* (this also revokes any stale one). |
| **Parent linked the wrong child** | *People → Guardians → open guardian → Linked children → Unlink*, then issue the correct code. |
| **Gate shows Offline** | No heartbeat from that turnstile for several minutes — check the device and its ingest token. Regenerating a school's token invalidates every gate until each is updated. |
| **App shows demo children on a real account** | Fixed automatically on login; if stuck, reinstall or clear the app's data. Turn off `APP_DEMO_MODE` for real use. |
| **No notifications on the phone** | Open **Settings → Preferences** in the app — if the banner says notifications are off, tap **Open settings** (it takes you straight to the phone's notification screen for TextBitz Gate; if it can't, it shows the manual steps). Keep at least one preference toggle on, and confirm FCM is configured on the server (SETUP.md). While the app is open, taps still appear live without push. |
| **App works but shows no data** | It needs one sync while online after linking. Open it with a connection; Home refreshes within a minute. |

---

*Setup, environment variables, the ingest API and the turnstile simulator:*
[`SETUP.md`](SETUP.md)
