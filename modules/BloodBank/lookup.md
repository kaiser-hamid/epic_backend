# Lookup API

Pre-fill helpers for the registration and billing forms. Session auth (`erp.auth`); permissions are checked in the session's branch. No pagination: each returns a capped `data` array, `[]` when nothing matches.

| Endpoint | Query | Permission | Matches | Order | Max |
|---|---|---|---|---|---|
| `GET /api/patients/lookup` | `phone` | `sh.patient.view` | exact phone, all branches | newest first | 10 |
| `GET /api/blood-bank/donors/lookup` | `phone` | `bb.donor.view` | exact phone, all branches | newest first | 10 |
| `GET /api/doctors/lookup` | `search` | signed in | active; code or name contains | exact code, then name | 20 |
| `GET /api/staff/lookup` | `search` | signed in | active; employee id or name contains | exact employee id, then name | 20 |
| `GET /api/test-items/lookup` | `search` | signed in | active; code or name contains | exact code, then name | 20 |

`phone` is required, max 20. `search` is required, max 100, case-insensitive.

`/test-items/lookup` also takes optional `exclude_ids[]` (integers, max 200): test items already on the form, never returned. Example: `/api/test-items/lookup?search=cbc&exclude_ids[]=9&exclude_ids[]=12`.

Ages are calculated as of today (Asia/Dhaka) from `date_of_birth`. A donor registered with only an age is aged forward from the registration date.

## Responses

**Patient**

```json
{ "data": [ {
  "id": 12, "patient_no": "P2610280001", "title": null, "name": "Rahim Uddin", "address": null,
  "age_years": 40, "age_months": 2, "age_days": 10, "date_of_birth": "1986-08-19",
  "gender": "male", "marital_status": null, "blood_group": "O_POS",
  "phone": "01711000000", "national_id": null, "created_at": "2026-10-28T11:20:00+06:00"
} ] }
```

**Donor**

```json
{ "data": [ {
  "id": 7, "donor_no": "BB-D-000007", "name": "Karim Mia", "age": 28, "date_of_birth": null,
  "gender": "male", "blood_group": "O_POS", "phone": "01911000000", "address": null,
  "national_id": null, "is_regular_volunteer": true, "created_at": "2026-10-28T11:20:00+06:00"
} ] }
```

**Doctor**

```json
{ "data": [ { "id": 3, "code": "DR-12", "name": "Dr. Zahid", "degree": "MBBS", "address": "...", "phone": "01..." } ] }
```

**Staff**

```json
{ "data": [ { "id": 5, "employee_id": "EMP-0005", "name": "Rahim Uddin", "designation": "Lab Technician" } ] }
```

**Test item** (`rate` in integer paisa, for display only; the bill snapshots it server-side)

```json
{ "data": [ { "id": 9, "code": "CBC", "name": "Complete Blood Count", "department_id": 2, "department_name": "Haematology", "rate": 50000 } ] }
```

## Component types

`GET /api/blood-bank/component-types` — dropdown options for the case form. Signed in, no extra permission, no query params, not paginated. Active types only, ordered by name.

```json
{ "data": [ { "id": 3, "code": "FP", "name": "Fresh Plasma" }, { "id": 5, "code": "PLATELET", "name": "Platelet Concentrate" } ] }
```

Send the chosen `id` as `component_type_id` when registering a case.

## Errors

Standard `{ "message", "code" }`:

| Status | `code` | When |
|---|---|---|
| 401 | `UNAUTHENTICATED` | no session |
| 403 | `FORBIDDEN` | missing permission (patient / donor lookup) |
| 422 | `VALIDATION_FAILED` | `phone` / `search` missing or too long; see `errors` |
