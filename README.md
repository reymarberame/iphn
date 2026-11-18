# BHN — Bukidnon Hospital Network

Hospital coordination platform for the **Bukidnon Hospital Network (BHN)**. BHN helps participating Bukidnon hospitals publish bed availability, refer patients between facilities, reserve receiving beds, and coordinate transfers — without storing clinical records.

Built with **Laravel 12 + Inertia.js + React 19 (plain JSX)**, Tailwind CSS v4, and MySQL.

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
- Private referral-document storage on the `local` disk

**Network administration**
- Admin-provisioned users, roles, and hospital memberships
- Network / region / province scopes for administrators
- Audit log for administrative and workflow actions, plus login history
- Operational reports and CSV exports for hospital staff; aggregate-only reporting for network administrators
- In-app notifications for referral, reservation, and stale-report events

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
| Database | MySQL 8 (`bhn_db`, tests use `bhn_testing`) |
| Auth | Session-based web login + Sanctum personal access tokens for API |
| Tests | PHPUnit 11 / Laravel test runner |

## Getting started

### Requirements

- PHP 8.4+ with PDO MySQL, Composer 2
- Node.js 22+ and npm
- MySQL 8

### Installation

```bash
composer install
npm install
cp .env.example .env   # if starting from a fresh clone
php artisan key:generate
```

`.env.example` is already preconfigured for BHN:

```dotenv
APP_NAME=BHN
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bhn_db
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

BHN_DEMO_PASSWORD=Password@123
```

Create the schema and demo data:

```bash
php artisan migrate:fresh --seed
npm run build
```

For local development:

```bash
php artisan serve          # or: php -S 127.0.0.1:8010 -t public
npm run dev
```

The seeded environment contains Region X, Bukidnon, all 22 Bukidnon LGUs, eight representative hospitals, 272 beds, sample referrals/transfers, and the demo accounts below.

> **Demo data notice.** Facility names, profiles, contact details, bed layouts, and referral records are representative demonstration data for the Bukidnon deployment. Replace them with official facility records before production use.

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

All seeded accounts use the password **`Password@123`** (configurable through `BHN_DEMO_PASSWORD`).

| Email | Role | Scope |
| --- | --- | --- |
| `superadmin@bhn.test` | Super Administrator | Network |
| `network@bhn.test` | Network Administrator | Network |
| `region10@bhn.test` | Regional Administrator | Region X |
| `province@bhn.test` | Provincial Administrator | Bukidnon |
| `hospitaladmin@bhn.test` | Hospital Administrator | BPMC |
| `bedmanager@bhn.test` | Hospital Bed Manager | BPMC |
| `erstaff@bhn.test` | ER Staff | BPMC |
| `referral@bhn.test` | Referral Coordinator | BPMC |
| `admission@vcmc.bhn.test` | Admission Staff | VCMC |
| `coordinator@vcmc.bhn.test` | Referral Coordinator | VCMC |
| `bedmanager@vcmc.bhn.test` | Hospital Bed Manager | VCMC |
| `admin@bdh.bhn.test` | Hospital Administrator | BDH |
| `referral@mdh.bhn.test` | Referral Coordinator | MDH |
| `referral@qmh.bhn.test` | Referral Coordinator | QMH |
| `admin@dcmh.bhn.test` | Hospital Administrator | DCMH |
| `admin@mfch.bhn.test` | Hospital Administrator | MFCH |
| `admin@vdh.bhn.test` | Hospital Administrator | VDH |

> Network administrators without a hospital membership cannot open patient-linked referral or transfer records. The superadmin is intentionally network-only; sign in as a hospital user (for example `hospitaladmin@bhn.test` or `admission@vcmc.bhn.test`) to exercise the referral workflow.

## Roles and permissions

Roles are stored in `roles` / `permissions` / `role_permissions`. Network, region, and province roles are attached through `user_roles`; hospital roles are attached through `hospital_user_memberships`. This keeps a user's hospital assignment and scope explicit.

| Role | Scope | Key permissions |
| --- | --- | --- |
| Super Administrator | network | All permissions |
| Network Administrator | network | Hospitals, beds view, aggregate reports, users, audit, settings |
| Regional Administrator | region | Hospitals and users within region, aggregate reporting |
| Provincial Administrator | province | Hospitals and users within province, aggregate reporting |
| Hospital Administrator | hospital | Hospital profile, users, beds, referrals, transfers, reports |
| Hospital Bed Manager | hospital | Bed inventory and status reporting |
| ER Staff | hospital | Bed status reporting |
| Referral Coordinator | hospital | Referrals, bed reservations, transfers |
| Admission Staff | hospital | Incoming referrals, bed reservations |

Hospital-scoped permission checks always require an **active membership** at that hospital. Submitted `hospital_id` / `user_id` values are never trusted; the acting user and hospital are resolved server-side.

## Patient-linked data isolation

- Referral and transfer lists are restricted to hospitals where the user holds an active membership — even if the user also has a network role.
- Individual referral/transfer views require membership at the referring or receiving hospital.
- Network administrators receive aggregate reports only; `/reports` is available, `/referrals` and `/transfers` are not.
- Individual bed records are visible only to hospital members (or scoped network users) with `beds.view`.

## Time zones and formatting

- All timestamps are stored in **UTC**.
- Web/API serialization uses ISO-8601 UTC strings.
- The React UI formats dates in **Asia/Manila**. `datetime-local` inputs are converted to/from Manila wall-clock time explicitly, so a value entered as `2026-11-17 14:30` is sent as `2026-11-17T14:30:00+08:00`.

## Testing

Tests run against a dedicated MySQL schema. Create it once:

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS bhn_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

`phpunit.xml` already points at `bhn_testing` with the local root credentials. Run:

```bash
php artisan test
```

The suite covers availability/freshness maths, referral transitions, hospital isolation, network-admin aggregate restrictions, reservation double-booking prevention, bed optimistic concurrency, and API authentication/envelopes. Each test uses `RefreshDatabase`, so the testing schema is rebuilt automatically.

## Project structure

```
app/
  Http/Controllers/          Web (Inertia) controllers
  Http/Controllers/Api/      Versioned REST controllers
  Models/                    Domain models
  Policies/                  Authorization rules
  Services/                  Availability, reservations, referrals, transfers, reports, access, audit
  Http/Middleware/           Inertia shared props, active-account enforcement
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
- Referral documents are stored privately under `storage/app/private/referral-documents/{referral_public_id}` and downloaded through an authorized controller action.
- Audit records capture the actor, hospital context, subject, changed fields, IP address, and user agent.
- The `reservations:expire` command releases overdue holds. It is scheduled every five minutes:

```php
Schedule::command('reservations:expire')->everyFiveMinutes();
```

## Further reading

- [REST API reference](docs/API.md)
- [Implementation notes](docs/IMPLEMENTATION.md)
