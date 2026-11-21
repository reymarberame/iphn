# BHN — Bukidnon Hospital Network

Hospital coordination platform for the **Bukidnon Hospital Network (BHN)**. BHN helps participating Bukidnon hospitals publish bed availability, refer patients between facilities, reserve receiving beds, and coordinate transfers — without storing clinical records.

Built with **Laravel 12 + Inertia.js + React 19 (plain JSX)**, Tailwind CSS v4, **Supabase Postgres/Storage**, and **Laravel Reverb**.

## Scope

BHN is deployed for **Bukidnon province only**. The seeded geography covers Region X (Northern Mindanao), the Province of Bukidnon, and all 22 Bukidnon LGUs — 20 municipalities plus the component cities of Malaybalay and Valencia.

BHN intentionally does **not** include:

- Electronic medical records or clinical documentation
- Billing, PhilHealth, or claims processing
- Automated bed sensors — availability is reported manually by hospital staff
- Public registration — accounts are provisioned by administrators

Patient-linked coordination data is limited to the minimum needed to move a patient between hospitals: a local patient reference, name, age/sex, urgency, service requested, referral reason, contact details, status history, transfer logistics, and optional referral documents.

## Features

**Hospital directory & bed availability**
- Network-wide hospital directory with geographic, ownership, service, and category filters
- Bed inventory organised by ward → room → bed, with per-bed status
- Availability is always derived from individual bed statuses (available, occupied, reserved, cleaning, out of service) — never entered as a total
- Freshness indicators driven by the latest bed status report, configurable by network administrators (default fresh ≤ 15 min, aging ≤ 60 min, stale > 60 min)
- Optimistic concurrency on bed status updates (`expected_updated_at`) with HTTP 409 on stale writes

**Referrals & transfers**
- Draft → submitted → under review → accepted/declined → transfer scheduled → in transit → arrived → completed, with cancellation and expiry paths
- Server-side transition matrix; the UI only mirrors it
- Accepting a referral is separate from browsing the receiving hospital's availability
- Bed reservations only for accepted referrals; reserved beds cannot be double-booked and out-of-service beds cannot be reserved
- Reservations expire automatically after a configurable TTL and release on cancellation
- Transfer scheduling, departure, arrival, and completion by the appropriate hospital
- Private referral-document storage on the `local` disk in development and Supabase Storage in production

**Network administration**
- Admin-provisioned users, roles, and hospital memberships
- Network or hospital scopes for administrators
- Hospital administrators can edit their own facility's profile — contact details, address, licensed capacity, services, and notes — directly from the hospital directory page; network-controlled fields stay locked
- Audit log for administrative and workflow actions, plus login history
- Operational reports and CSV exports for hospital staff; aggregate-only reporting for the super administrator
- In-app notifications for referral, reservation, and stale-report events

**Real-time direct messaging**
- 1-to-1 direct messages between any active BHN users, delivered instantly over Laravel Reverb WebSockets
- Private per-user channels keep messages, unread badges, read receipts, edits, and deletions in sync on every page
- Senders can edit or delete their own messages; deletions leave a "This message was deleted" tombstone for both participants
- Unread counts are derived from each participant's read cursor; opening a thread marks it read, and deleted messages are excluded
- Chat history persists in Postgres, and the UI warns users not to share patient-identifiable information

**REST API**
- Versioned under `/api/v1`, authenticated with Sanctum bearer tokens
- Consistent JSON envelope, validation errors, pagination metadata, and HTTP status mapping
- Same policies and data isolation as the web application

## Tech stack

| Layer | Choice |
| --- | --- |
| Backend | PHP 8.4, Laravel 12, Sanctum |
| Frontend | Inertia.js 3, React 19, JSX, Tailwind CSS v4, Lucide icons |
| Build | Vite 6 |
| Database | Supabase Postgres (MySQL remains supported for local development) |
| Storage | Supabase Storage (S3 protocol) in production; private local disk in development |
| Hosting | Vercel (Laravel via `vercel-php` serverless function) + Railway (Reverb + scheduler) |
| Auth | Session-based web login + Sanctum personal access tokens for API |
| Realtime | Laravel Reverb 1, Laravel Echo, pusher-js |
| Tests | PHPUnit 11 / Laravel test runner |

## Getting started

### Requirements

- PHP 8.4+ with PDO MySQL (local) and PDO PostgreSQL (Supabase), Composer 2
- Node.js 22+ and npm
- MySQL 8 for the local workflow, or a Supabase project
- Laragon on Windows can enable `pdo_pgsql` per command with `php -d extension=pdo_pgsql`

### Installation

```bash
composer install
npm install
cp .env.example .env   # if starting from a fresh clone
php artisan key:generate
```

### Laragon (Windows)

The project runs on Laragon's bundled Apache, PHP, and MySQL with no code changes:

1. Clone/copy the project to `C:\laragon\www\emr`.
2. Start **Apache** and **MySQL** from Laragon. The auto virtual host already points at `public/`, so the app is served at **http://emr.test**.
3. Create the database once (Laragon can also do this for you):

   ```bash
   mysql -uroot -e "CREATE DATABASE IF NOT EXISTS bhn_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

4. Set `APP_URL=http://emr.test` in `.env`, then install, migrate, and build:

   ```bash
   composer install
   npm install
   php artisan migrate:fresh --seed
   npm run build
   ```

5. Start Reverb in a separate terminal so chat and unread badges work in real time:

   ```bash
   php artisan reverb:start --host=0.0.0.0 --port=8080
   ```

6. Open **http://emr.test** and sign in with a demo account, for example `hospitaladmin@bhn.test` / `Password@123`.

> In PowerShell, use `npm.cmd` if script execution is disabled (`npm.cmd run build`, `npm.cmd install`).

`.env.example` is preconfigured for the production Supabase/Railway setup. For the Laragon workflow above, override the database block locally:

```dotenv
APP_NAME=BHN
APP_URL=http://emr.test
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bhn_db
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=emr.test
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_CLIENT_HOST=emr.test
REVERB_CLIENT_PORT=8080
REVERB_CLIENT_SCHEME=http

BHN_DEMO_PASSWORD=Password@123
BHN_SHOW_DEMO_ACCOUNTS=false
SANCTUM_EXPIRATION_MINUTES=480
```

> Generate unique Reverb credentials per deployment and keep `REVERB_APP_SECRET` server-side. Reverb settings are shared with the browser at runtime through Inertia, so changing them no longer requires rebuilding assets.

Create the schema and demo data:

```bash
php artisan migrate:fresh --seed
npm run build
```

For local development:

```bash
php artisan serve          # or: php -S 127.0.0.1:8010 -t public
php artisan reverb:start --host=0.0.0.0 --port=8080
npm run dev
```

The seeded environment contains Region X, Bukidnon, all 22 Bukidnon LGUs, eight representative hospitals, 272 beds, sample referrals/transfers, and the demo accounts below.

> **Demo data notice.** Facility names, profiles, contact details, bed layouts, and referral records are representative demonstration data for the Bukidnon deployment. Replace them with official facility records before production use.

## Vercel + Supabase + Railway deployment

The HTTP application runs on **Vercel** through `api/index.php` and the community `vercel-php@0.8.0` runtime; `vercel.json` serves compiled assets and routes every other request to Laravel. Long-running processes still live on **Railway** (one Docker service running Reverb and `schedule:work`), and data lives in **Supabase Postgres/Storage**.

Browser Reverb settings are shared at runtime and point at the Railway HTTPS domain (`REVERB_CLIENT_HOST`, `REVERB_CLIENT_PORT=443`, `REVERB_CLIENT_SCHEME=https`), while server-side broadcasting uses `REVERB_HOST` with the same public host. Railway's Apache proxies both `/app` (WebSockets) and `/apps` (Pusher HTTP events) to Reverb.

### Vercel

1. Create a Vercel project with framework preset "Other" and no build/install/output settings (the PHP runtime installs Composer dependencies itself). Keep `package.json`, `package-lock.json`, and `vite.config.js` in `.vercelignore` so Vercel does not auto-detect Vite.
2. Build assets locally (`npm run build`) before deploying so `public/build` is uploaded.
3. Set the production environment variables from `.env.example`, including `APP_URL=https://<your-vercel-domain>` and the `REVERB_*`, `DB_*`, and `SUPABASE_*` blocks.
4. Deploy with `vercel --prod --yes`.

### Railway (Reverb + scheduler only)

The Docker image and `railway.toml` are still used for the always-on Reverb and scheduler service. Set the same `DB_*`, `SUPABASE_*`, and `REVERB_*` variables there, with `REVERB_SERVER_HOST=127.0.0.1`, `REVERB_SERVER_PORT=8081`, `REVERB_HOST=127.0.0.1`, and `REVERB_PORT=8081`. Deploy with `railway up --detach`.

### Supabase project

1. Create a project and copy the **Session Pooler** connection details (port `5432`). Avoid the transaction pooler for migrations and transactional workflows.
2. Create a **private** Storage bucket (default name `bhn-storage`).
3. Create **S3 access keys** under Project Settings → Storage → S3 access keys.
4. Fill the `DB_*` and `SUPABASE_*` values from `.env.example`. Use `DB_SSLMODE=require`.

### Migrate schema and existing data

Run this from the machine that holds the current MySQL data. On Laragon, prefix PHP with `-d extension=pdo_pgsql`.

```bash
php artisan migrate --force
php artisan db:copy-to-postgres --fresh --documents
```

The command copies business tables in foreign-key order, converts MySQL booleans and zero dates, resets Postgres sequences, uploads referral documents when `--documents` is passed, and verifies source/target row counts. Framework tables (`migrations`, `sessions`, `cache`, `jobs`, tokens) are rebuilt on the destination rather than copied.

A final migration revokes `anon`/`authenticated` privileges on the `public` schema so Supabase's Data API cannot read application tables. Laravel connects as the table owner and is unaffected.

## Seeded hospital network

| Code | Facility | LGU | Level | Ownership | Status |
| --- | --- | --- | --- | --- | --- |
| `BPMC-1013` | Bukidnon Provincial Medical Center | Malaybalay City | Tertiary | Government | Operational |
| `BDH-1014` | Bukidnon Doctors Hospital | Malaybalay City | Tertiary | Private | Operational |
| `VCMC-1015` | Valencia City Medical Center | Valencia City | Secondary | Government | Operational |
| `VDH-1016` | Valencia Doctors Hospital | Valencia City | Secondary | Private | Operational |
| `MDH-1017` | Maramag District Hospital | Maramag | Secondary | Government | Limited |
| `DCMH-1018` | Don Carlos Municipal Hospital | Don Carlos | Secondary | Government | Operational |
| `MFCH-1019` | Manolo Fortich Community Hospital | Manolo Fortich | Secondary | Government | Operational |
| `QMH-1020` | Quezon Municipal Hospital | Quezon | Secondary | Government | Limited |

Each hospital's demo board is seeded with a different report age (5–120 minutes) so the interface exercises fresh, aging, and stale availability states. Freshness advances with real time after seeding.

BPMC is the demo referral hub; VCMC, BDH, MDH, DCMH, and QMH participate in the seeded referral and transfer flow.

## Demo accounts

All seeded accounts use the password **`Password@123`** (configurable through `BHN_DEMO_PASSWORD`). The sign-in page can list five representative shortcuts **only in local development** when `BHN_SHOW_DEMO_ACCOUNTS=true`; the panel is omitted in every other environment. The full seeded set is below.

| Email | Role | Scope |
| --- | --- | --- |
| `superadmin@bhn.test` | Super Administrator | Network |
| `hospitaladmin@bhn.test` | Hospital Administrator | BPMC |
| `bedmanager@bhn.test` | Hospital Bed Manager | BPMC |
| `referral@bhn.test` | Hospital Staff | BPMC |
| `admission@vcmc.bhn.test` | Hospital Staff | VCMC |
| `admin@bdh.bhn.test` | Hospital Administrator | BDH |
| `referral@mdh.bhn.test` | Hospital Staff | MDH |
| `referral@qmh.bhn.test` | Hospital Staff | QMH |
| `admin@dcmh.bhn.test` | Hospital Administrator | DCMH |

> The super administrator is intentionally network-only and cannot open patient-linked referral or transfer records. Sign in as a hospital user (for example `hospitaladmin@bhn.test` or `admission@vcmc.bhn.test`) to exercise the referral workflow.

## Roles and permissions

Roles are stored in `roles` / `permissions` / `role_permissions`. Network roles are attached through `user_roles`; hospital roles are attached through `hospital_user_memberships`. The seeded BHN deployment uses four roles. The schema still supports optional region/province admin scopes if a wider deployment needs them.

| Role | Scope | Key permissions |
| --- | --- | --- |
| Super Administrator | network | All permissions, network-wide administration |
| Hospital Administrator | hospital | Hospital profile, users, beds, referrals, transfers, reports |
| Hospital Bed Manager | hospital | Bed inventory and status reporting |
| Hospital Staff | hospital | Referrals, bed reservations, transfers, bed status reporting |

Hospital-scoped permission checks always require an **active membership** at that hospital. Submitted `hospital_id` / `user_id` values are never trusted; the acting user and hospital are resolved server-side.

## Patient-linked data isolation

- Referral and transfer lists are restricted to hospitals where the user holds an active membership — even if the user also has a network role.
- Individual referral/transfer views require membership at the referring or receiving hospital.
- The super administrator receives aggregate reports only; `/reports` is available, `/referrals` and `/transfers` are not.
- Individual bed records are visible only to hospital members (or scoped network users) with `beds.view`.

## Time zones and formatting

- Application timestamps use **Asia/Manila** (`APP_TIMEZONE`, default `Asia/Manila`), so report date boundaries and scheduled commands match Philippine wall-clock time.
- Web/API serialization uses ISO-8601 strings with the application offset (`+08:00`).
- The React UI formats dates in **Asia/Manila**. `datetime-local` inputs use Manila wall-clock time explicitly, so a value entered as `2026-11-17 14:30` is sent as `2026-11-17T14:30:00+08:00`.

## Testing

Tests run against a dedicated MySQL schema. Create it once:

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS bhn_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

`phpunit.xml` already points at `bhn_testing` with the local root credentials. Run:

```bash
php artisan test
```

The suite covers availability/freshness maths, referral transitions, hospital isolation, network-admin aggregate restrictions, reservation double-booking prevention, bed optimistic concurrency, API authentication/envelopes, direct-message delivery, authorization, unread counts, read receipts, sender-only editing/deletion, validation, tombstone broadcasts, hospital profile editing, demo-account gating, CSV formula-injection neutralisation, and security headers/CSP. Each test uses `RefreshDatabase`, so the testing schema is rebuilt automatically. Broadcasting is disabled in tests via `BROADCAST_CONNECTION=null`.

## Project structure

```
app/
  Http/Controllers/          Web (Inertia) controllers
  Http/Controllers/Api/      Versioned REST controllers
  Models/                    Domain models
  Policies/                  Authorization rules
  Services/                  Availability, reservations, referrals, transfers, reports, access, audit, chat
  Events/                    Broadcast events (messages, edits, deletions, read receipts)
  Http/Middleware/           Inertia shared props, active-account enforcement, security headers
config/
  bhn.php                    Network branding, demo accounts, disclaimer, freshness defaults
database/
  migrations/                Schema
  seeders/                   Bukidnon geography, access, catalog, hospitals, demo users, referrals
resources/js/
  Pages/                     Inertia page components
  Components/                Layout, navigation, UI kit
  lib/                       Formatting and helper utilities
routes/
  web.php                    Inertia routes
  api.php                    `/api/v1` routes
tests/
  Feature/                   Workflow, isolation, concurrency, API tests
  Concerns/BuildsNetwork.php Minimal network fixtures
docs/
  API.md                     REST API reference
  IMPLEMENTATION.md          Design decisions and operational notes
```

## Operational notes

- Bed counts, category breakdowns, and freshness are computed at read time from `beds` and `bed_status_history`; snapshots are generated only when explicitly requested by a network administrator.
- Referral documents are stored privately under `referral-documents/{referral_public_id}` on the disk selected by `REFERRAL_DOCUMENTS_DISK` (`local` in development, `supabase` in production) and downloaded through an authorized controller action.
- Audit records capture the actor, hospital context, subject, changed fields, IP address, and user agent.
- Branding uses the Province of Bukidnon official seal (`public/images/bukidnon-seal.jpg`), with 64 px and 180 px PNG derivatives for the favicon and Apple touch icon.
- Chat requires the Reverb server to be running (`php artisan reverb:start --host=0.0.0.0 --port=8080`). Broadcasts use `ShouldBroadcastNow`, so no queue worker is required for messaging. Open two accounts in separate browsers to exercise live delivery.
- Chat edits and deletions broadcast `message.updated` / `message.deleted` to both participants. Delete events carry refreshed per-user and per-conversation unread counts so badges stay accurate without a page reload.
- Chat policy: the UI warns users not to share patient names, medical record numbers, or other identifiable information. Direct messages are not part of the clinical record; use referral records and approved documents for patient coordination.
- Security hardening: the demo-account panel is local-only (`BHN_SHOW_DEMO_ACCOUNTS`), API routes are limited to 120 requests/min, web mutations/exports use a 20/min limiter, chat mutations use 60/min, Sanctum tokens expire after `SANCTUM_EXPIRATION_MINUTES` (default 480), and responses include CSP, `X-Frame-Options`, `X-Content-Type-Options`, and `Referrer-Policy` headers. In production, set `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and `SESSION_ENCRYPT=true`.
- The `reservations:expire` command releases overdue holds. It is scheduled every five minutes:

```php
Schedule::command('reservations:expire')->everyFiveMinutes();
```

## Further reading

- [REST API reference](docs/API.md)
- [Implementation notes](docs/IMPLEMENTATION.md)
