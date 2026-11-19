# BHN REST API (v1)

Base URL: `{APP_URL}/api/v1`

All responses are JSON. Authenticated endpoints require a Sanctum bearer token:

```http
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

There is no public registration endpoint. Accounts are provisioned by network or hospital administrators.

## Response envelope

Success:

```json
{
  "success": true,
  "message": "Referrals retrieved.",
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 0,
      "last_page": 1
    }
  }
}
```

Error:

```json
{
  "success": false,
  "message": "The selected bed is out of service.",
  "errors": {
    "bed": ["An out-of-service bed cannot be reserved."]
  }
}
```

`errors` is present for validation failures (422). Pagination metadata appears under `meta.pagination` where a list is paginated; notifications additionally expose `meta.unread_count`, and availability responses expose `meta.count`.

## Status codes

| Code | Meaning |
| --- | --- |
| 200 | Success |
| 201 | Resource created |
| 401 | Missing/invalid token, invalid credentials, or inactive account |
| 403 | Authenticated but not allowed (policy, hospital scope, or role) |
| 404 | Record not found in the caller's scope |
| 409 | Optimistic-concurrency conflict (stale bed update) |
| 422 | Validation failure, or a workflow rule rejected the action |
| 429 | Rate limit exceeded |

## Authentication

### `POST /auth/login`

Rate limited per email/IP. Body:

```json
{
  "email": "hospitaladmin@bhn.test",
  "password": "Password@123",
  "device_name": "referral-tablet-01"
}
```

Response `data`:

```json
{
  "token": "1|...",
  "token_type": "Bearer",
  "user": {
    "id": 5,
    "name": "Bukidnon Provincial Medical Center Admin",
    "email": "hospitaladmin@bhn.test",
    "is_network_user": false,
    "roles": ["Hospital Administrator"],
    "permissions": ["beds.view", "referrals.view", "..."],
    "primary_hospital_id": 1,
    "has_hospital_membership": true,
    "hospitals": []
  }
}
```

Failed logins return `401` and are recorded in `login_logs`.

Tokens expire after `SANCTUM_EXPIRATION_MINUTES` (default `480` minutes). Login is limited to 10 attempts/min; forgot-password and reset-password are limited to 5/min. Authenticated API routes share a 120 requests/min limiter per user/IP.

### `POST /auth/forgot-password`

Body: `{ "email": "user@example.test" }`. Always returns success to avoid account enumeration. Sends a reset link when an active account matches.

### `POST /auth/reset-password`

```json
{
  "token": "reset-token-from-email",
  "email": "user@example.test",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}
```

Passwords require at least 10 characters with mixed case and numbers. Resetting revokes all existing tokens.

### `GET /auth/me`

Returns the same auth context as the login `user` object.

### `POST /auth/logout`

Revokes the current bearer token.

## Reference data

| Method | Path | Description |
| --- | --- | --- |
| GET | `/reference/municipalities` | Municipality list with province/region IDs |
| GET | `/reference/services` | Active service catalog |

Both require authentication.

## Hospitals and availability

| Method | Path | Permission / scope | Description |
| --- | --- | --- | --- |
| GET | `/hospitals` | Any active user | Directory; filters: `search`, `region_id`, `province_id`, `municipality_id`, `ownership_type`, `category`, `service_id`, `status`, `facility_type` |
| GET | `/hospitals/{hospital}` | `HospitalPolicy@view` | Profile, services, and current availability |
| POST | `/hospitals` | `hospitals.register` | Register a hospital |
| PATCH | `/hospitals/{hospital}` | `hospitals.manage` within scope | Update profile/services. Hospital staff cannot change code, ownership, status, or municipality |
| GET | `/hospitals/{hospital}/services` | `HospitalPolicy@view` | Listed services |
| GET | `/hospitals/{hospital}/availability` | `HospitalPolicy@view` | Counts, categories, freshness |
| GET | `/availability` | Any active user | Bed availability search; filters: `search`, `region_id`, `province_id`, `municipality_id`, `ownership_type`, `category`, `service_id`, `status`, `hospital_id` |
| GET | `/availability/summary` | Any active user | Aggregate network summary for the caller's scope |
| POST | `/availability/snapshots` | `reports.national` or `settings.manage` | Capture availability snapshots for scoped hospitals |

Availability counts are always derived from individual bed statuses. A hospital's freshness is based on its most recent `bed_status_history` entry:

```json
{
  "counts": {
    "total": 40,
    "available": 12,
    "occupied": 22,
    "reserved": 2,
    "cleaning": 2,
    "out_of_service": 2,
    "operational": 38
  },
  "freshness": "fresh",
  "freshness_label": "Fresh",
  "last_updated_at": "2026-11-17T06:45:00+00:00"
}
```

Thresholds default to 15 minutes (fresh) and 60 minutes (aging), and are configurable by a network administrator.

## Beds

| Method | Path | Permission | Description |
| --- | --- | --- | --- |
| GET | `/beds` | `beds.view` | Inventory for one hospital; optional `hospital_id` (defaults to first managed), `ward_id`, `status`, `category`, `search` |
| POST | `/beds` | `beds.manage` at hospital | Create a bed |
| POST | `/beds/bulk-status` | `beds.update` at hospital | Update up to 100 beds; body `hospital_id`, `bed_ids[]`, `status`, `reason` |
| GET | `/beds/{bed}` | `beds.view` + hospital membership/scope | Bed detail |
| PATCH | `/beds/{bed}/status` | `beds.update` at bed's hospital | Report status; optional `reason`, `expected_updated_at` |
| PATCH | `/beds/{bed}/activation` | `beds.manage` at bed's hospital | Activate/deactivate inventory |
| GET | `/beds/{bed}/history` | `beds.view` at bed's hospital | Latest 100 status changes |

### Optimistic concurrency

Send the `updated_at` value you last saw:

```http
PATCH /api/v1/beds/42/status
Content-Type: application/json

{
  "status": "occupied",
  "reason": "Patient admitted",
  "expected_updated_at": "2026-11-17T06:30:00+00:00"
}
```

If another user has changed the bed since then, the API returns `409`:

```json
{
  "success": false,
  "message": "This bed was updated by another user. Reload the bed board and try again."
}
```

Workflow guards:

- `reserved` cannot be set directly; it is set only by the referral reservation service.
- Beds with an active reservation cannot change status until the hold is released.
- `out_of_service` requires a `reason`.

## Referrals

List endpoints return only referrals whose referring or receiving hospital is an **active membership** of the caller. Network/regional roles do not widen this patient-linked scope.

| Method | Path | Who | Description |
| --- | --- | --- | --- |
| GET | `/referrals` | Hospital member with `referrals.view` | Paginated list; filters: `direction` (`incoming`/`outgoing`), `status`, `urgency`, `search`, `per_page` |
| POST | `/referrals` | `referrals.create` at referring hospital | Create draft or submit (`submit: true`) |
| GET | `/referrals/{referral}` | Referring or receiving member | Full coordination detail, abilities, availability, transfer, documents |
| POST | `/referrals/{referral}/submit` | Referring `referrals.create` | Draft → submitted |
| POST | `/referrals/{referral}/start-review` | Receiving `referrals.respond` | Submitted → under review |
| POST | `/referrals/{referral}/accept` | Receiving `referrals.respond` | Submitted/under review/more info → accepted; optional `coordinator_id`, `response_note` |
| POST | `/referrals/{referral}/decline` | Receiving `referrals.respond` | → declined; `decline_reason` required |
| POST | `/referrals/{referral}/request-information` | Receiving `referrals.respond` | → more information required; `note` required |
| POST | `/referrals/{referral}/cancel` | Referring coordinator or receiving responder | → cancelled; `reason` required (5–500 chars); releases any active hold |
| POST | `/referrals/{referral}/assign-coordinator` | Receiving `referrals.respond` | `coordinator_id` must be an active member of the receiving hospital |
| POST | `/referrals/{referral}/reserve-bed` | Receiving `referrals.respond` + `beds.reserve` | Hold an available bed on an accepted referral; body `bed_id` |
| POST | `/referrals/{referral}/transfer` | Referring `transfers.manage` | Schedule transfer (accepted referral only) |
| POST | `/referrals/{referral}/departure` | Referring `transfers.manage` | Mark in transit |
| POST | `/referrals/{referral}/arrival` | Receiving `referrals.respond` or `transfers.manage` | Requires in-transit transfer; consumes the hold and marks the bed occupied |
| POST | `/referrals/{referral}/complete` | Receiving responder/manager | Requires arrived transfer; completes referral |
| POST | `/referrals/{referral}/documents` | Referring or receiving member | Multipart `documents[]`; max 5 files, 10 MB each, pdf/jpg/jpeg/png/doc/docx |

### Status transitions

```
draft                    → submitted | cancelled
submitted                → under_review | accepted | declined | more_information_required | cancelled | expired
under_review             → accepted | declined | more_information_required | cancelled | expired
more_information_required→ submitted | under_review | accepted | declined | cancelled | expired
accepted                 → transfer_scheduled | cancelled
transfer_scheduled       → in_transit | cancelled
in_transit               → arrived | cancelled
arrived                  → completed
declined / completed / cancelled / expired → (terminal)
```

Invalid transitions return `422` with a `status` error. Attempting an action without the right hospital role returns `403`.

Browsing the receiving hospital's availability does **not** accept a referral or hold a bed. Acceptance, reservation, and transfer actions are separate authorized steps.

### Create referral

```json
{
  "referring_hospital_id": 1,
  "receiving_hospital_id": 2,
  "patient_reference": "BPMC-2026-0451",
  "patient_name": "Juan Dela Cruz",
  "patient_age": 54,
  "patient_sex": "male",
  "urgency": "urgent",
  "requested_service_id": 3,
  "referral_reason": "Needs cardiology evaluation and possible catheterization.",
  "contact_name": "Dr. Maria Santos",
  "contact_phone": "0917 555 0100",
  "submit": true
}
```

`referring_hospital_id` must be one where the caller holds an active referral role. The receiving hospital must be operational/limited and different from the referring hospital. A requested service must be listed by the receiving hospital.

### Reserve a bed

```json
{ "bed_id": 128 }
```

Rules enforced inside a database transaction with row locks:

- Referral must be `accepted`.
- Referral must not already have an active reservation.
- Bed must belong to the receiving hospital, be active, available, and not out of service.
- No other active reservation may exist for the bed.

On success the bed becomes `reserved` and the hold expires after the configured TTL (default 120 minutes). Overdue holds are released by the scheduled `reservations:expire` command.

## Transfers

| Method | Path | Permission | Description |
| --- | --- | --- | --- |
| GET | `/transfers` | `transfers.view` + hospital membership | Paginated list; filters `status`, `search`, `per_page` |
| GET | `/transfers/{transfer}` | Referring or receiving member | Transfer detail |
| POST | `/transfers/{transfer}/departure` | Referring `transfers.manage` | Record departure; optional `departure_at`, `vehicle_reference`, `escort_contact` |
| POST | `/transfers/{transfer}/arrival` | Receiving responder/manager | Confirm arrival; optional `arrival_at`, `notes` |
| POST | `/transfers/{transfer}/complete` | Receiving responder/manager | Complete the transfer and referral |

Transfer actions require an active membership at the appropriate hospital. Network administrators cannot read transfer records.

## Reports

| Method | Path | Permission | Description |
| --- | --- | --- | --- |
| GET | `/reports/overview` | `reports.view` or `reports.national` | Scoped operational overview; optional `date_from`, `date_to`, `hospital_id` |
| GET | `/reports/availability` | `reports.view` or `reports.national` | Availability rows for scoped hospitals |
| GET | `/reports/referrals` | `reports.view` + hospital membership | Patient-linked referral rows; network users are rejected with `403` |
| GET | `/reports/transfers` | `reports.view` + hospital membership | Patient-linked transfer rows; network users are rejected with `403` |

CSV exports are available only in the web application (`/reports/export/{type}`) and are similarly blocked for network users.

## Notifications

| Method | Path | Description |
| --- | --- | --- |
| GET | `/notifications` | Caller's notifications, newest first; `per_page` up to 100; `meta.unread_count` |
| POST | `/notifications/{id}/read` | Mark one notification read |
| POST | `/notifications/read-all` | Mark all read |

Notifications are scoped to the authenticated user; IDs from other users return `404`.

## Administration

| Method | Path | Permission | Description |
| --- | --- | --- | --- |
| GET | `/admin/hospitals` | `hospitals.manage` or `hospitals.register` | Hospitals in the caller's management scope |
| GET | `/admin/users` | `users.manage` | Users in scope; filters `search`, `status`, `hospital_id` |
| PATCH | `/admin/users/{user}/status` | `users.manage` | Body `status` (`active`/`inactive`); cannot deactivate self |
| GET | `/admin/audit` | `audit.view` | Audit log in scope; filters `action`, `user_id`, `hospital_id`, `date_from`, `date_to` |
| GET | `/admin/settings` | `settings.manage` + network role | Platform settings |
| PUT | `/admin/settings` | `settings.manage` + network role | Update network name, freshness thresholds, reservation TTL, disclaimer, support email |

## cURL examples

```bash
# Login
curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"referral@bhn.test","password":"Password@123"}'

# List incoming referrals
curl -s "http://localhost:8000/api/v1/referrals?direction=incoming&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# Report bed status with concurrency check
curl -s -X PATCH http://localhost:8000/api/v1/beds/42/status \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"occupied","reason":"Patient admitted","expected_updated_at":"2026-11-17T14:30:00+08:00"}'
```

## Notes

- All timestamps in responses are ISO-8601 strings in the application timezone (`Asia/Manila`, offset `+08:00`). Display them in Asia/Manila on the client.
- Authenticated API routes are limited to 120 requests/min per user/IP; excess requests return `429` with `X-RateLimit-*` headers.
- List endpoints are paginated; `per_page` is bounded to 1–100 where supported.
- Hospital/resource IDs are numeric. Referrals also expose a ULID `public_id`, used for private document folders.
- Bed updates and reservations use database transactions and row locks; stale writes return `409`, workflow violations return `422`.
