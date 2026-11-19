# Implementation notes

This document explains how BHN's main rules are implemented and why certain design decisions were made.

## 1. Scope and assumptions

BHN is the **Bukidnon Hospital Network**, deployed for Bukidnon province only. The geography model keeps regions and provinces for reporting and administrative scopes, but `GeographySeeder` seeds only Region X (Northern Mindanao), the Province of Bukidnon, and its 22 LGUs (20 municipalities and the cities of Malaybalay and Valencia). The eight seeded hospitals are representative demonstration facilities pending official records.

BHN is a **coordination** system, not a clinical or billing system. It deliberately stores only the minimum patient-linked data required to route a referral:

- local patient reference and display name
- age, sex, urgency
- requested service and referral reason
- contact person/phone
- status history, transfer logistics, and optional documents

No diagnoses, notes, results, prescriptions, or billing records are stored. Bed availability is always manually reported; the system never infers availability from admissions or sensors.

The SRS recommended server-rendered Blade, but the implementation uses **Laravel + Inertia.js + React (JSX)** as requested. Inertia keeps a single Laravel routing/authorization layer while providing a modern React UI.

## 2. Data model

Core tables:

- `regions` → `provinces` → `municipalities` → `hospitals`
- `roles`, `permissions`, `role_permissions`
- `user_roles` for network-scoped roles
- `hospital_user_memberships` for hospital roles and active hospital assignment
- `admin_scopes` for optional region/province administrator bounds
- `wards` → `rooms` → `beds`
- `bed_categories`, `bed_status_history`, `bed_availability_snapshots`
- `bed_reservations`
- `referrals`, `referral_status_history`, `referral_documents`
- `transfer_records`
- `notifications`, `audit_logs`, `login_logs`, `system_settings`

`beds.hospital_id` is denormalised from `rooms.ward.hospital_id` so hospital-scoped queries and the unique `(hospital_id, bed_code)` constraint stay simple. Inventory creation validates that the room belongs to the selected hospital.

Referrals have both a numeric route key and a ULID `public_id`. The ULID is used for private document folders so storage paths do not expose sequential IDs.

## 3. Authorization model

### Role attachment

| Role scope | Storage |
| --- | --- |
| network (seeded: Super Administrator) | `user_roles` |
| region / province (optional, not seeded) | `user_roles` + `admin_scopes` |
| hospital | `hospital_user_memberships` (status `active`) |

`HospitalAccessService` is the single authority for scope questions:

- `hasPermission()` — any global role or active hospital membership grants the permission
- `hasActiveMembership()` — explicit active membership at a hospital
- `managementHospitalIds()` — hospitals a user may administer (scope hospitals for network users; memberships otherwise)
- `patientLinkedHospitalIds()` — **memberships only**, used for referral/transfer lists
- `canViewReferral()` / `canRespondToReferral()` — active membership at a participating hospital plus the relevant permission
- `referralAbilities()` — serialisable action flags consumed by the UI

### Key isolation rules

1. **Patient-linked records are membership-scoped.** A network-scoped user with a membership at Hospital A still sees only Hospital A's referrals/transfers, not every hospital in their geographic scope. This prevents aggregate roles from becoming back-door access to patient-linked data.
2. **Network-scoped users without memberships** receive `403` on `/referrals`, `/transfers`, and their API counterparts, but retain aggregate reports.
3. **Individual bed records** require hospital membership or a scoped network role with `beds.view` (`BedPolicy@view`). The directory and aggregate availability remain browsable to hospital staff so they can identify a receiving facility.
4. **Workflow actions** re-check hospital membership and role permission inside the service layer; controller policies are not the only guard.
5. **Submitted IDs are not trusted.** The acting user comes from the authenticated session/token. Hospital IDs on actions are validated against the user's memberships or resolved from the record being acted on.

### Network scopes

The seeded BHN deployment uses a single network role (Super Administrator) with no geographic restriction. Region/province roles are still supported by the schema: if created, they may manage users and hospital profiles inside `admin_scopes`, and `applyGeographicScope()` turns those scopes into hospital query constraints. An empty scope for a network user means network scope (except for superadmin-only or patient-linked checks, which remain membership-based).

## 4. Bed availability and freshness

`BedAvailabilityService` computes every number at read time:

- `statusCounts()` groups active beds by status.
- `operational = total − out_of_service`.
- Category breakdowns group by `bed_categories`.
- Freshness uses the latest `bed_status_history.changed_at` for the hospital (or for a category where relevant):
  - `fresh` when age ≤ `freshness_fresh_minutes` (default 15)
  - `aging` when age ≤ `freshness_aging_minutes` (default 60)
  - `stale` when older
  - `unknown` when no report exists

Thresholds are stored in `system_settings` and editable only by the super administrator. Snapshots are **not** the source of live availability; `POST /api/v1/availability/snapshots` writes historical `bed_availability_snapshots` for reporting.

"Reserved" is a first-class bed status. It is set only by `BedReservationService`; direct status updates to `reserved` are rejected. This keeps the hold table and bed board consistent.

## 5. Bed updates and optimistic concurrency

`BedManagementService::updateStatus()` runs in a transaction and:

1. Re-checks hospital permission on the locked bed.
2. Rejects inactive beds.
3. Compares `expected_updated_at` (second precision, application timezone) with the locked row; mismatch throws `StaleBedUpdateException` → API `409`.
4. Refuses to change a bed with an active reservation (except a no-op `reserved` report).
5. Requires a reason for `out_of_service`.
6. Writes `bed_status_history` and an audit entry.

The React bed board sends the timestamp it rendered. The API also accepts updates without `expected_updated_at`, but clients should always send it.

## 6. Reservations

`BedReservationService::reserve()` enforces:

- caller is an active receiving-hospital member with `referrals.respond` and `beds.reserve`
- referral status is `accepted`
- referral has no other active reservation
- bed belongs to the receiving hospital, is active, available, and not out of service
- no active reservation exists for that bed (checked under `lockForUpdate`)

The bed is flipped to `reserved` and a `bed_status_history` row plus audit entry are written in the same transaction. The TTL defaults to 120 minutes.

Lifecycle:

| Event | Reservation status | Bed status |
| --- | --- | --- |
| Reserve for accepted referral | `active` | `reserved` |
| Manual release by receiving staff | `released` | `available` |
| Referral cancelled | `cancelled` | `available` |
| TTL elapsed (`reservations:expire`) | `expired` | `available` |
| Patient arrival confirmed | `consumed` | `occupied` |

`ReservationSystemService::releaseForReferral()` is the internal, permission-free release used only by terminal referral transitions that have already authorized the caller. User-initiated release goes through `BedReservationService::release()`, which requires an active membership and role permission.

## 7. Referral workflow

`Referral::TRANSITIONS` is the single transition matrix. `ReferralService::transition()` locks the referral row, validates `canTransitionTo()`, writes `referral_status_history` and an audit entry.

Actions and actors:

| Action | Actor requirement |
| --- | --- |
| Create / submit | active referring-hospital member with `referrals.create` |
| Start review / accept / decline / request info / assign coordinator | active receiving-hospital member with `referrals.respond` |
| Cancel | referring coordinator or receiving responder; in-transit referrals cannot be cancelled |
| Reserve bed | receiving responder with `beds.reserve`, accepted status |
| Schedule transfer / departure | active referring member with `transfers.manage` |
| Arrival / complete | active receiving member with `referrals.respond` or `transfers.manage` |

Acceptance stores `accepted_at`, assigns a coordinator (must be an active receiving-hospital member), and notifies both hospitals. Viewing availability is intentionally independent: the `receivingAvailability` prop and `availableBeds` list do not mutate the referral.

Accepting a referral does not automatically reserve a bed. This keeps clinical acceptance and capacity commitment as separate operational decisions.

## 8. Transfers

`TransferService` locks the referral and transfer rows and updates both state machines together:

- schedule: referral `accepted` → `transfer_scheduled`
- departure: transfer `scheduled`/`ready` → `in_transit`; referral → `in_transit`
- arrival: transfer must be `in_transit`; transfer → `arrived`; referral → `arrived`; active reservation consumed and bed marked `occupied`
- complete: transfer must be `arrived`; transfer → `completed`; referral → `completed` with `closed_at`

Arrival deliberately requires `in_transit` even though the UI ability flag also considers `scheduled` for visibility. The UI only exposes the arrival action for in-transit transfers.

## 9. Documents

Referral documents are stored on the `local` disk:

```
storage/app/private/referral-documents/{referral_public_id}/{uuid}.{ext}
```

They are never served from `public/`. Downloads go through `ReferralController@downloadDocument`, which authorizes referral membership and streams the file. API uploads are limited to five files per request, 10 MB each, with pdf/jpg/jpeg/png/doc/docx types. `ReferralDocument` records the original name, MIME type, size, and uploader.

## 10. Real-time direct messaging

Chat is 1-to-1 direct messaging between active users, available network-wide.

- `conversations` stores a unique `direct_key` (`minUserId-maxUserId`) so a pair shares one thread; `conversation_participants` stores membership and each participant's `last_read_message_id` read cursor; `messages` stores the body, sender, `edited_at`, and `deleted_at`.
- `ChatService` computes unread counts in SQL from the read cursor (excluding deleted messages), builds conversation summaries, creates/reuses direct conversations (tolerating a simultaneous-create race), and advances read cursors. Cursors only move forward.
- Access is participant-only (`ConversationPolicy`); a non-participant receives 403 on read/send/read-receipt endpoints. Starting a conversation validates that the target account is active and not the current user.
- Only the original sender may edit or delete a message. Editing sets `edited_at`; deleting clears the body, sets `deleted_at`, and leaves a tombstone row so ordering, read cursors, and history remain intact. Editing a deleted message is rejected with a 422, and deleting an already-deleted message is idempotent.
- `MessageSent`, `MessageUpdated`, `MessageDeleted`, and `ConversationRead` implement `ShouldBroadcastNow` and broadcast to each participant's private `user.{id}` channel, authorized in `routes/channels.php` by matching the authenticated active user id. Because delivery targets the user channel rather than the page, unread badges update outside `/messages` too.
- The frontend uses a singleton Echo client (`resources/js/lib/echo.js`). `AppLayout` subscribes once per authenticated user, forwards `message.sent` / `message.updated` / `message.deleted` / `conversation.read` events as DOM events, and keeps the sidebar and header badges in sync. `Messages/Index` handles thread state, optimistic sends, auto-read for the open thread, read receipts, inline editing, delete confirmation, and tombstone rendering.
- Messages are capped at 2,000 characters. Chat is a coordination convenience, not part of the clinical record; the UI shows a persistent warning against sharing patient-identifiable information.

## 11. Notifications and audit

`NotificationService` sends database notifications to the relevant hospital members:

- new referrals → receiving responders
- status changes/cancellations → receiving responders + referring viewers
- reservation events → both hospitals
- stale availability → hospital users with `beds.update`

Notifications are read through the authenticated user's own relation, so one user cannot read another's.

`AuditLogger` records actor, hospital context, action, subject, changed fields, IP, and user agent. Audit entries are written for administrative changes, bed updates, reservations, referral transitions, transfers, snapshots, and settings changes. Login attempts are recorded separately in `login_logs`.

## 12. API conventions

- Prefix `/api/v1`.
- Sanctum bearer tokens; web sessions are separate.
- `ApiResponse` trait produces the `{success, message, data, meta}` envelope.
- Exception handlers in `bootstrap/app.php` map validation (422), authentication (401), authorization (403), not-found (404), HTTP exceptions, and generic failures to the same envelope for `api/*`.
- Route throttles: login and password routes; global API limiter 120/min; web mutations, exports, and document downloads use the `sensitive` limiter (20/min); chat sends, edits, deletions, and read receipts use `chat` (60/min).
- List endpoints page with bounded `per_page` (1–100).
- Web errors render the Inertia `Errors/Error` page instead of Laravel's default error screen.

## 13. Time zones

- The application and database timestamps use `Asia/Manila` (`APP_TIMEZONE`, default `Asia/Manila`). This aligns `now()`, report date boundaries, and scheduler cadence with Philippine wall-clock time.
- API strings are ISO-8601 with the application offset (`+08:00`).
- React formats all display timestamps in `Asia/Manila`.
- `datetime-local` values are Philippine wall-clock times. `toDateTimeInput()` formats the stored timestamp as Manila time for editing, and `fromDateTimeInput()` appends `+08:00` before sending, so the intended local time is preserved.

## 14. Testing

`phpunit.xml` points tests at the MySQL `bhn_testing` schema because the PHP SQLite PDO driver is unavailable in this environment. `RefreshDatabase` rebuilds the schema per test; `tests/Concerns/BuildsNetwork.php` creates minimal geography/access/hospital/bed/referral fixtures without running the full demo seeders.

Current coverage:

- freshness thresholds and status-derived counts
- transition matrix and accept/decline flows
- referral/transfer/bed isolation between hospitals
- network-admin aggregate-only restrictions
- membership-only referral scope for network users who also hold a membership
- reservation success, double-booking, out-of-service, cross-hospital, pre-acceptance, and release rules
- bed optimistic concurrency (`409`), reserved-status guard, out-of-service reason, cross-hospital updates
- API login, invalid/inactive credentials, bearer auth, profile, logout revocation, envelope shape
- chat page, unread counts, participant-only authorization, idempotent direct conversations, message validation, delivery broadcasts, read receipts, sender-only editing/deletion, tombstones, and unread-count refresh after deletion
- demo-account gating (default hidden, local-only when enabled), CSV formula-injection neutralisation, and security headers/CSP

Run:

```bash
php artisan test
```

## 15. Operations

- Schedule `reservations:expire` every five minutes (already registered in `routes/console.php`).
- Run `php artisan reverb:start --host=0.0.0.0 --port=8080` alongside the web server for chat. Reverb credentials live in `REVERB_*`; the frontend reads compiled `VITE_REVERB_*` values, so rebuild assets after changing them. Production deployments should terminate TLS in front of Reverb and use `REVERB_SCHEME=https`.
- Run a queue worker if notifications/audits are moved off the sync driver; the current implementation uses database notifications and synchronous writes.
- Run `php artisan storage:link` only if public assets are added; referral documents intentionally remain private.
- `npm run build` produces `public/build`; deploy that directory with the application.
- For production, set a strong `APP_KEY`, HTTPS, restrictive database credentials, and a real mailer for password resets.
- Security hardening: keep `BHN_SHOW_DEMO_ACCOUNTS=false` and `APP_ENV=production` (the demo panel is local-only); set `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and `SESSION_ENCRYPT=true`; Sanctum tokens expire after `SANCTUM_EXPIRATION_MINUTES` (default 480); responses carry CSP, `X-Frame-Options`, `X-Content-Type-Options`, and `Referrer-Policy` headers.
- The login page and demo seeder require `BHN_DEMO_PASSWORD`; the seeder fails closed if it is empty.

## 16. Known limitations / future work

- Beds are manually reported; there is no integration with ADT/hospital information systems.
- Reservation expiry relies on the scheduler; a queue-based job could provide tighter timing.
- Notifications are in-app/database only; SMS/email delivery would need an additional channel.
- Chat supports 1-to-1 text messages only: no group threads, attachments, typing indicators, or push notifications. History is capped at the latest 200 messages per thread in the UI. Deletions are tombstones; there is no hard delete, restore, or edit history.
- Reports are operational and date-bounded; no data warehouse or long-term analytics store.
- The frontend uses page-level code splitting; the shared initial bundle is ~372 kB minified (~117 kB gzipped). Further vendor chunking is possible but not required for current usage.
- Referral documents are stored on the local disk; production deployments with multiple app servers should switch to S3-compatible private storage.
